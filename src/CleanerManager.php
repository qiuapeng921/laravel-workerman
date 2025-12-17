<?php

declare(strict_types=1);

namespace Qiuapeng\LaravelWorkerman;

use Throwable;
use Qiuapeng\LaravelWorkerman\Contracts\CleanerInterface;
use Qiuapeng\LaravelWorkerman\Cleaners\GlobalVariableCleaner;
use Qiuapeng\LaravelWorkerman\Cleaners\RequestInstanceCleaner;
use Qiuapeng\LaravelWorkerman\Cleaners\FacadeCleaner;
use Qiuapeng\LaravelWorkerman\Cleaners\SessionCleaner;
use Qiuapeng\LaravelWorkerman\Cleaners\AuthCleaner;
use Qiuapeng\LaravelWorkerman\Cleaners\CookieCleaner;
use Qiuapeng\LaravelWorkerman\Cleaners\ValidatorCleaner;
use Qiuapeng\LaravelWorkerman\Cleaners\UrlGeneratorCleaner;
use Qiuapeng\LaravelWorkerman\Cleaners\DatabaseCleaner;

/**
 * 清理器管理器
 *
 * 负责加载和执行清理器（内置 + 用户自定义）
 */
class CleanerManager
{
    /** @var array<CleanerInterface> 清理器实例列表 */
    private $cleaners = [];

    /**
     * 内置清理器列表
     *
     * @var array<string>
     */
    private const BUILTIN_CLEANERS = [
        GlobalVariableCleaner::class,
        RequestInstanceCleaner::class,
        FacadeCleaner::class,
        SessionCleaner::class,
        AuthCleaner::class,
        CookieCleaner::class,
        ValidatorCleaner::class,
        UrlGeneratorCleaner::class,
        DatabaseCleaner::class,
    ];

    public function __construct()
    {
        // 初始化内置清理器
        foreach (self::BUILTIN_CLEANERS as $class) {
            $this->cleaners[] = new $class();
        }
    }

    /**
     * 加载用户自定义清理器
     *
     * @param array<string> $cleaners 清理器类名列表
     *
     * @return void
     */
    public function loadCleaners(array $cleaners): void
    {
        foreach ($cleaners as $class) {
            try {
                if (!class_exists($class)) {
                    Logger::warning("清理器类不存在: {$class}");
                    continue;
                }

                $cleaner = new $class();

                if (!($cleaner instanceof CleanerInterface)) {
                    Logger::warning("清理器必须实现 CleanerInterface 接口: {$class}");
                    continue;
                }

                $this->cleaners[] = $cleaner;
            } catch (Throwable $e) {
                Logger::error("加载清理器失败 [{$class}]: {$e->getMessage()}");
            }
        }

        $customCount = count($cleaners);
        if ($customCount > 0) {
            Logger::info("已加载 {$customCount} 个自定义清理器");
        }
    }

    /**
     * 获取已加载的清理器数量（包括内置）
     *
     * @return int
     */
    public function getCleanerCount(): int
    {
        return count($this->cleaners);
    }

    /**
     * 执行请求清理
     *
     * @param mixed $app 应用实例
     *
     * @return void
     */
    public function cleanup($app): void
    {
        foreach ($this->cleaners as $cleaner) {
            try {
                $cleaner->clean($app);
            } catch (Throwable $e) {
                Logger::error('清理器执行失败: ' . $e->getMessage());
            }
        }
    }
}
