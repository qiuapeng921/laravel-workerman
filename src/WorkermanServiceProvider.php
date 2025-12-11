<?php

namespace Qiuapeng\LaravelWorkerman;

use Illuminate\Support\ServiceProvider;

/**
 * Workerman Laravel 服务提供者
 */
class WorkermanServiceProvider extends ServiceProvider
{
    /**
     * 注册服务
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/workerman.php', 'workerman');
    }

    /**
     * 引导服务
     */
    public function boot(): void
    {
        // 发布文件
        $this->publishes([
            __DIR__ . '/../config/workerman.php' => config_path('workerman.php'),
            __DIR__ . '/../workerman.php'        => base_path('workerman.php'),
        ], 'workerman');
    }
}
