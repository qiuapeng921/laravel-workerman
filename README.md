# Workerman Laravel

使用 Workerman 加速 Laravel 应用，常驻内存模式提升 5-20 倍性能。

## 版本要求

| 依赖 | 版本                             |
|------|--------------------------------|
| PHP | ^7.4 \| ^8.0                   |
| Laravel | ^8.0 \| ^9.0 \| ^10.0 \| ^11.0 |
| Workerman | ^4.0                           |

## 特性

- 🚀 **高性能**: 常驻内存，避免重复加载框架
- 🔄 **自动重启**: 达到最大请求数自动重启 Worker，防止内存泄漏
- 📁 **静态文件**: 内置静态文件服务，无需 Nginx
- 🛠 **易于集成**: 一键安装，开箱即用
- 📊 **性能统计**: 自动统计请求数、响应时间、内存使用

## 安装

```bash
composer require qiuapeng921/workerman-laravel
```

## 配置

发布配置文件和启动脚本：

```bash
php artisan vendor:publish --tag=workerman
```

这将发布：
- `config/workerman.php` - 配置文件
- `workerman.php` - 启动脚本

## 使用

### 启动服务

```bash
# 前台启动
php workerman.php start

# 后台启动（守护进程，仅 Linux/Mac）
php workerman.php start -d

# 自定义参数
php workerman.php --port=9000 --workers=8 --max=5000 --debug start
```

### 命令行参数

| 参数 | 短选项 | 说明 | 默认值 |
|------|--------|------|--------|
| `--port` | `-p` | 监听端口 | 8080 |
| `--workers` | `-w` | Worker 进程数 | 4 |
| `--max` | `-m` | 单个 Worker 最大请求数 | 10000 |
| `--debug` | - | 启用调试模式 | false |

### 停止服务

```bash
# Linux/Mac
php workerman.php stop

# Windows
Ctrl+C
```

### 其他命令（仅 Linux/Mac）

```bash
php workerman.php restart   # 重启
php workerman.php reload    # 平滑重载
php workerman.php status    # 查看状态
```

## 配置选项

编辑 `config/workerman.php`：

```php
return [
    // 监听地址
    'host'         => env('WORKERMAN_HOST', '0.0.0.0'),

    // 监听端口
    'port'         => env('WORKERMAN_PORT', 8080),

    // Worker 进程数（Windows 只能为 1）
    'workers'      => env('WORKERMAN_WORKERS', 4),

    // 最大请求数（达到后 Worker 自动重启，防止内存泄漏）
    'max_requests' => env('WORKERMAN_MAX_REQUESTS', 10000),

    // 调试模式
    'debug'        => env('WORKERMAN_DEBUG', false),

    // 进程名称
    'name'         => env('WORKERMAN_NAME', 'laravel-workerman'),

    // 静态文件配置
    'static'       => [
        'enable' => true,
        'path'   => base_path('public'),
    ],

    // 日志配置
    'log'          => [
        'file' => storage_path('logs/workerman.log'),
    ],
];
```

## 环境变量

```ini
WORKERMAN_HOST=0.0.0.0
WORKERMAN_PORT=8080
WORKERMAN_WORKERS=4
WORKERMAN_MAX_REQUESTS=10000
WORKERMAN_DEBUG=false
WORKERMAN_NAME=laravel-workerman
```

## 项目结构

```
src/
├── Bootstrap.php              # 引导类 - 环境检查、参数解析
├── Config/
│   └── WorkermanConfig.php    # 配置管理器 - 多级配置覆盖
├── WorkermanServer.php        # 服务器类 - Worker 生命周期管理
├── LaravelAppManager.php      # Laravel 应用管理器
├── StaticFileHandler.php      # 静态文件处理器
└── WorkermanServiceProvider.php
```

## 注意事项

### 1. Session 和 Cache
- 建议使用 Redis 驱动，避免文件锁竞争

### 2. 代码更新
- 修改代码后需要执行 `php workerman.php reload` 或重启服务

### 3. 静态变量
- 避免在静态变量中存储请求相关数据，可能导致数据污染

### 4. 单例模式
- 注意 Laravel 容器中的单例在多次请求间共享

### 5. Windows 限制
- Windows 下只能使用单进程模式
- 不支持 `stop`、`restart`、`reload`、`status` 命令

## 本地开发调试

在 Laravel 项目中使用本地开发版本：

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "/path/to/workerman-laravel",
            "options": { "symlink": true }
        }
    ],
    "require": {
        "qiuapeng921/workerman-laravel": "@dev"
    }
}
```

## 性能对比

| 模式 | QPS | 响应时间 |
|------|-----|----------|
| PHP-FPM | 500 | 20ms |
| Workerman | 5000+ | 2ms |

> 测试环境：4 核 CPU，8GB 内存，简单 API 请求

## License

MIT
