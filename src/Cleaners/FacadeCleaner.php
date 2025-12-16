<?php

declare(strict_types=1);

namespace Qiuapeng\LaravelWorkerman\Cleaners;

use Illuminate\Support\Facades\Facade;
use Qiuapeng\LaravelWorkerman\Contracts\CleanerInterface;

/**
 * Facade 缓存清理器
 */
class FacadeCleaner implements CleanerInterface
{
    public function clean($app): void
    {
        if (class_exists(Facade::class)) {
            Facade::clearResolvedInstances();
        }
    }
}
