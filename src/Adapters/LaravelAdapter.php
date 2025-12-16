<?php

declare(strict_types=1);

namespace Qiuapeng\LaravelWorkerman\Adapters;

use Illuminate\Http\Request;
use Illuminate\Foundation\Application;
use Illuminate\Contracts\Http\Kernel;
use Symfony\Component\HttpFoundation\Response;
use Qiuapeng\LaravelWorkerman\Contracts\FrameworkAdapter;

/**
 * Laravel 框架适配器
 *
 * 封装 Laravel 应用的请求处理逻辑
 */
final class LaravelAdapter implements FrameworkAdapter
{
    /** @var Application Laravel 应用实例 */
    private $app;

    /** @var Kernel HTTP 内核 */
    private $kernel;

    /**
     * 构造函数
     *
     * @param Application $app Laravel 应用实例
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->kernel = $app->make(Kernel::class);
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

        // 通过 Kernel 引导应用
        $response = $this->kernel->handle($mockRequest);
        $this->kernel->terminate($mockRequest, $response);
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Request $request): Response
    {
        return $this->kernel->handle($request);
    }

    /**
     * {@inheritdoc}
     */
    public function terminate(Request $request, Response $response): void
    {
        $this->kernel->terminate($request, $response);
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
        return $this->app->resolved($abstract);
    }

    /**
     * {@inheritdoc}
     */
    public function forgetInstance(string $abstract): void
    {
        $this->app->forgetInstance($abstract);
    }

    /**
     * {@inheritdoc}
     */
    public function getFrameworkType(): string
    {
        return 'laravel';
    }
}
