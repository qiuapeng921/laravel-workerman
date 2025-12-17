# Changelog

所有版本的重要变更都会记录在此文件中。

## [2.2.0] - 2025-12-17

### 修复
- 🐛 修复 Workerman 多文件上传格式无法正确转换的 BUG
- 🐛 修复 `files[]` 多文件上传在 Workerman 环境下失效的问题

### 重构
- ♻️ 新增 `UploadedFileConverter` 类，将文件上传转换逻辑从 `AppManager` 中分离
- ♻️ 遵循单一职责原则，`AppManager` 代码精简约 150 行
- ♻️ 支持 5 种文件格式：PHP 单文件、PHP 多文件、Workerman 多文件、对象、对象数组


## [2.1.1] - 2025-12-10

### 新增
- ✨ Lumen 框架支持
- ✨ 使用适配器模式重构代码
- ✨ `AppManager` 统一管理 Laravel/Lumen 应用

### 文档
- 📝 更新文档，添加 Lumen 使用说明

## [2.1.0] - 2025-12-01

### 首次发布
- 🚀 Laravel 支持
- 🚀 Workerman HTTP 服务器集成
- 🚀 静态文件服务
- 🚀 自动 Worker 重启机制
- 🚀 性能统计
