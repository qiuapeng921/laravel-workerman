<?php

declare(strict_types=1);

namespace Qiuapeng\LaravelWorkerman\Cleaners;

use Throwable;
use Qiuapeng\LaravelWorkerman\Contracts\CleanerInterface;

/**
 * Cookie 队列清理器
 */
class CookieCleaner implements CleanerInterface
{
    public function clean($app): void
    {
        if (!$app->bound('cookie')) {
            return;
        }

        try {
            $cookie = $app->make('cookie');
            if (method_exists($cookie, 'flushQueuedCookies')) {
                $cookie->flushQueuedCookies();
            }
            if (method_exists($cookie, 'getQueuedCookies')) {
                $queuedCookies = $cookie->getQueuedCookies();
                if (method_exists($cookie, 'unqueue')) {
                    foreach ($queuedCookies as $name => $value) {
                        $cookie->unqueue($name);
                    }
                }
            }
        } catch (Throwable $e) {
            // 忽略
        }
    }
}
