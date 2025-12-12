<?php

declare(strict_types=1);

namespace Qiuapeng\LaravelWorkerman;

use Throwable;
use Workerman\Worker;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use Workerman\Connection\TcpConnection;
use Qiuapeng\LaravelWorkerman\Config\WorkermanConfig;

/**
 * Workerman 服务器管理类
 *
 * 封装 Worker 的创建、配置和生命周期管理
 * 提供清晰的回调注册和服务器启动接口
 * 同时支持 Laravel 和 Lumen 框架
 */
final class WorkermanServer
{
    /** @var WorkermanConfig 配置对象 */
    private $config;

    /** @var Worker|null Worker 实例 */
    private $worker = null;

    /** @var AppManager|null 应用管理器 (每个 Worker 进程独立，支持 Laravel/Lumen) */
    private $appManager = null;

    /** @var float 服务器启动时间 */
    private $serverStartTime;

    /** @var string 健康检查端点路径 */
    private const HEALTH_CHECK_PATH = '/health';

    /** @var string 状态端点路径 */
    private const STATUS_PATH = '/_status';

    /**
     * 构造函数
     *
     * @param WorkermanConfig $config 配置对象
     */
    public function __construct(WorkermanConfig $config)
    {
        $this->config = $config;
        $this->serverStartTime = microtime(true);
    }

    /**
     * 初始化并运行服务器
     *
     * @return void
     */
    public function run(): void
    {
        $this->setupEnvironment();
        $this->createWorker();
        $this->registerCallbacks();

        Worker::runAll();
    }

    /**
     * 设置 Worker 运行环境
     *
     * @return void
     */
    private function setupEnvironment(): void
    {
        // 确保运行时目录存在
        $this->config->ensureRuntimeDir();

        // 设置 Workerman 全局配置
        Worker::$pidFile = $this->config->getPidFile();
        Worker::$logFile = $this->config->getLogFile();
        Worker::$stdoutFile = $this->config->getStdoutFile();

        // 初始化 Logger（设置调试模式和日志文件）
        Logger::init(
            $this->config->isDebug(),
            $this->config->getLogFile(),
            $this->config->isDebug() ? Logger::LEVEL_DEBUG : Logger::LEVEL_INFO
        );
    }

    /**
     * 创建 Worker 实例
     *
     * @return void
     */
    private function createWorker(): void
    {
        $this->worker = new Worker($this->config->getListenAddress());
        $this->worker->count = $this->config->getWorkerCount();
        $this->worker->name = $this->config->getName();
    }

    /**
     * 注册 Worker 回调函数
     *
     * @return void
     */
    private function registerCallbacks(): void
    {
        $this->worker->onWorkerStart = function (Worker $worker) {
            $this->onWorkerStart($worker);
        };
        $this->worker->onMessage = function (TcpConnection $conn, Request $req) {
            $this->onMessage($conn, $req);
        };
        $this->worker->onWorkerStop = function (Worker $worker) {
            $this->onWorkerStop($worker);
        };
    }

