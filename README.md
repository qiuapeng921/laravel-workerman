# Laravel Workerman

使用 Workerman 加速 Laravel/Lumen 应用，常驻内存模式提升 5-20 倍性能。

## 版本要求

| 依赖 | 版本                         |
|------|----------------------------|
| PHP | ^7.2 \| ^7.3 \| ^7.4 \| ^8.0 |
| Laravel | ^8.0 \| ^9.0 |
| Lumen | ^8.0 \| ^9.0 |
| Workerman | ^4.0                   |

## 特性

- 🚀 **高性能**: 常驻内存，避免重复加载框架
- 🔄 **自动重启**: 达到最大请求数自动重启 Worker，防止内存泄漏
- 📁 **静态文件**: 内置静态文件服务，无需 Nginx
- 🛠 **易于集成**: 一键安装，开箱即用
- 📊 **性能统计**: 自动统计请求数、响应时间、内存使用
- 🔀 **双框架支持**: 同时兼容 Laravel 和 Lumen 框架

## 安装

```bash
composer require qiuapeng921/laravel-workerman
```

## 配置

### Laravel

发布配置文件和启动脚本：

```bash
php artisan vendor:publish --tag=workerman
```

这将发布：
- `config/workerman.php` - 配置文件
- `workerman.php` - 启动脚本

### Lumen

由于 Lumen 不支持 `vendor:publish`，需要手动复制文件：

```bash
# 复制配置文件
cp vendor/qiuapeng921/laravel-workerman/config/workerman.php config/workerman.php

# 复制启动脚本
cp vendor/qiuapeng921/laravel-workerman/workerman.php workerman.php
```

然后在 `bootstrap/app.php` 中注册服务提供者：

```php
$app->register(Qiuapeng\LaravelWorkerman\WorkermanServiceProvider::class);
```

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
├── Contracts/
│   └── FrameworkAdapter.php   # 框架适配器接口
├── Adapters/
│   ├── AdapterFactory.php     # 适配器工厂 - 自动检测框架类型
│   ├── LaravelAdapter.php     # Laravel 适配器
│   └── LumenAdapter.php       # Lumen 适配器
├── WorkermanServer.php        # 服务器类 - Worker 生命周期管理
├── AppManager.php             # 应用管理器 - 统一处理 Laravel/Lumen
├── StaticFileHandler.php      # 静态文件处理器
├── Logger.php                 # 日志工具
└── WorkermanServiceProvider.php
```

## 架构说明

### 适配器模式

项目使用适配器模式来兼容 Laravel 和 Lumen 两个框架：

```
┌─────────────────┐
│   AppManager    │
└────────┬────────┘
         │
    ┌────▼────┐
    │ Factory │ ──── 自动检测框架类型
    └────┬────┘
         │
    ┌────┴────┐
    │         │
┌───▼───┐ ┌───▼───┐
│Laravel│ │ Lumen │
│Adapter│ │Adapter│
└───────┘ └───────┘
```

**核心差异处理：**

| 特性 | Laravel | Lumen |
|------|---------|-------|
| HTTP Kernel | ✅ 有 | ❌ 无 |
| Facade | 默认启用 | 需手动启用 |
| config_path() | ✅ 有 | ❌ 无 |
| vendor:publish | ✅ 有 | ❌ 无 |

## 注意事项

### 1. Session 和 Cache
- 建议使用 Redis 驱动，避免文件锁竞争

### 2. 代码更新
- 修改代码后需要执行 `php workerman.php reload` 或重启服务

### 3. 静态变量
- 避免在静态变量中存储请求相关数据，可能导致数据污染

### 4. 单例模式
- 注意 Laravel/Lumen 容器中的单例在多次请求间共享

### 5. Windows 限制
- Windows 下只能使用单进程模式
- 不支持 `stop`、`restart`、`reload`、`status` 命令

### 6. Lumen 特殊配置

在 Lumen 中使用时，确保在 `bootstrap/app.php` 中启用 Facade（如果需要）：

```php
$app->withFacades();
```

## 本地开发调试

在 Laravel/Lumen 项目中使用本地开发版本：

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "/path/to/laravel-workerman",
            "options": { "symlink": true }
        }
    ],
    "require": {
        "qiuapeng/laravel-workerman": "@dev"
    }
}
```

## 性能对比

| 模式 | QPS | 响应时间 |
|------|-----|----------|
| PHP-FPM | 500 | 20ms |
| Workerman (Laravel) | 5000+ | 2ms |
| Workerman (Lumen) | 8000+ | 1.5ms |

> 测试环境：4 核 CPU，8GB 内存，简单 API 请求

## 健康检查

内置健康检查端点，供负载均衡器和监控系统使用：

```bash
# 健康检查（始终可用）
curl http://localhost:8080/health

# 响应示例
{
  "status": "healthy",
  "timestamp": "2024-01-01T12:00:00+08:00",
  "uptime": 3600.5,
  "memory": {
    "current_mb": 32.5,
    "peak_mb": 48.2
  },
  "worker": {
    "pid": 12345,
    "requests": 5000
  }
}

# 详细状态（仅调试模式）
curl http://localhost:8080/_status
```

## Changelog

### v1.2.0
- 🔒 **安全修复**: 修复静态文件目录遍历漏洞
- 🔒 **安全修复**: 使用加密安全随机数生成请求 ID
- 🔒 **安全修复**: 动态获取所有 Auth Guard 进行清理，防止用户状态残留
- ✨ **新功能**: 添加 `/health` 健康检查端点
- ✨ **新功能**: 添加 `/_status` 详细状态端点（仅调试模式）
- ✨ **新功能**: Logger 支持日志级别和文件写入
- 🐛 **Bug 修复**: 添加全局变量清理，防止请求间状态污染
- 🐛 **Bug 修复**: 添加未提交事务检测和自动回滚
- 🐛 **Bug 修复**: 添加数据库连接断开检测和自动重连
- ⚡ **优化**: 扩展静态文件 MIME 类型支持
- ⚡ **优化**: 改进内存管理和资源清理机制
- 📝 **文档**: 更新文档，添加健康检查说明

### v1.1.0
- ✨ 新增 Lumen 框架支持
- ✨ 使用适配器模式重构代码
- 📦 新增 `AppManager` 统一管理 Laravel/Lumen 应用
- 📝 更新文档

### v1.0.x
- 🚀 初始版本
- ✅ Laravel 支持

## License

MIT
