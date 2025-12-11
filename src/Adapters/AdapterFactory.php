<?php

declare(strict_types=1);

namespace Qiuapeng\LaravelWorkerman\Adapters;

use RuntimeException;
use Qiuapeng\LaravelWorkerman\Contracts\FrameworkAdapter;

/**
 * 适配器工厂
 *
 * 根据应用类型自动创建对应的框架适配器
 */
final class AdapterFactory
{
    /** @var string Laravel Application 类名 */
    private const LARAVEL_APP_CLASS = 'Illuminate\Foundation\Application';

    /** @var string Lumen Application 类名 */
    private const LUMEN_APP_CLASS = 'Laravel\Lumen\Application';

    /**
     * 根据 bootstrap/app.php 返回的应用实例创建适配器
     *
     * @param mixed $app 应用实例
     *
     * @return FrameworkAdapter
     *
     * @throws RuntimeException 无法识别的应用类型
     */
    public static function create($app): FrameworkAdapter
    {
        // 检测 Laravel
        if ($app instanceof \Illuminate\Foundation\Application) {
            return new LaravelAdapter($app);
        }

        // 检测 Lumen
        if (class_exists(self::LUMEN_APP_CLASS) && $app instanceof \Laravel\Lumen\Application) {
            return new LumenAdapter($app);
        }

        // 无法识别的应用类型
        $actualType = is_object($app) ? get_class($app) : gettype($app);
        throw new RuntimeException(
            "无法识别的应用类型: {$actualType}。仅支持 Laravel 和 Lumen 框架。"
        );
    }

    /**
     * 检测框架类型
     *
     * @param mixed $app 应用实例
     *
     * @return string 'laravel', 'lumen', 或 'unknown'
     */
    public static function detect($app): string
    {
        if ($app instanceof \Illuminate\Foundation\Application) {
            return 'laravel';
        }

        if (class_exists(self::LUMEN_APP_CLASS) && $app instanceof \Laravel\Lumen\Application) {
            return 'lumen';
        }

        return 'unknown';
    }

    /**
     * 检查是否为 Lumen 应用
     *
     * @param mixed $app 应用实例
     *
     * @return bool
     */
    public static function isLumen($app): bool
    {
        return self::detect($app) === 'lumen';
    }

    /**
     * 检查是否为 Laravel 应用
     *
     * @param mixed $app 应用实例
     *
     * @return bool
     */
    public static function isLaravel($app): bool
    {
        return self::detect($app) === 'laravel';
    }
}
