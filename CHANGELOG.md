# Changelog

所有版本的重要变更都会记录在此文件中。

## [2.1.2] - 2024-12-17

### 修复
- 🐛 修复 Workerman 多文件上传格式无法正确转换的 BUG
- 🐛 修复 `files[]` 多文件上传在 Workerman 环境下失效的问题

### 重构
- ♻️ 新增 `UploadedFileConverter` 类，将文件上传转换逻辑从 `AppManager` 中分离
- ♻️ 遵循单一职责原则，`AppManager` 代码精简约 150 行
- ♻️ 支持 5 种文件格式：PHP 单文件、PHP 多文件、Workerman 多文件、对象、对象数组

## [1.3.0] - 2024-12-16

### 新增
- ✨ 自定义清理器扩展系统
- ✨ 清理逻辑模块化，每个清理器一个类
- ✨ `CleanerInterface` 接口，支持用户创建自定义清理器
- ✨ `CleanerManager` 清理器管理器

### 重构
- 🔧 将清理逻辑从 AppManager 提取到独立的 Cleaners 目录
- 🔧 配置文件只加载一次，通过 WorkermanConfig 统一管理

### 内置清理器
- `GlobalVariableCleaner` - 清理 PHP 超全局变量
- `RequestInstanceCleaner` - 清理请求级别容器实例
- `FacadeCleaner` - 清理 Facade 缓存
- `SessionCleaner` - 保存并重置 Session
- `AuthCleaner` - 清理认证状态
- `CookieCleaner` - 清理 Cookie 队列
- `ValidatorCleaner` - 清理验证器实例
- `UrlGeneratorCleaner` - 清理 URL 生成器
- `DatabaseCleaner` - 清理数据库查询日志、回滚未提交事务

## [1.2.0] - 2024-12-15

### 安全修复
- 🔒 修复静态文件目录遍历漏洞
- 🔒 使用加密安全随机数生成请求 ID
- 🔒 动态获取所有 Auth Guard 进行清理，防止用户状态残留

### 新增
- ✨ 添加 `/health` 健康检查端点
- ✨ 添加 `/_status` 详细状态端点（仅调试模式）
- ✨ Logger 支持日志级别和文件写入

### 修复
- 🐛 添加全局变量清理，防止请求间状态污染
- 🐛 添加未提交事务检测和自动回滚
- 🐛 添加数据库连接断开检测和自动重连

### 优化
- ⚡ 扩展静态文件 MIME 类型支持
- ⚡ 改进内存管理和资源清理机制

## [1.1.0] - 2024-12-10

### 新增
- ✨ Lumen 框架支持
- ✨ 使用适配器模式重构代码
- ✨ `AppManager` 统一管理 Laravel/Lumen 应用

### 文档
- 📝 更新文档，添加 Lumen 使用说明

## [1.0.0] - 2024-12-01

### 首次发布
- 🚀 Laravel 支持
- 🚀 Workerman HTTP 服务器集成
- 🚀 静态文件服务
- 🚀 自动 Worker 重启机制
- 🚀 性能统计
