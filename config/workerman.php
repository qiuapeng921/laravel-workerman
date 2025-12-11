<?php

/**
 * Workerman 配置文件
 *
 * 注意：此配置在 Laravel 初始化前加载，不能使用 public_path()、storage_path() 等辅助函数
 */

// 项目根目录
$basePath = dirname(__DIR__);

return [
    // 监听地址
    'host'         => env('WORKERMAN_HOST', '0.0.0.0'),

    // 监听端口
    'port'         => env('WORKERMAN_PORT', 8080),

    // Worker 进程数（Windows 只能为 1）
    'workers'      => env('WORKERMAN_WORKERS', 4),

    // 最大请求数（达到后 Worker 自动重启，防止内存泄漏）
    'max_requests' => env('WORKERMAN_MAX_REQUESTS', 10000),

    // 调试模式
    'debug'        => env('WORKERMAN_DEBUG', false),

    // 进程名称
    'name'         => env('WORKERMAN_NAME', 'laravel-workerman'),

    // 静态文件
    'static'       => [
        'enable' => true,
        'path'   => $basePath . '/public',
    ],

    // 日志
    'log'          => [
        'file' => $basePath . '/storage/workerman/workerman.log',
    ],
];
