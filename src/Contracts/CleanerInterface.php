<?php

declare(strict_types=1);

namespace Qiuapeng\LaravelWorkerman\Contracts;

/**
 * 请求清理器接口
 *
 * 每个清理器实现一个具体的清理逻辑
 */
interface CleanerInterface
{
    /**
     * 执行清理操作
     *
     * @param mixed $app Laravel/Lumen 应用实例
     *
     * @return void
     */
    public function clean($app): void;
}
