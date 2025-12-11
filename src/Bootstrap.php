<?php

declare(strict_types=1);

namespace Qiuapeng\WorkermanLaravel;

use Workerman\Worker;
use Qiuapeng\WorkermanLaravel\Config\WorkermanConfig;

/**
 * Workerman 引导类
 *
 * 负责环境检查、命令行参数解析和服务器启动
 * 作为入口脚本的封装，提供清晰的启动流程
 */
final class Bootstrap
{
    /** @var string 项目根目录 */
    private string $basePath;

    /** @var array<string> 命令行参数 */
    private array $argv;

    /** @var string Workerman 版本要求 */
    private const WORKERMAN_MIN_VERSION = '4.0.0';

    /**
     * 构造函数
     *
     * @param string        $basePath 项目根目录
     * @param array<string> $argv     命令行参数 (通常是 $GLOBALS['argv'])
     */
    public function __construct(string $basePath, array $argv = [])
    {
        $this->basePath = $basePath;
        $this->argv = $argv;
    }

    /**
     * 运行 Workerman 服务器
     *
     * @return void
     */
    public function run(): void
    {
        $this->defineConstants();
        $this->validateEnvironment();
        $this->checkWindowsCommand();

        $config = $this->loadConfig();
        $server = new WorkermanServer($config);
        $server->run();
    }

    /**
     * 定义必要的常量
     *
     * @return void
     */
    private function defineConstants(): void
    {
        if (!defined('WORKERMAN_RUNNING')) {
            define('WORKERMAN_RUNNING', true);
        }
    }

    /**
     * 验证运行环境
     *
     * @return void
     * @throws \RuntimeException 环境不满足要求时抛出
     */
    private function validateEnvironment(): void
    {
        // 检查 CLI 模式
        if (PHP_SAPI !== 'cli') {
            $this->exitWithError('请使用命令行运行: php workerman.php start');
        }

        // 检查 Workerman 是否安装
        if (!class_exists(Worker::class)) {
            $this->exitWithError('错误: 未找到 Workerman，请执行 composer require workerman/workerman');
        }

        // 检查 Workerman 版本（可选，但推荐）
        if (defined('WORKERMAN_VERSION') && version_compare(Worker::VERSION, self::WORKERMAN_MIN_VERSION, '<')) {
            $this->exitWithError(sprintf(
                '错误: Workerman 版本过低，需要 >= %s，当前版本: %s',
                self::WORKERMAN_MIN_VERSION,
                Worker::VERSION
            ));
        }
    }

    /**
     * 检查 Windows 不支持的命令
     *
     * @return void
     */
    private function checkWindowsCommand(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return;
        }

        $command = $this->argv[1] ?? '';
        $unsupportedCommands = ['stop', 'restart', 'reload', 'status'];

        if (in_array($command, $unsupportedCommands, true)) {
            $this->exitWithError("Windows 不支持 {$command} 命令，请使用 Ctrl+C 停止服务");
        }
    }

    /**
     * 加载配置
     *
     * @return WorkermanConfig
     */
    private function loadConfig(): WorkermanConfig
    {
        $cliOptions = getopt('p:w:m:', ['port:', 'workers:', 'max:', 'debug']);

        return (new WorkermanConfig($this->basePath))
            ->load($cliOptions ?: []);
    }

    /**
     * 输出错误信息并退出
     *
     * @param string $message 错误信息
     *
     * @return never
     */
    private function exitWithError(string $message): void
    {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}
