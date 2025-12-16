<?php

declare(strict_types=1);

namespace Qiuapeng\LaravelWorkerman;

use Illuminate\Support\ServiceProvider;
use Qiuapeng\LaravelWorkerman\Adapters\AdapterFactory;

/**
 * Workerman 服务提供者
 *
 * 同时支持 Laravel 和 Lumen 框架
 */
class WorkermanServiceProvider extends ServiceProvider
{
    /**
     * 注册服务
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom($this->getConfigPath(), 'workerman');
    }

    /**
     * 引导服务
     *
     * @return void
     */
    public function boot(): void
    {
        // 仅在 Laravel 中发布配置（Lumen 不支持 publishes）
        if ($this->isLaravel()) {
            $this->publishes([
                $this->getConfigPath() => $this->configPath('workerman.php'),
                $this->getEntryPath()  => $this->basePath('workerman.php'),
            ], 'workerman');
        }
    }

    /**
     * 获取配置文件路径
     *
     * @return string
     */
    private function getConfigPath(): string
    {
        return __DIR__ . '/../config/workerman.php';
    }

    /**
     * 获取入口文件路径
     *
     * @return string
     */
    private function getEntryPath(): string
    {
        return __DIR__ . '/../workerman.php';
    }

    /**
     * 检查是否为 Laravel 框架
     *
     * @return bool
     */
    private function isLaravel(): bool
    {
        return $this->app instanceof \Illuminate\Foundation\Application;
    }

    /**
     * 检查是否为 Lumen 框架
     *
     * @return bool
     */
    private function isLumen(): bool
    {
        return class_exists('Laravel\Lumen\Application')
            && $this->app instanceof \Laravel\Lumen\Application;
    }

    /**
     * 获取配置目录路径
     *
     * 兼容 Laravel 和 Lumen
     *
     * @param string $path 相对路径
     *
     * @return string
     */
    private function configPath(string $path = ''): string
    {
        // Laravel 使用 config_path()
        if (function_exists('config_path')) {
            return config_path($path);
        }

        // Lumen 使用 base_path('config/')
        return $this->basePath('config/' . $path);
    }

    /**
     * 获取项目根目录路径
     *
     * @param string $path 相对路径
     *
     * @return string
     */
    private function basePath(string $path = ''): string
    {
        return $this->app->basePath($path);
    }
}
