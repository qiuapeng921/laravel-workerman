<?php

declare(strict_types=1);

namespace Qiuapeng\LaravelWorkerman\Cleaners;

use Throwable;
use Qiuapeng\LaravelWorkerman\Contracts\CleanerInterface;

/**
 * Auth 认证状态清理器
 */
class AuthCleaner implements CleanerInterface
{
    public function clean($app): void
    {
        if (!$app->bound('auth')) {
            return;
        }

        try {
            $auth = $app->make('auth');
            $guards = $this->getConfiguredGuards($app);

            foreach ($guards as $guard) {
                try {
                    $guardInstance = $auth->guard($guard);
                    if (method_exists($guardInstance, 'forgetUser')) {
                        $guardInstance->forgetUser();
                    }
                    if (method_exists($guardInstance, 'setUser')) {
                        $guardInstance->setUser(null);
                    }
                } catch (Throwable $e) {
                    // 忽略
                }
            }
        } catch (Throwable $e) {
            // 忽略
        }
    }

    /**
     * @param mixed $app
     * @return array<string>
     */
    private function getConfiguredGuards($app): array
    {
        try {
            if ($app->bound('config')) {
                $config = $app->make('config');
                $guards = $config->get('auth.guards', []);
                if (is_array($guards)) {
                    return array_keys($guards);
                }
            }
        } catch (Throwable $e) {
            // 忽略
        }

        return ['web', 'api', 'sanctum', 'admin'];
    }
}
