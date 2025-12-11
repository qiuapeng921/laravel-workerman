<?php

declare(strict_types=1);

namespace Qiuapeng\LaravelWorkerman\Adapters;

use Throwable;
use Illuminate\Http\Request;
use Laravel\Lumen\Application;
use Symfony\Component\HttpFoundation\Response;
use Qiuapeng\LaravelWorkerman\Contracts\FrameworkAdapter;

/**
 * Lumen 框架适配器
 *
 * 封装 Lumen 应用的请求处理逻辑
 * 注意：Lumen 没有 HTTP Kernel，直接通过 Application 处理请求
 */
final class LumenAdapter implements FrameworkAdapter
{
    /** @var Application Lumen 应用实例 */
    private Application $app;

    /**
     * 构造函数
     *
     * @param Application $app Lumen 应用实例
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * {@inheritdoc}
     */
    public function getApp()
    {
        return $this->app;
    }

    /**
     * {@inheritdoc}
     */
    public function bootstrap(Request $mockRequest): void
    {
        $this->instance('request', $mockRequest);

        // Lumen 没有 Kernel，直接通过 handle 引导
        try {
            $response = $this->app->handle($mockRequest);
            $this->terminate($mockRequest, $response);
        } catch (Throwable $e) {
            // 引导阶段的异常被忽略
        }
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Request $request): Response
    {
        return $this->app->handle($request);
    }

    /**
     * {@inheritdoc}
     */
    public function terminate(Request $request, Response $response): void
    {
        // Lumen 的 terminate 方法是可选的
        if (method_exists($this->app, 'terminate')) {
            $this->app->terminate();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function bound(string $abstract): bool
    {
        return $this->app->bound($abstract);
    }

    /**
     * {@inheritdoc}
     */
    public function make(string $abstract)
    {
        return $this->app->make($abstract);
    }

    /**
     * {@inheritdoc}
     */
    public function instance(string $abstract, $instance): void
    {
        $this->app->instance($abstract, $instance);
    }

    /**
     * {@inheritdoc}
     */
    public function resolved(string $abstract): bool
    {
        // Lumen 使用 bound 替代 resolved
        return $this->app->bound($abstract);
    }

    /**
     * {@inheritdoc}
     */
    public function forgetInstance(string $abstract): void
    {
        // Lumen 的容器实例清理方式
        if (method_exists($this->app, 'forgetInstance')) {
            $this->app->forgetInstance($abstract);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getFrameworkType(): string
    {
        return 'lumen';
    }
}
