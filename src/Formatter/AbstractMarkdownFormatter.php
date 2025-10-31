<?php

namespace Tourze\ProfilerMarkdownBundle\Formatter;

use Symfony\Component\VarDumper\Cloner\Data;

abstract class AbstractMarkdownFormatter implements MarkdownFormatterInterface
{
    public function getPriority(): int
    {
        return 0;
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes > 0 ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $pow = (int) $pow;
        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    protected function formatJson(mixed $data): string
    {
        $encoded = json_encode($this->getValue($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return false !== $encoded ? $encoded : '';
    }

    protected function getValue(mixed $value): mixed
    {
        if ($value instanceof Data) {
            return $value->getValue(true);
        }

        return $value;
    }

    protected function truncate(string $text, int $length = 80): string
    {
        return strlen($text) > $length ? substr($text, 0, $length) . '...' : $text;
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function interpolateMessage(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            $replace['{' . $key . '}'] = $this->formatContextValue($val);
        }

        return strtr($message, $replace);
    }

    /**
     * @param mixed $value
     */
    private function formatContextValue($value): string
    {
        if (is_array($value)) {
            return $this->formatArrayValue($value);
        }

        if (is_object($value)) {
            return $this->formatObjectValue($value);
        }

        return $this->formatScalarValue($value);
    }

    /**
     * @param array<mixed> $value
     */
    private function formatArrayValue(array $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return false !== $encoded ? $encoded : '';
    }

    /**
     * @param object $value
     */
    private function formatObjectValue(object $value): string
    {
        if (method_exists($value, '__toString')) {
            return (string) $value;
        }

        return get_class($value);
    }

    /**
     * @param mixed $value
     */
    private function formatScalarValue($value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
