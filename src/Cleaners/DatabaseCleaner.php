<?php

declare(strict_types=1);

namespace Qiuapeng\LaravelWorkerman\Cleaners;

use Throwable;
use Qiuapeng\LaravelWorkerman\Contracts\CleanerInterface;
use Qiuapeng\LaravelWorkerman\Logger;

/**
 * 数据库清理器
 */
class DatabaseCleaner implements CleanerInterface
{
    public function clean($app): void
    {
        if (!$app->bound('db')) {
            return;
        }

        try {
            $db = $app->make('db');
            foreach ($db->getConnections() as $connection) {
                $connection->flushQueryLog();

                if (method_exists($connection, 'transactionLevel') && $connection->transactionLevel() > 0) {
                    Logger::warning('检测到未提交的事务，正在回滚...');
                    try {
                        while ($connection->transactionLevel() > 0) {
                            $connection->rollBack();
                        }
                    } catch (Throwable $e) {
                        Logger::error('事务回滚失败: ' . $e->getMessage());
                    }
                }
            }
        } catch (Throwable $e) {
            // 忽略
        }
    }
}
