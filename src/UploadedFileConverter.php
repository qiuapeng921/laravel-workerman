<?php

declare(strict_types=1);

namespace Qiuapeng\LaravelWorkerman;

use Illuminate\Http\UploadedFile;

/**
 * 上传文件转换器
 *
 * 将 Workerman 的文件格式转换为 Laravel 可识别的 UploadedFile 对象
 * 支持多种文件格式：PHP 标准格式、Workerman 格式、对象格式
 */
class UploadedFileConverter
{
    /**
     * 转换上传文件数组
     *
     * 支持多种文件格式：
     * 1. 标准 PHP 数组格式 ['tmp_name' => ..., 'name' => ..., ...]
     * 2. 标准 PHP 多文件格式 ['tmp_name' => [...], 'name' => [...], ...]
     * 3. Workerman 多文件格式 [['tmp_name' => ..., 'name' => ...], [...]]
     * 4. Workerman 对象格式
     *
     * @param array $files 原始文件数据
     *
     * @return array<string, UploadedFile|UploadedFile[]> 转换后的 UploadedFile 数组
     */
    public static function convert(array $files): array
    {
        $converted = [];

        foreach ($files as $key => $file) {
            $result = self::convertSingle($file);
            if ($result !== null) {
                $converted[$key] = $result;
            }
        }

        return $converted;
    }

    /**
     * 转换单个文件字段
     *
     * @param mixed $file 文件数据
     *
     * @return UploadedFile|UploadedFile[]|null
     */
    private static function convertSingle($file)
    {
        // 情况 1: 标准 PHP 数组格式（单文件）
        if (is_array($file) && isset($file['tmp_name']) && is_string($file['tmp_name'])) {
            return self::createFromArray($file);
        }

        // 情况 2: 标准 PHP 多文件格式 (tmp_name 是数组)
        if (is_array($file) && isset($file['tmp_name']) && is_array($file['tmp_name'])) {
            return self::convertPhpMultiFile($file);
        }

        // 情况 3: Workerman 多文件格式（索引数组，每个元素是完整文件信息）
        if (isset($file[0]['tmp_name']) && is_array($file) && is_array($file[0])) {
            return self::convertWorkermanMultiFile($file);
        }

        // 情况 4: 单个对象
        if (is_object($file)) {
            return self::createFromObject($file);
        }

        // 情况 5: 对象数组
        if (is_array($file) && isset($file[0]) && is_object($file[0])) {
            return self::convertObjectArray($file);
        }

        return null;
    }

    /**
     * 转换标准 PHP 多文件格式
     *
     * @param array $file 标准 PHP 多文件数组
     *
     * @return UploadedFile[]
     */
    private static function convertPhpMultiFile(array $file): array
    {
        $result = [];

        foreach ($file['tmp_name'] as $index => $tmpName) {
            $single = [
                'tmp_name' => $tmpName,
                'name' => $file['name'][$index] ?? '',
                'type' => $file['type'][$index] ?? null,
                'error' => $file['error'][$index] ?? UPLOAD_ERR_OK,
            ];
            $uploadedFile = self::createFromArray($single);
            if ($uploadedFile !== null) {
                $result[] = $uploadedFile;
            }
        }

        return $result;
    }

    /**
     * 转换 Workerman 多文件格式
     *
     * @param array $files Workerman 格式的文件数组
     *
     * @return UploadedFile[]
     */
    private static function convertWorkermanMultiFile(array $files): array
    {
        $result = [];

        foreach ($files as $file) {
            $uploadedFile = self::createFromArray($file);
            if ($uploadedFile !== null) {
                $result[] = $uploadedFile;
            }
        }

        return $result;
    }

    /**
     * 转换对象数组
     *
     * @param array $files 对象数组
     *
     * @return UploadedFile[]
     */
    private static function convertObjectArray(array $files): array
    {
        $result = [];

        foreach ($files as $file) {
            $uploadedFile = self::createFromObject($file);
            if ($uploadedFile !== null) {
                $result[] = $uploadedFile;
            }
        }

        return $result;
    }

    /**
     * 从数组创建 UploadedFile
     *
     * @param mixed $file 文件数组
     *
     * @return UploadedFile|null
     */
    private static function createFromArray($file): ?UploadedFile
    {
        if (!is_array($file) || !isset($file['tmp_name'])) {
            return null;
        }

        $tmpName = $file['tmp_name'];
        if (empty($tmpName) || !is_string($tmpName) || !is_file($tmpName)) {
            return null;
        }

        return new UploadedFile(
            $tmpName,
            $file['name'] ?? '',
            $file['type'] ?? null,
            $file['error'] ?? UPLOAD_ERR_OK,
            true
        );
    }

    /**
     * 从对象创建 UploadedFile
     *
     * @param object $file 文件对象
     *
     * @return UploadedFile|null
     */
    private static function createFromObject(object $file): ?UploadedFile
    {
        // 获取临时文件路径
        $tmpName = self::getObjectProperty($file, 'getPathname', 'tmp_name');
        if (!$tmpName || !is_file($tmpName)) {
            return null;
        }

        // 获取原始文件名
        $name = self::getObjectProperty($file, 'getClientOriginalName', 'name') ?? '';

        // 获取 MIME 类型
        $type = self::getObjectProperty($file, 'getClientMimeType', 'type');

        // 获取错误码
        $error = self::getObjectProperty($file, 'getError', 'error') ?? UPLOAD_ERR_OK;

        return new UploadedFile($tmpName, $name, $type, $error, true);
    }

    /**
     * 从对象获取属性值（优先使用方法，其次使用属性）
     *
     * @param object $object   对象
     * @param string $method   方法名
     * @param string $property 属性名
     *
     * @return mixed
     */
    private static function getObjectProperty(object $object, string $method, string $property)
    {
        if (method_exists($object, $method)) {
            return $object->$method();
        }

        if (property_exists($object, $property)) {
            return $object->$property;
        }

        return null;
    }
}
