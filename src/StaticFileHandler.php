<?php

declare(strict_types=1);

namespace Qiuapeng\LaravelWorkerman;

use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;

/**
 * 静态文件处理器
 *
 * 提供静态文件服务，支持常见的文件类型
 * 包含目录遍历攻击防护
 */
class StaticFileHandler
{
    /** @var array<string, string> MIME 类型映射 */
    private const MIME_TYPES = [
        'html'  => 'text/html',
        'htm'   => 'text/html',
        'css'   => 'text/css',
        'js'    => 'application/javascript',
        'mjs'   => 'application/javascript',
        'json'  => 'application/json',
        'xml'   => 'application/xml',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'svg'   => 'image/svg+xml',
        'ico'   => 'image/x-icon',
        'webp'  => 'image/webp',
        'avif'  => 'image/avif',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
        'otf'   => 'font/otf',
        'eot'   => 'application/vnd.ms-fontobject',
        'pdf'   => 'application/pdf',
        'zip'   => 'application/zip',
        'txt'   => 'text/plain',
        'mp3'   => 'audio/mpeg',
        'mp4'   => 'video/mp4',
        'webm'  => 'video/webm',
        'ogg'   => 'audio/ogg',
        'wav'   => 'audio/wav',
        'map'   => 'application/json',
    ];

    /** @var string|null 缓存的规范化静态目录路径 */
    private static $normalizedStaticDir = null;

    /** @var string|null 上次使用的静态目录原始路径 */
    private static $lastStaticDir = null;

    /**
     * 处理静态文件请求
     *
     * @param Request $request   Workerman 请求对象
     * @param string  $staticDir 静态文件根目录
     *
     * @return Response|null 返回响应对象，如果不是静态文件则返回 null
     */
    public static function handle(Request $request, string $staticDir): ?Response
    {
        $uri = $request->uri();
        $path = parse_url($uri, PHP_URL_PATH);

        // 跳过根路径和 PHP 入口文件
        if ($path === null || $path === '/' || $path === '/index.php') {
            return null;
        }

        // 检查文件扩展名是否在允许列表中
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!isset(self::MIME_TYPES[$ext])) {
            return null;
        }

        // 安全验证：防止目录遍历攻击
        $filePath = self::validateAndResolvePath($staticDir, $path);
        if ($filePath === null) {
            return null;
        }

        // 检查文件是否存在且可读
        if (!is_file($filePath) || !is_readable($filePath)) {
            return null;
        }

        $response = new Response(200);
        $response->withHeader('Content-Type', self::MIME_TYPES[$ext]);
        $response->withHeader('Cache-Control', 'public, max-age=86400');
        $response->withHeader('X-Content-Type-Options', 'nosniff');
        $response->withFile($filePath);

        return $response;
    }

    /**
     * 验证并解析文件路径
     *
     * 防止目录遍历攻击，确保请求的文件在允许的静态目录内
     *
     * @param string $staticDir 静态文件根目录
     * @param string $path      请求的相对路径
     *
     * @return string|null 规范化后的文件路径，如果路径不安全则返回 null
     */
    private static function validateAndResolvePath(string $staticDir, string $path): ?string
    {
        // 缓存规范化的静态目录路径（避免重复调用 realpath）
        if (self::$lastStaticDir !== $staticDir) {
            self::$normalizedStaticDir = realpath($staticDir);
            self::$lastStaticDir = $staticDir;
        }

        // 静态目录不存在
        if (self::$normalizedStaticDir === false) {
            return null;
        }

        // 先进行初步的路径安全检查（快速失败）
        if (self::containsDangerousPatterns($path)) {
            return null;
        }

        // 构建完整文件路径
        $fullPath = self::$normalizedStaticDir . DIRECTORY_SEPARATOR . ltrim($path, '/\\');

        // 使用 realpath 获取规范化路径
        $realPath = realpath($fullPath);

        // 文件不存在
        if ($realPath === false) {
            return null;
        }

        // 关键安全检查：确保解析后的路径在静态目录内
        // 使用 strpos 检查路径前缀，防止目录遍历
        if (strpos($realPath, self::$normalizedStaticDir) !== 0) {
            Logger::warning("目录遍历攻击尝试被阻止: {$path}");
            return null;
        }

        return $realPath;
    }

    /**
     * 检查路径是否包含危险模式
     *
     * 快速检查常见的目录遍历模式，避免不必要的文件系统操作
     *
     * @param string $path 请求路径
     *
     * @return bool 如果包含危险模式返回 true
     */
    private static function containsDangerousPatterns(string $path): bool
    {
        // 检查常见的目录遍历模式
        $dangerousPatterns = [
            '..',           // 父目录引用
            "\0",           // 空字节注入
            '%00',          // URL 编码的空字节
            '%2e%2e',       // URL 编码的 ..
            '..%2f',        // 混合编码
            '%2f..',        // 混合编码
            '..%5c',        // Windows 路径分隔符编码
            '%5c..',        // Windows 路径分隔符编码
        ];

        $lowerPath = strtolower($path);
        foreach ($dangerousPatterns as $pattern) {
            if (strpos($lowerPath, strtolower($pattern)) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * 清理静态目录路径缓存
     *
     * 在静态目录配置变更时调用
     *
     * @return void
     */
    public static function clearCache(): void
    {
        self::$normalizedStaticDir = null;
        self::$lastStaticDir = null;
    }
}
