<?php

namespace Qiuapeng\WorkermanLaravel;

use Throwable;
use Workerman\Worker;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Workerman\Connection\TcpConnection;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\UploadedFile;

/**
 * Laravel 应用管理器
 *
 * 负责在 Workerman 进程中管理 Laravel 应用实例
 */
class LaravelAppManager
{
    /** @var Application Laravel 应用实例 */
    private Application $app;

    /** @var \Illuminate\Contracts\Http\Kernel HTTP 内核 */
    private $kernel;

    /** @var bool 是否已初始化 */
    private bool $initialized = false;

    /** @var int 当前进程已处理的请求数 */
    private int $requestCount = 0;

    /** @var int 最大请求数 */
    private int $maxRequests;

    /** @var float 峰值内存 */
    private float $peakMemory = 0;

    /** @var array 性能统计 */
    private array $stats = [
        'total_requests' => 0,
        'total_time_ms'  => 0,
        'min_time_ms'    => PHP_FLOAT_MAX,
        'max_time_ms'    => 0,
    ];

    /** @var string 基础路径 */
    private string $basePath;

    /** @var int 监听端口 */
    private int $port;

    /** @var bool 是否开启调试模式 */
    private bool $debug;

    /**
     * @param string $basePath    Laravel 项目根目录
     * @param int    $maxRequests 最大请求数
     * @param int    $port        监听端口
     */
    public function __construct(string $basePath, int $maxRequests = 10000, int $port = 8080, bool $debug = false)
    {
        $this->basePath = $basePath;
        $this->maxRequests = $maxRequests;
        $this->port = $port;
        $this->debug = $debug || getenv('WORKERMAN_DEBUG') === 'true';
    }

