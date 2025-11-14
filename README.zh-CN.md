# Symfony Profiler Markdown Bundle

[English](README.md) | [中文](README.zh-CN.md)

一个Symfony扩展包，用于将Symfony Profiler数据导出为Markdown格式，便于分享和存档性能分析报告。

## 🚀 功能特性

- **Markdown导出**：将Symfony Profiler数据导出为格式良好的Markdown文档
- **多收集器支持**：支持所有标准Symfony分析器收集器（请求、时间、内存、安全、Doctrine等）
- **可扩展格式化器**：为不同数据类型提供可定制的格式化系统
- **直接访问**：通过`/_profiler/{token}.md`端点直接访问分析器报告
- **丰富格式**：包含表格、代码块和表情符号指示器，提升可读性

## 📦 安装

使用Composer安装扩展包：

```bash
composer require tourze/symfony-profiler-markdown-bundle
```

## ⚙️ 配置

### 1. 启用扩展包

在`config/bundles.php`中添加扩展包：

```php
// config/bundles.php
return [
    // ...
    Tourze\ProfilerMarkdownBundle\ProfilerMarkdownBundle::class => ['all' => true],
];
```

### 2. 配置依赖

此扩展包需要以下依赖包：
- `DoctrineBundle`（用于数据库分析）
- `SecurityBundle`（用于安全分析）
- `RoutingAutoLoaderBundle`（用于自动路由加载）

确保这些依赖包已在您的应用中启用。

## 🔧 使用方法

### 基本用法

安装后，您可以通过访问以下URL获取Markdown格式的分析器报告：

```
http://your-app.com/_profiler/{token}.md
```

将`{token}`替换为Symfony工具栏中的实际分析器令牌。

### 示例URL

```
http://localhost:8000/_profiler/a1b2c3d.md
```

这将生成一个全面的Markdown报告，包括：

- **摘要**：包含令牌、URL、方法、状态、时间、IP的请求概览
- **目录**：指向所有收集器部分的导航链接
- **详细部分**：来自每个分析器收集器的格式化数据：
  - 📨 **请求**：HTTP请求详情、头部、参数
  - ⏱️ **性能**：执行时间、时间线数据
  - 💾 **内存**：内存使用统计
  - 🔒 **安全**：身份验证和授权详情
  - 🗄️ **数据库**：Doctrine查询和性能指标
  - 🎨 **模板**：Twig渲染信息
  - 📋 **日志**：应用程序日志条目
  - 以及更多...

## 🎨 格式化器

扩展包包含针对不同数据类型的专用格式化器：

### 内置格式化器

- **RequestFormatter**：HTTP请求/响应数据
- **TimeFormatter**：性能计时数据
- **MemoryFormatter**：内存使用统计
- **SecurityFormatter**：安全事件和决策
- **DoctrineFormatter**：数据库查询和指标
- **TwigFormatter**：模板渲染数据
- **LoggerFormatter**：带级别和格式的日志条目

### 自定义格式化器

通过实现`MarkdownFormatterInterface`创建自定义格式化器：

```php
<?php

use Tourze\ProfilerMarkdownBundle\Formatter\MarkdownFormatterInterface;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;

class CustomFormatter implements MarkdownFormatterInterface
{
    public function supports(DataCollectorInterface $collector): bool
    {
        return $collector instanceof YourCustomCollector;
    }

    public function format(DataCollectorInterface $collector): array
    {
        // 返回Markdown行数组
        return [
            "## 自定义数据",
            "",
            "| 属性 | 值 |",
            "|----------|-------|",
            "| 自定义字段 | 自定义值 |",
            "",
        ];
    }

    public function getPriority(): int
    {
        return 0; // 更高优先级 = 更早执行
    }
}
```

## 🔍 示例输出

```markdown
# Symfony Profiler 报告

---

## 📊 摘要

| 属性 | 值 |
|----------|-------|
| **令牌** | `a1b2c3d` |
| **URL** | https://example.com/api/users |
| **方法** | GET |
| **状态** | 200 |
| **时间** | 2024-01-15 14:30:25 |
| **IP** | 127.0.0.1 |

---

## 📑 目录

- [📨 请求](#request)
- [⏱️ 性能](#time)
- [💾 内存](#memory)
- [🔒 安全](#security)
- [🗄️ 数据库](#doctrine)

---

## 📨 请求

| 属性 | 值 |
|----------|-------|
| 方法 | GET |
| URI | /api/users |
| 参数 | `{}` |
| 头部 | `{"host":"example.com",...}` |
```

## 🛠️ 开发

### 运行测试

```bash
composer test
```

### 代码质量检查

```bash
composer phpstan
```

## 📄 系统要求

- PHP 8.1+
- Symfony 7.3+
- Doctrine Bundle
- Security Bundle
- Twig

## 📝 许可证

此扩展包基于MIT许可证发布。详见[LICENSE](LICENSE)文件。

## 🤝 贡献

欢迎贡献！请随时提交Pull Request。

## 📞 支持

如有问题和疑问：
- 在GitHub上创建issue
- 查看[Symfony文档](https://symfony.com/doc)
