# Symfony Profiler Markdown Bundle

[English](README.md) | [中文](README.zh-CN.md)

A Symfony bundle that exports Symfony Profiler data as Markdown format, making it easy to share and archive profiling reports.

## 🚀 Features

- **Markdown Export**: Export Symfony Profiler data to well-formatted Markdown
- **Multiple Collectors**: Supports all standard Symfony profiler collectors (Request, Time, Memory, Security, Doctrine, etc.)
- **Extensible Formatters**: Customizable formatter system for different data types
- **Direct Access**: Access profiler reports via `/_profiler/{token}.md` endpoint
- **Rich Formatting**: Includes tables, code blocks, and emoji indicators for better readability

## 📦 Installation

Install the bundle using Composer:

```bash
composer require tourze/symfony-profiler-markdown-bundle
```

## ⚙️ Configuration

### 1. Enable the Bundle

Add the bundle to your `config/bundles.php`:

```php
// config/bundles.php
return [
    // ...
    Tourze\ProfilerMarkdownBundle\ProfilerMarkdownBundle::class => ['all' => true],
];
```

### 2. Configure Dependencies

This bundle requires the following bundles:
- `DoctrineBundle` (for database profiling)
- `SecurityBundle` (for security profiling)
- `RoutingAutoLoaderBundle` (for automatic route loading)

Make sure these are enabled in your application.

## 🔧 Usage

### Basic Usage

Once installed, you can access any profiler report in Markdown format by visiting:

```
http://your-app.com/_profiler/{token}.md
```

Replace `{token}` with the actual profiler token from your Symfony toolbar.

### Example URL

```
http://localhost:8000/_profiler/a1b2c3d.md
```

This will generate a comprehensive Markdown report including:

- **Summary**: Request overview with token, URL, method, status, time, IP
- **Table of Contents**: Navigation links to all collector sections
- **Detailed Sections**: Formatted data from each profiler collector:
  - 📨 **Request**: HTTP request details, headers, parameters
  - ⏱️ **Performance**: Execution time, timeline data
  - 💾 **Memory**: Memory usage statistics
  - 🔒 **Security**: Authentication and authorization details
  - 🗄️ **Database**: Doctrine queries and performance metrics
  - 🎨 **Templates**: Twig rendering information
  - 📋 **Logs**: Application log entries
  - And more...

## 🎨 Formatters

The bundle includes specialized formatters for different data types:

### Built-in Formatters

- **RequestFormatter**: HTTP request/response data
- **TimeFormatter**: Performance timing data
- **MemoryFormatter**: Memory usage statistics
- **SecurityFormatter**: Security events and decisions
- **DoctrineFormatter**: Database queries and metrics
- **TwigFormatter**: Template rendering data
- **LoggerFormatter**: Log entries with levels and formatting

### Custom Formatters

You can create custom formatters by implementing `MarkdownFormatterInterface`:

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
        // Return array of Markdown lines
        return [
            "## Custom Data",
            "",
            "| Property | Value |",
            "|----------|-------|",
            "| Custom Field | Custom Value |",
            "",
        ];
    }

    public function getPriority(): int
    {
        return 0; // Higher priority = earlier execution
    }
}
```

## 🔍 Example Output

```markdown
# Symfony Profiler Report

---

## 📊 Summary

| Property | Value |
|----------|-------|
| **Token** | `a1b2c3d` |
| **URL** | https://example.com/api/users |
| **Method** | GET |
| **Status** | 200 |
| **Time** | 2024-01-15 14:30:25 |
| **IP** | 127.0.0.1 |

---

## 📑 Table of Contents

- [📨 Request](#request)
- [⏱️ Performance](#time)
- [💾 Memory](#memory)
- [🔒 Security](#security)
- [🗄️ Database](#doctrine)

---

## 📨 Request

| Property | Value |
|----------|-------|
| Method | GET |
| URI | /api/users |
| Parameters | `{}` |
| Headers | `{"host":"example.com",...}` |
```

## 🛠️ Development

### Running Tests

```bash
composer test
```

### Code Quality

```bash
composer phpstan
```

## 📄 Requirements

- PHP 8.1+
- Symfony 7.3+
- Doctrine Bundle
- Security Bundle
- Twig

## 📝 License

This bundle is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 📞 Support

For issues and questions:
- Create an issue on GitHub
- Check the [Symfony Documentation](https://symfony.com/doc)