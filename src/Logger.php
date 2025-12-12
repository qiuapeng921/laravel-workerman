<?php

declare(strict_types=1);

namespace Qiuapeng\LaravelWorkerman;

/**
 * 日志工具类
 *
 * 提供统一的日志输出接口，支持不同级别的日志
 * 支持控制台输出和文件日志
 */
final class Logger
{
    /** @var bool 是否开启调试模式 */
    private static $debug = false;

    /** @var string|null 日志文件路径 */
    private static $logFile = null;

    /** @var int 日志级别阈值 */
    private static $logLevel = self::LEVEL_DEBUG;

    /** @var int DEBUG 级别 */
    public const LEVEL_DEBUG = 0;

    /** @var int INFO 级别 */
    public const LEVEL_INFO = 1;

    /** @var int WARN 级别 */
    public const LEVEL_WARN = 2;

    /** @var int ERROR 级别 */
    public const LEVEL_ERROR = 3;

    /** @var array<string, int> 日志级别映射 */
    private const LEVEL_MAP = [
        'DEBUG' => self::LEVEL_DEBUG,
        'INFO' => self::LEVEL_INFO,
        'WARN' => self::LEVEL_WARN,
        'ERROR' => self::LEVEL_ERROR,
    ];

    /**
     * 初始化 Logger
     *
     * @param bool        $debug    是否开启调试模式
     * @param string|null $logFile  日志文件路径
     * @param int         $logLevel 日志级别阈值
     *
     * @return void
     */
    public static function init(bool $debug = false, ?string $logFile = null, int $logLevel = self::LEVEL_DEBUG): void
    {
        self::$debug = $debug;
        self::$logFile = $logFile;
        self::$logLevel = $logLevel;
    }

    /**
     * 重置 Logger 状态
     *
     * 主要用于测试或需要重新初始化的场景
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$debug = false;
        self::$logFile = null;
        self::$logLevel = self::LEVEL_DEBUG;
    }

    /**
     * 设置调试模式
     *
     * @param bool $debug 是否开启调试
     *
     * @return void
     */
    public static function setDebug(bool $debug): void
    {
        self::$debug = $debug;
    }

    /**
     * 是否开启调试模式
     *
     * @return bool
     */
    public static function isDebug(): bool
    {
        return self::$debug;
    }

    /**
     * 设置日志文件路径
     *
     * @param string|null $logFile 日志文件路径，null 表示只输出到控制台
     *
     * @return void
     */
    public static function setLogFile(?string $logFile): void
    {
        self::$logFile = $logFile;
    }

    /**
     * 设置日志级别阈值
     *
     * @param int $level 日志级别
     *
     * @return void
     */
    public static function setLogLevel(int $level): void
    {
        self::$logLevel = $level;
    }

    /**
     * 输出信息日志
     *
     * @param string $message 日志消息
     * @param array<string, mixed> $context 上下文数据
     *
     * @return void
     */
    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }

    /**
     * 输出调试日志（仅在调试模式下）
     *
     * @param string $message 日志消息
     * @param array<string, mixed> $context 上下文数据
     *
     * @return void
     */
    public static function debug(string $message, array $context = []): void
    {
        if (self::$debug) {
            self::log('DEBUG', $message, $context);
        }
    }

    /**
     * 输出警告日志
     *
     * @param string $message 日志消息
     * @param array<string, mixed> $context 上下文数据
     *
     * @return void
     */
    public static function warning(string $message, array $context = []): void
    {
        self::log('WARN', $message, $context);
    }

    /**
     * 输出错误日志
     *
     * @param string $message 日志消息
     * @param array<string, mixed> $context 上下文数据
     *
     * @return void
     */
    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }

    /**
     * 输出日志
     *
     * @param string $level   日志级别
     * @param string $message 日志消息
     * @param array<string, mixed> $context 上下文数据
     *
     * @return void
     */
    private static function log(string $level, string $message, array $context = []): void
    {
        // 检查日志级别
        $levelValue = self::LEVEL_MAP[$level] ?? self::LEVEL_DEBUG;
        if ($levelValue < self::$logLevel) {
            return;
        }

        // 格式化日志消息
        $time = date('Y-m-d H:i:s');
        $pid = function_exists('getmypid') ? getmypid() : 0;

        // 如果有上下文数据，追加到消息末尾
        $contextStr = '';
        if (!empty($context)) {
            $contextStr = ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }

        $logLine = sprintf("[%s] %s [PID:%d] %s%s\n", $level, $time, $pid, $message, $contextStr);

        // 输出到控制台
        echo $logLine;

        // 如果配置了日志文件，同时写入文件
        if (self::$logFile !== null) {
            self::writeToFile($logLine);
        }
    }

    /**
     * 写入日志文件
     *
     * @param string $logLine 日志行
     *
     * @return void
     */
    private static function writeToFile(string $logLine): void
    {
        try {
            // 确保日志目录存在
            $logDir = dirname(self::$logFile);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }

            // 追加写入日志文件
            file_put_contents(self::$logFile, $logLine, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // 日志写入失败时，输出到 stderr
            fwrite(STDERR, "日志写入失败: {$e->getMessage()}\n");
        }
    }
}
