<?php

namespace Qiuapeng\LaravelWorkerman;

use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;

/**
 * 静态文件处理器
 */
class StaticFileHandler
{
    /** @var array MIME 类型映射 */
    private const MIME_TYPES = [
        'html'  => 'text/html',
        'htm'   => 'text/html',
        'css'   => 'text/css',
        'js'    => 'application/javascript',
        'json'  => 'application/json',
        'xml'   => 'application/xml',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'svg'   => 'image/svg+xml',
        'ico'   => 'image/x-icon',
        'webp'  => 'image/webp',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
        'eot'   => 'application/vnd.ms-fontobject',
        'pdf'   => 'application/pdf',
        'zip'   => 'application/zip',
        'txt'   => 'text/plain',
    ];

    /**
     * 处理静态文件请求
     */
    public static function handle(Request $request, string $staticDir): ?Response
    {
        $uri = $request->uri();
        $path = parse_url($uri, PHP_URL_PATH);

        if ($path === '/' || $path === '/index.php') {
            return null;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!isset(self::MIME_TYPES[$ext])) {
            return null;
        }

        $filePath = $staticDir . $path;

        if (!is_file($filePath) || !is_readable($filePath)) {
            return null;
        }

        $response = new Response(200);
        $response->withHeader('Content-Type', self::MIME_TYPES[$ext]);
        $response->withHeader('Cache-Control', 'public, max-age=86400');
        $response->withFile($filePath);

        return $response;
    }
}