    /**
     * Worker 启动回调
     *
     * 在每个 Worker 进程启动时调用，负责初始化应用（支持 Laravel/Lumen）
     *
     * @param Worker $worker Worker 实例
     *
     * @return void
     */
    private function onWorkerStart(Worker $worker): void
    {
        $this->appManager = new AppManager(
            $this->config->getBasePath(),
            $this->config->getMaxRequests(),
            $this->config->getPort(),
            $this->config->isDebug()
        );

        try {
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }
            if (function_exists('apc_clear_cache')) {
                apc_clear_cache();
            }

            $startTime = microtime(true);

            $this->appManager->initialize();

            $initTime = round((microtime(true) - $startTime) * 1000, 2);
            $memoryUsed = round(memory_get_usage(true) / 1024 / 1024, 2);

            Logger::info(sprintf(
                'Worker #%d 启动成功 | 初始化: %.2fms | 内存: %sMB',
                $worker->id,
                $initTime,
                $memoryUsed
            ));
        } catch (Throwable $e) {
            $this->handleInitializationError($e);
        }
    }

    /**
     * HTTP 请求回调
     *
     * 处理每个 HTTP 请求，支持健康检查、静态文件和动态请求
     *
     * @param TcpConnection $connection TCP 连接对象
     * @param Request       $request    HTTP 请求对象
     *
     * @return void
     */
    private function onMessage(TcpConnection $connection, Request $request): void
    {
        $path = parse_url($request->uri(), PHP_URL_PATH);

        // 健康检查端点（优先处理，不经过 Laravel）
        if ($path === self::HEALTH_CHECK_PATH) {
            $connection->send($this->handleHealthCheck());
            return;
        }

        // 状态端点（仅调试模式可用）
        if ($path === self::STATUS_PATH && $this->config->isDebug()) {
            $connection->send($this->handleStatus());
            return;
        }

        // 尝试处理静态文件
        if ($this->config->isStaticEnabled()) {
            $staticResponse = StaticFileHandler::handle($request, $this->config->getStaticPath());
            if ($staticResponse !== null) {
                $connection->send($staticResponse);
                return;
            }
        }

        // 应用处理动态请求 (Laravel/Lumen)
        $response = $this->appManager->handleRequest($request);
        $connection->send($response);
    }

    /**
     * 处理健康检查请求
     *
     * 返回服务器健康状态，供负载均衡器和监控系统使用
     *
     * @return Response
     */
    private function handleHealthCheck(): Response
    {
        $uptime = round(microtime(true) - $this->serverStartTime, 2);
        $memoryUsage = round(memory_get_usage(true) / 1024 / 1024, 2);
        $memoryPeak = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

        $health = [
            'status'    => 'healthy',
            'timestamp' => date('c'),
            'uptime'    => $uptime,
            'memory'    => [
                'current_mb' => $memoryUsage,
                'peak_mb'    => $memoryPeak,
            ],
            'worker'    => [
                'pid'      => getmypid(),
                'requests' => $this->appManager !== null ? $this->appManager->getRequestCount() : 0,
            ],
        ];

        $response = new Response(200);
        $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        $response->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->withBody(json_encode($health, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return $response;
    }

    /**
     * 处理状态请求（仅调试模式）
     *
     * 返回详细的服务器状态信息
     *
     * @return Response
     */
    private function handleStatus(): Response
    {
        $stats = $this->appManager !== null ? $this->appManager->getStats() : [];
        $uptime = round(microtime(true) - $this->serverStartTime, 2);

        $status = [
            'status'    => 'ok',
            'timestamp' => date('c'),
            'uptime'    => $uptime,
            'config'    => [
                'host'         => $this->config->getHost(),
                'port'         => $this->config->getPort(),
                'workers'      => $this->config->getWorkerCount(),
                'max_requests' => $this->config->getMaxRequests(),
                'debug'        => $this->config->isDebug(),
            ],
            'memory'    => [
                'current_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'peak_mb'    => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ],
            'stats'     => [
                'total_requests' => $stats['total_requests'] ?? 0,
                'avg_time_ms'    => $this->calculateAverageTime($stats),
                'min_time_ms'    => $stats['min_time_ms'] ?? 0,
                'max_time_ms'    => $stats['max_time_ms'] ?? 0,
            ],
            'worker'    => [
                'pid'       => getmypid(),
                'framework' => $this->appManager !== null ? $this->appManager->getFrameworkType() : 'unknown',
            ],
            'php'       => [
                'version'    => PHP_VERSION,
                'extensions' => get_loaded_extensions(),
            ],
        ];

        $response = new Response(200);
        $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        $response->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->withBody(json_encode($status, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return $response;
    }

    /**
     * Worker 停止回调
     *
     * 在 Worker 进程停止时调用，输出统计信息
     *
     * @param Worker $worker Worker 实例
     *
     * @return void
     */
    private function onWorkerStop(Worker $worker): void
    {
        // 清理静态文件处理器缓存
        StaticFileHandler::clearCache();

        if ($this->appManager === null || $this->appManager->getRequestCount() === 0) {
            return;
        }

        $stats = $this->appManager->getStats();
        $avgTime = $this->calculateAverageTime($stats);
        $peakMemory = $this->appManager->getPeakMemory();

        Logger::info(sprintf(
            'Worker #%d 停止 | 请求数: %d | 平均响应: %.2fms | 峰值内存: %sMB',
            $worker->id,
            $stats['total_requests'],
            $avgTime,
            $peakMemory
        ));
    }

    /**
     * 处理初始化错误
     *
     * @param Throwable $e 异常对象
     *
     * @return void
     */
    private function handleInitializationError(Throwable $e): void
    {
        Logger::error("初始化失败: {$e->getMessage()}");

        if ($this->config->isDebug()) {
            Logger::error($e->getTraceAsString());
        }

        Worker::stopAll();
    }

    /**
     * 计算平均响应时间
     *
     * @param array<string, mixed> $stats 统计数据
     *
     * @return float
     */
    private function calculateAverageTime(array $stats): float
    {
        $totalRequests = max(1, $stats['total_requests'] ?? 0);
        $totalTime = $stats['total_time_ms'] ?? 0;

        return round($totalTime / $totalRequests, 2);
    }
}
