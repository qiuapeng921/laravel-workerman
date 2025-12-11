<?php

declare(strict_types=1);

namespace Qiuapeng\LaravelWorkerman\Contracts;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 框架适配器接口
 *
 * 抽象 Laravel 和 Lumen 之间的差异，提供统一的请求处理接口
 */
interface FrameworkAdapter
{
    /**
     * 获取应用实例
     *
     * @return mixed 应用实例 (Laravel\Application 或 Lumen\Application)
     */
    public function getApp();

    /**
     * 初始化应用
     *
     * @param Request $mockRequest 用于引导应用的模拟请求
     *
     * @return void
     */
    public function bootstrap(Request $mockRequest): void;

    /**
     * 处理 HTTP 请求
     *
     * @param Request $request HTTP 请求对象
     *
     * @return Response HTTP 响应对象
     */
    public function handle(Request $request): Response;

    /**
     * 终止请求处理
     *
     * @param Request  $request  HTTP 请求对象
     * @param Response $response HTTP 响应对象
     *
     * @return void
     */
    public function terminate(Request $request, Response $response): void;

    /**
     * 检查服务是否已绑定
     *
     * @param string $abstract 抽象名称
     *
     * @return bool
     */
    public function bound(string $abstract): bool;

    /**
     * 从容器中解析服务
     *
     * @param string $abstract 抽象名称
     *
     * @return mixed
     */
    public function make(string $abstract);

    /**
     * 在容器中注册实例
     *
     * @param string $abstract 抽象名称
     * @param mixed  $instance 实例对象
     *
     * @return void
     */
    public function instance(string $abstract, $instance): void;

    /**
     * 检查服务是否已解析
     *
     * @param string $abstract 抽象名称
     *
     * @return bool
     */
    public function resolved(string $abstract): bool;

    /**
     * 忘记容器中的实例
     *
     * @param string $abstract 抽象名称
     *
     * @return void
     */
    public function forgetInstance(string $abstract): void;

    /**
     * 获取框架类型名称
     *
     * @return string 'laravel' 或 'lumen'
     */
    public function getFrameworkType(): string;
}