    /**
     * 初始化 Laravel 应用（只执行一次）
     */
    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->app = require $this->basePath . '/bootstrap/app.php';
        $this->kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);
        $this->bootstrapApp();

        $this->initialized = true;
    }

    /**
     * 引导 Laravel 应用
     */
    private function bootstrapApp(): void
    {
        $mockRequest = \Illuminate\Http\Request::create(
            'http://localhost/',
            'GET',
            [],
            [],
            [],
            [
                'HTTP_HOST'   => 'localhost',
                'SERVER_NAME' => 'localhost',
                'SERVER_PORT' => $this->port,
                'SCRIPT_NAME' => '/index.php',
                'REQUEST_URI' => '/',
            ]
        );

        $this->app->instance('request', $mockRequest);
        Facade::clearResolvedInstance('request');

        $response = $this->kernel->handle($mockRequest);
        $this->kernel->terminate($mockRequest, $response);
    }

    /**
     * 处理 HTTP 请求
     */
    public function handleRequest(Request $workermanRequest): Response
    {
        $this->requestCount++;
        $requestStartTime = microtime(true);

        // 调试模式：打印请求信息
        if ($this->debug) {
            $this->logDebugRequest($workermanRequest);
        }

        try {
            $laravelRequest = $this->convertRequest($workermanRequest, $requestStartTime);

            $this->app->instance('request', $laravelRequest);
            Facade::clearResolvedInstance('request');

            $laravelResponse = $this->kernel->handle($laravelRequest);
            $response = $this->convertResponse($laravelResponse);

            $this->kernel->terminate($laravelRequest, $laravelResponse);

            $requestTime = (microtime(true) - $requestStartTime) * 1000;
            $this->updateStats($requestTime);
            $this->cleanupRequest();

            // 调试模式：打印响应信息
            if ($this->debug) {
                Logger::debug(sprintf('Response: %d, Time: %.2fms', $laravelResponse->getStatusCode(), $requestTime));
            }

            return $response;

        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * 调试模式：打印请求信息
     */
    private function logDebugRequest(Request $workermanRequest): void
    {
        $method = $workermanRequest->method();
        $uri = $workermanRequest->uri();

        Logger::debug("{$method} {$uri}");

        // 打印 Query 参数
        $query = $workermanRequest->get();
        if (!empty($query)) {
            Logger::debug('Query: ' . json_encode($query, JSON_UNESCAPED_UNICODE));
        }

        // 打印 POST 参数（非文件）
        $post = $workermanRequest->post();
        if (!empty($post)) {
            Logger::debug('POST: ' . json_encode($post, JSON_UNESCAPED_UNICODE));
        }

        // 打印上传文件信息
        $files = $workermanRequest->file();
        if (!empty($files)) {
            $fileInfo = [];
            foreach ($files as $key => $file) {
                if (is_array($file) && isset($file['name'])) {
                    $fileInfo[$key] = $file['name'];
                } elseif (is_object($file) && method_exists($file, 'getClientOriginalName')) {
                    $fileInfo[$key] = $file->getClientOriginalName();
                }
            }
            Logger::debug('Files: ' . json_encode($fileInfo, JSON_UNESCAPED_UNICODE));
        }

        // 打印 JSON Body（如果是 JSON 请求）
        $contentType = $workermanRequest->header('content-type') ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $rawBody = $workermanRequest->rawBody();
            if (!empty($rawBody)) {
                $jsonData = json_decode($rawBody, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    Logger::debug('JSON Body: ' . json_encode($jsonData, JSON_UNESCAPED_UNICODE));
                }
            }
        }
    }

    /**
     * 生成唯一请求 ID
     */
    private function generateRequestId(): string
    {
        return sprintf(
            '%s%04x',
            md5(uniqid((string)mt_rand(), true)),
            getmypid() % 0xffff
        );
    }

    /**
     * 转换 Workerman 请求为 Laravel 请求
     */
    private function convertRequest(Request $workermanRequest, float $requestStartTime): \Illuminate\Http\Request
    {
        $connection = $workermanRequest->connection;
        $remoteIp = $connection !== null ? $connection->getRemoteIp() : '127.0.0.1';
        $remotePort = $connection !== null ? $connection->getRemotePort() : 0;

        $server = [
            'REQUEST_METHOD'     => $workermanRequest->method(),
            'REQUEST_URI'        => $workermanRequest->uri(),
            'QUERY_STRING'       => $workermanRequest->queryString() ?? '',
            'SERVER_PROTOCOL'    => 'HTTP/1.1',
            'SERVER_NAME'        => $workermanRequest->host() ?? 'localhost',
            'HTTP_HOST'          => $workermanRequest->host() ?? 'localhost',
            'HTTPS'              => 'off',
            'REMOTE_ADDR'        => $remoteIp,
            'REMOTE_PORT'        => $remotePort,
            'SERVER_PORT'        => $this->port,
            'DOCUMENT_ROOT'      => $this->basePath . '/public',
            'SCRIPT_FILENAME'    => $this->basePath . '/public/index.php',
            'SCRIPT_NAME'        => '/index.php',
            'REQUEST_TIME'       => time(),
            'REQUEST_TIME_FLOAT' => microtime(true),
            'REQUEST_ID'         => $this->generateRequestId(),
            'START_TIME'         => $requestStartTime,
        ];

        foreach ($workermanRequest->header() as $name => $value) {
            $name = strtoupper(str_replace('-', '_', $name));
            $server['HTTP_' . $name] = $value;
        }

        if ($contentType = $workermanRequest->header('content-type')) {
            $server['CONTENT_TYPE'] = $contentType;
        }
        if ($contentLength = $workermanRequest->header('content-length')) {
            $server['CONTENT_LENGTH'] = $contentLength;
        }

        $query = $workermanRequest->get() ?? [];
        $post = $workermanRequest->post() ?? [];
        $rawBody = $workermanRequest->rawBody();

        $contentType = $server['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false && !empty($rawBody)) {
            $jsonData = json_decode($rawBody, true);
            if (is_array($jsonData)) {
                $post = array_merge($post, $jsonData);
            }
        }

        $cookies = $workermanRequest->cookie() ?? [];
        $files = $this->convertUploadedFiles($workermanRequest->file() ?? []);

        $laravelRequest = new \Illuminate\Http\Request($query, $post, [], $cookies, $files, $server, $rawBody);
        $laravelRequest->setMethod($workermanRequest->method());

        return $laravelRequest;
    }

    /**
     * 转换上传文件
     *
     * 支持多种文件格式：
     * 1. 标准 PHP 数组格式 ['tmp_name' => ..., 'name' => ..., ...]
     * 2. Workerman 对象格式
     *
     * @param array $files Workerman 请求中的文件数据
     *
     * @return array 转换后的 Symfony UploadedFile 数组
     */
    private function convertUploadedFiles(array $files): array
    {
        $converted = [];

        foreach ($files as $key => $file) {
            // 情况 1: 标准 PHP 数组格式
            if (is_array($file) && isset($file['tmp_name'])) {
                // 单文件上传
                if (is_string($file['tmp_name'])) {
                    if (!empty($file['tmp_name']) && is_file($file['tmp_name'])) {
                        $converted[$key] = new UploadedFile(
                            $file['tmp_name'],
                            $file['name'] ?? '',
                            $file['type'] ?? null,
                            $file['error'] ?? UPLOAD_ERR_OK,
                            true
                        );
                    }
                } // 多文件上传 (file[] 格式)
                elseif (is_array($file['tmp_name'])) {
                    $converted[$key] = [];
                    foreach ($file['tmp_name'] as $index => $tmpName) {
                        if (!empty($tmpName) && is_file($tmpName)) {
                            $converted[$key][] = new UploadedFile(
                                $tmpName,
                                $file['name'][$index] ?? '',
                                $file['type'][$index] ?? null,
                                $file['error'][$index] ?? UPLOAD_ERR_OK,
                                true
                            );
                        }
                    }
                }
            } // 情况 2: Workerman 对象格式（某些版本返回对象）
            elseif (is_object($file)) {
                $tmpName = null;
                $name = '';
                $type = null;
                $error = UPLOAD_ERR_OK;

                // 尝试通过方法获取
                if (method_exists($file, 'getPathname')) {
                    $tmpName = $file->getPathname();
                } elseif (property_exists($file, 'tmp_name')) {
                    $tmpName = $file->tmp_name;
                }

                if (method_exists($file, 'getClientOriginalName')) {
                    $name = $file->getClientOriginalName();
                } elseif (property_exists($file, 'name')) {
                    $name = $file->name;
                }

                if (method_exists($file, 'getClientMimeType')) {
                    $type = $file->getClientMimeType();
                } elseif (property_exists($file, 'type')) {
                    $type = $file->type;
                }

                if (method_exists($file, 'getError')) {
                    $error = $file->getError();
                } elseif (property_exists($file, 'error')) {
                    $error = $file->error;
                }

                if ($tmpName && is_file($tmpName)) {
                    $converted[$key] = new UploadedFile(
                        $tmpName,
                        $name,
                        $type,
                        $error,
                        true
                    );
                }
            }
        }

        return $converted;
    }

    /**
     * 转换 Laravel 响应为 Workerman 响应
     */
    private function convertResponse(\Symfony\Component\HttpFoundation\Response $laravelResponse): Response
    {
        return new Response(
            $laravelResponse->getStatusCode(),
            $laravelResponse->headers->all(),
            $laravelResponse->getContent()
        );
    }

    /**
     * 更新性能统计
     */
    private function updateStats(float $requestTimeMs): void
    {
        $this->stats['total_requests']++;
        $this->stats['total_time_ms'] += $requestTimeMs;
        $this->stats['min_time_ms'] = min($this->stats['min_time_ms'], $requestTimeMs);
        $this->stats['max_time_ms'] = max($this->stats['max_time_ms'], $requestTimeMs);

        $currentMemory = memory_get_usage(true) / 1024 / 1024;
        $this->peakMemory = max($this->peakMemory, $currentMemory);
    }

    /**
     * 清理请求相关资源
     *
     * 注意：绝对不能调用 $this->app->flush()，这会清除所有绑定包括核心服务
     */
    private function cleanupRequest(): void
    {
        // 检查是否达到最大请求数限制
        if ($this->requestCount >= $this->maxRequests) {
            $avgTime = round($this->stats['total_time_ms'] / max(1, $this->stats['total_requests']), 2);
            Logger::info("已处理 {$this->requestCount} 个请求，平均耗时 {$avgTime}ms，峰值内存 {$this->peakMemory}MB");
            Logger::info('达到最大请求数限制，Worker 进程重启中...');
            Worker::stopAll();
            return;
        }

        // 1. 清理请求级别的实例（这些在每次请求后需要重置）
        $this->clearRequestInstances();

        // 2. 清理 Facade 缓存的实例
        Facade::clearResolvedInstances();

        // 3. 清理数据库查询日志（防止内存膨胀）
        $this->flushDatabaseQueryLog();

        // 4. 定期执行垃圾回收
        if ($this->requestCount % 100 === 0) {
            gc_collect_cycles();

            // PHP 7.4+ 清理内存缓存
            if (function_exists('gc_mem_caches')) {
                gc_mem_caches();
            }
        }
    }

    /**
     * 清理请求级别的容器实例
     *
     * 只清理与单次请求相关的实例，保留核心服务
     */
    private function clearRequestInstances(): void
    {
        // 需要在每次请求后清理的抽象绑定
        $requestScopedAbstracts = [
            'request',
            'Illuminate\Http\Request',
        ];

        foreach ($requestScopedAbstracts as $abstract) {
            if ($this->app->resolved($abstract)) {
                $this->app->forgetInstance($abstract);
            }
        }

        // 清理 Session（如果使用）
        if ($this->app->bound('session')) {
            try {
                $session = $this->app->make('session');
                if (method_exists($session, 'flush')) {
                    // 只清理 session 驱动，不清理整个 session manager
                    $driver = $session->driver();
                    if (method_exists($driver, 'flush')) {
                        $driver->flush();
                    }
                }
            } catch (Throwable $e) {
                // 忽略 Session 清理错误
            }
        }

        // 重置 Auth guard 状态
        if ($this->app->bound('auth')) {
            try {
                $auth = $this->app->make('auth');
                // 清理所有 guard 的缓存用户
                foreach (['web', 'api', 'sanctum'] as $guard) {
                    try {
                        $guardInstance = $auth->guard($guard);
                        if (method_exists($guardInstance, 'forgetUser')) {
                            $guardInstance->forgetUser();
                        }
                    } catch (Throwable $e) {
                        // 忽略不存在的 guard
                    }
                }
            } catch (Throwable $e) {
                // 忽略 Auth 清理错误
            }
        }

        // 清理 Cookie 队列
        if ($this->app->bound('cookie')) {
            try {
                $cookie = $this->app->make('cookie');
                if (method_exists($cookie, 'flushQueuedCookies')) {
                    $cookie->flushQueuedCookies();
                }
            } catch (Throwable $e) {
                // 忽略
            }
        }
    }

    /**
     * 清理数据库查询日志
     */
    private function flushDatabaseQueryLog(): void
    {
        if (!$this->app->bound('db')) {
            return;
        }

        try {
            $db = $this->app->make('db');
            foreach ($db->getConnections() as $connection) {
                $connection->flushQueryLog();
            }
        } catch (Throwable $e) {
            // 忽略数据库清理错误
        }
    }

    /**
     * 处理异常
     */
    private function handleException(Throwable $e): Response
    {
        try {
            $handler = $this->app->make(ExceptionHandler::class);
            $handler->report($e);
        } catch (Throwable $reportException) {
            // 忽略
        }

        $response = new Response(500);
        $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        $response->withBody(json_encode([
            'code'    => 500,
            'message' => 'Internal Server Error',
        ], JSON_UNESCAPED_UNICODE));

        return $response;
    }

    public function getStats(): array
    {
        return $this->stats;
    }

    public function getRequestCount(): int
    {
        return $this->requestCount;
    }

    public function getPeakMemory(): float
    {
        return round($this->peakMemory, 2);
    }
}
