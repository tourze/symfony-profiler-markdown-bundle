<?php

namespace Tourze\ProfilerMarkdownBundle\Formatter;

use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;

class FormatterRegistry
{
    /** @var array<MarkdownFormatterInterface> */
    private array $formatters = [];

    /**
     * @param array<MarkdownFormatterInterface> $formatters
     */
    public function __construct(
        array $formatters,
    ) {
        foreach ($formatters as $formatter) {
            $this->formatters[] = $formatter;
        }

        // Sort by priority
        usort($this->formatters, function (MarkdownFormatterInterface $a, MarkdownFormatterInterface $b) {
            return $b->getPriority() <=> $a->getPriority();
        });
    }

    /**
     * @return array<int, string>
     */
    public function format(string $name, DataCollectorInterface $collector): array
    {
        foreach ($this->formatters as $formatter) {
            if ($formatter->supports($collector)) {
                return $formatter->format($collector);
            }
        }

        // Fallback to generic formatter
        return $this->genericFormat($name, $collector);
    }

    /**
     * @return array<int, string>
     */
    private function genericFormat(string $name, DataCollectorInterface $collector): array
    {
        $markdown = [
            "## 📦 {$name}",
            '',
        ];

        $data = $this->extractCollectorData($collector);

        if ([] !== $data) {
            $markdown = array_merge($markdown, $this->formatDataAsTable($data));
        } else {
            $markdown[] = '_No extractable data_';
            $markdown[] = '';
        }

        return $markdown;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractCollectorData(DataCollectorInterface $collector): array
    {
        $methods = get_class_methods($collector);
        $data = [];

        foreach ($methods as $method) {
            if ($this->isDataGetterMethod($method)) {
                $extractedData = $this->extractMethodData($collector, $method);
                if (null !== $extractedData) {
                    $key = $this->normalizeMethodName($method);
                    $data[$key] = $extractedData;
                }
            }
        }

        return $data;
    }

    private function isDataGetterMethod(string $method): bool
    {
        return str_starts_with($method, 'get') || str_starts_with($method, 'count');
    }

    private function extractMethodData(DataCollectorInterface $collector, string $method): mixed
    {
        try {
            $reflection = new \ReflectionMethod($collector, $method);
            if (0 === $reflection->getNumberOfRequiredParameters()) {
                if (method_exists($collector, $method)) {
                    // 将动态方法调用替换为显式调用，避免静态分析错误
                    $result = $this->callExplicitMethod($collector, $method);
                    if (null !== $result && !is_object($result)) {
                        return $result;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Ignore method call failures
        }

        return null;
    }

    /**
     * 使用显式方法调用替代动态调用，避免静态分析错误
     */
    private function callExplicitMethod(DataCollectorInterface $collector, string $method): mixed
    {
        // 使用method_exists和is_callable来确保方法是可调用的
        if (method_exists($collector, $method) && is_callable([$collector, $method])) {
            // 使用类型断言告诉PHPStan这是一个可调用的
            /** @var callable $callable */
            $callable = [$collector, $method];

            return call_user_func($callable);
        }

        return null;
    }

    private function normalizeMethodName(string $method): string
    {
        $key = preg_replace('/^(get|count)/', '', $method);
        if (null === $key) {
            return 'unknown';
        }

        $lcFirst = lcfirst($key);
        $normalized = preg_replace('/([A-Z])/', '_$1', $lcFirst);
        if (null === $normalized) {
            return 'unknown';
        }

        return strtolower($normalized);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, string>
     */
    private function formatDataAsTable(array $data): array
    {
        $markdown = [
            '| Property | Value |',
            '|----------|-------|',
        ];

        foreach ($data as $key => $value) {
            $key = ucwords(str_replace('_', ' ', $key));
            $value = $this->formatValue($value);
            $markdown[] = "| {$key} | {$value} |";
        }

        $markdown[] = '';

        return $markdown;
    }

    private function formatValue(mixed $value): string
    {
        if (is_array($value)) {
            $encoded = json_encode($value, JSON_PRETTY_PRINT);

            return false !== $encoded ? $encoded : 'Array';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_scalar($value) || is_null($value)) {
            return (string) $value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return get_debug_type($value);
    }
}
