<?php

declare(strict_types=1);

namespace Qiuapeng\LaravelWorkerman;

use Throwable;
use Workerman\Worker;
use Workerman\Protocols\Http\Request;
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

    /**
     * 构造函数
     *
     * @param WorkermanConfig $config 配置对象
     */
    public function __construct(WorkermanConfig $config)
    {
        $this->config = $config;
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

        // 设置 Logger 调试模式
        Logger::setDebug($this->config->isDebug());
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
     * 处理每个 HTTP 请求，优先检查静态文件，否则交由应用处理
     *
     * @param TcpConnection $connection TCP 连接对象
     * @param Request       $request    HTTP 请求对象
     *
     * @return void
     */
    private function onMessage(TcpConnection $connection, Request $request): void
    {
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
