<?php

declare(strict_types=1);

namespace Qiuapeng\LaravelWorkerman\Cleaners;

use Throwable;
use Qiuapeng\LaravelWorkerman\Contracts\CleanerInterface;

/**
 * Session 清理器
 */
class SessionCleaner implements CleanerInterface
{
    public function clean($app): void
    {
        if (!$app->bound('session')) {
            return;
        }

        try {
            $session = $app->make('session');
            if (method_exists($session, 'driver')) {
                $driver = $session->driver();
                if (method_exists($driver, 'save')) {
                    $driver->save();
                }
                if (method_exists($driver, 'setId')) {
                    $driver->setId('');
                }
            }
        } catch (Throwable $e) {
            // 忽略
        }
    }
}
