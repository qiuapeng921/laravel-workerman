<?php

declare(strict_types=1);

namespace Qiuapeng\WorkermanLaravel;

/**
 * 日志工具类
 *
 * 提供统一的日志输出接口，支持不同级别的日志
 */
final class Logger
{
    /** @var bool 是否开启调试模式 */
    private static bool $debug = false;

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
     * 输出信息日志
     *
     * @param string $message 日志消息
     *
     * @return void
     */
    public static function info(string $message): void
    {
        self::log('INFO', $message);
    }

    /**
     * 输出调试日志（仅在调试模式下）
     *
     * @param string $message 日志消息
     *
     * @return void
     */
    public static function debug(string $message): void
    {
        if (self::$debug) {
            self::log('DEBUG', $message);
        }
    }

    /**
     * 输出警告日志
     *
     * @param string $message 日志消息
     *
     * @return void
     */
    public static function warning(string $message): void
    {
        self::log('WARN', $message);
    }

    /**
     * 输出错误日志
     *
     * @param string $message 日志消息
     *
     * @return void
     */
    public static function error(string $message): void
    {
        self::log('ERROR', $message);
    }

    /**
     * 输出日志
     *
     * @param string $level   日志级别
     * @param string $message 日志消息
     *
     * @return void
     */
    private static function log(string $level, string $message): void
    {
        $time = date('Y-m-d H:i:s');
        echo "[{$level}] {$time} {$message}\n";
    }
}
