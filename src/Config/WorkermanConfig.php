<?php

declare(strict_types=1);

namespace Qiuapeng\LaravelWorkerman\Config;

/**
 * Workerman 配置管理器
 *
 * 负责加载和管理 Workerman 服务器的所有配置项
 * 支持配置文件、环境变量、命令行参数三级覆盖
 */
final class WorkermanConfig
{
    /** @var string 服务器监听地址 */
    private $host;

    /** @var int 服务器监听端口 */
    private $port;

    /** @var int Worker 进程数量 */
    private $workerCount;

    /** @var int 单个 Worker 最大请求数 */
    private $maxRequests;

    /** @var bool 是否启用调试模式 */
    private $debug;

    /** @var string Worker 进程名称 */
    private $name;

    /** @var bool 是否启用静态文件服务 */
    private $staticEnable;

    /** @var string 静态文件目录路径 */
    private $staticPath;

    /** @var string 日志文件路径 */
    private $logFile;

    /** @var string 项目根目录 */
    private $basePath;

    /** @var string 运行时目录 */
    private $runtimeDir;

    /**
     * 配置构造函数
     *
     * @param string $basePath 项目根目录
     */
    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
        $this->runtimeDir = $basePath . '/storage/workerman';
    }

    /**
     * 从配置文件和命令行参数加载配置
     *
     * @param array<string, mixed> $cliOptions 命令行参数 (getopt 返回值)
     *
     * @return self
     */
    public function load(array $cliOptions = []): self
    {
        // 加载配置文件
        $config = $this->loadConfigFile();

        // 解析各项配置（优先级：命令行 > 配置文件 > 环境变量 > 默认值）
        $this->host = $this->resolveString($config, 'host', 'WORKERMAN_HOST', '0.0.0.0');
        $this->port = $this->resolvePort($config, $cliOptions);
        $this->workerCount = $this->resolveWorkerCount($config, $cliOptions);
        $this->maxRequests = $this->resolveMaxRequests($config, $cliOptions);
        $this->debug = $this->resolveDebug($config, $cliOptions);
        $this->name = $this->resolveString($config, 'name', 'WORKERMAN_NAME', 'laravel-workerman');

        // 静态文件配置
        $staticConfig = $config['static'] ?? [];
        $this->staticEnable = (bool)($staticConfig['enable'] ?? true);
        $this->staticPath = $staticConfig['path'] ?? $this->basePath . '/public';

        // 日志配置
        $logConfig = $config['log'] ?? [];
        $this->logFile = $logConfig['file'] ?? $this->runtimeDir . '/workerman.log';

        return $this;
    }

    /**
     * 加载配置文件
     *
     * @return array<string, mixed>
     */
    private function loadConfigFile(): array
    {
        $configFile = $this->basePath . '/config/workerman.php';

        if (file_exists($configFile)) {
            return require $configFile;
        }

        return [];
    }

    /**
     * 解析字符串类型配置
     *
     * @param array<string, mixed> $config  配置数组
     * @param string               $key     配置键名
     * @param string               $envKey  环境变量名
     * @param string               $default 默认值
     *
     * @return string
     */
    private function resolveString(array $config, string $key, string $envKey, string $default): string
    {
        return (string)($config[$key] ?? getenv($envKey) ?: $default);
    }

    /**
     * 解析端口配置
     *
     * @param array<string, mixed> $config     配置数组
     * @param array<string, mixed> $cliOptions 命令行参数
     *
     * @return int
     */
    private function resolvePort(array $config, array $cliOptions): int
    {
        $port = $cliOptions['p'] ?? $cliOptions['port'] ?? $config['port'] ?? getenv('WORKERMAN_PORT') ?: 8080;
        return (int)$port;
    }

    /**
     * 解析 Worker 进程数配置
     *
     * @param array<string, mixed> $config     配置数组
     * @param array<string, mixed> $cliOptions 命令行参数
     *
     * @return int
     */
    private function resolveWorkerCount(array $config, array $cliOptions): int
    {
        $workers = $cliOptions['w'] ?? $cliOptions['workers'] ?? $config['workers'] ?? getenv('WORKERMAN_WORKERS') ?: 4;

        // Windows 系统只能使用单进程
        if (PHP_OS_FAMILY === 'Windows') {
            return 1;
        }

        return (int)$workers;
    }

    /**
     * 解析最大请求数配置
     *
     * @param array<string, mixed> $config     配置数组
     * @param array<string, mixed> $cliOptions 命令行参数
     *
     * @return int
     */
    private function resolveMaxRequests(array $config, array $cliOptions): int
    {
        $maxRequests = $cliOptions['m'] ?? $cliOptions['max'] ?? $config['max_requests'] ?? getenv('WORKERMAN_MAX_REQUESTS') ?: 10000;
        return (int)$maxRequests;
    }

    /**
     * 解析调试模式配置
     *
     * @param array<string, mixed> $config     配置数组
     * @param array<string, mixed> $cliOptions 命令行参数
     *
     * @return bool
     */
    private function resolveDebug(array $config, array $cliOptions): bool
    {
        return isset($cliOptions['debug'])
            || ($config['debug'] ?? false)
            || getenv('WORKERMAN_DEBUG');
    }

    // =========================================================================
    // Getter 方法
    // =========================================================================

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getWorkerCount(): int
    {
        return $this->workerCount;
    }

    public function getMaxRequests(): int
    {
        return $this->maxRequests;
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isStaticEnabled(): bool
    {
        return $this->staticEnable;
    }

    public function getStaticPath(): string
    {
        return $this->staticPath;
    }

    public function getLogFile(): string
    {
        return $this->logFile;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public function getRuntimeDir(): string
    {
        return $this->runtimeDir;
    }

    /**
     * 获取 Worker 监听地址
     *
     * @return string 格式: http://host:port
     */
    public function getListenAddress(): string
    {
        return "http://$this->host:$this->port";
    }

    /**
     * 获取 PID 文件路径
     *
     * @return string
     */
    public function getPidFile(): string
    {
        return $this->runtimeDir . '/workerman.pid';
    }

    /**
     * 获取标准输出日志文件路径
     *
     * @return string
     */
    public function getStdoutFile(): string
    {
        return $this->runtimeDir . '/stdout.log';
    }

    /**
     * 确保运行时目录存在
     *
     * @return void
     */
    public function ensureRuntimeDir(): void
    {
        if (!is_dir($this->runtimeDir)) {
            mkdir($this->runtimeDir, 0755, true);
        }
    }
}
