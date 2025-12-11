<?php

/**
 * Workerman 启动脚本 - Laravel 加速器
 *
 * 用法:
 *   php workerman.php [--port=8080] [--workers=4] [--max=10000] [--debug] start [-d]
 *
 * 命令:
 *   start     启动服务器 (添加 -d 参数后台运行)
 *   stop      停止服务器 (仅 Linux/Mac)
 *   restart   重启服务器 (仅 Linux/Mac)
 *   reload    平滑重启 (仅 Linux/Mac)
 *   status    查看状态 (仅 Linux/Mac)
 *
 * 选项:
 *   -p, --port     监听端口 (默认: 8080)
 *   -w, --workers  Worker 进程数 (默认: 4, Windows 固定为 1)
 *   -m, --max      单个 Worker 最大请求数 (默认: 10000)
 *   --debug        启用调试模式
 *
 * @package Qiuapeng\WorkermanLaravel
 */

declare(strict_types=1);

// 加载 Composer 自动加载
require __DIR__ . '/vendor/autoload.php';

// 使用 Bootstrap 类启动服务器
use Qiuapeng\WorkermanLaravel\Bootstrap;

$bootstrap = new Bootstrap(__DIR__, $argv ?? []);
$bootstrap->run();
