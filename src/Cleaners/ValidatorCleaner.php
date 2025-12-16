<?php

declare(strict_types=1);

namespace Qiuapeng\LaravelWorkerman\Cleaners;

use Throwable;
use Qiuapeng\LaravelWorkerman\Contracts\CleanerInterface;

/**
 * 验证器清理器
 */
class ValidatorCleaner implements CleanerInterface
{
    public function clean($app): void
    {
        if (!$app->bound('validator')) {
            return;
        }

        try {
            if ($app->resolved('validator')) {
                $app->forgetInstance('validator');
            }
        } catch (Throwable $e) {
            // 忽略
        }
    }
}
