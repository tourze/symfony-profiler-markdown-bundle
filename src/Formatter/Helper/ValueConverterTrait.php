<?php

declare(strict_types=1);

namespace Tourze\ProfilerMarkdownBundle\Formatter\Helper;

use Symfony\Component\VarDumper\Cloner\Data;

trait ValueConverterTrait
{
    /**
     * 安全地将混合值转换为字符串
     */
    protected function convertToString(mixed $value): string
    {
        return match (true) {
            is_string($value) => $value,
            is_null($value) => '',
            is_bool($value) => $value ? '1' : '0',
            is_scalar($value) => (string) $value,
            is_object($value) => $this->convertObjectToString($value),
            is_array($value) => $this->convertArrayToString($value),
            default => 'Unknown',
        };
    }

    /**
     * 将对象转换为字符串
     */
    private function convertObjectToString(object $value): string
    {
        return method_exists($value, '__toString') ? (string) $value : get_class($value);
    }

    /**
     * 将数组转换为字符串
     * @param array<mixed> $value
     */
    private function convertArrayToString(array $value): string
    {
        $json = json_encode($value);

        return false !== $json ? $json : 'Array';
    }

    /**
     * 获取类的短名称（去除命名空间）
     */
    protected function getShortClassName(string $className): string
    {
        $lastBackslashPos = strrpos($className, '\\');

        return false !== $lastBackslashPos ? substr($className, $lastBackslashPos + 1) : $className;
    }

    /**
     * 截断字符串到指定长度
     */
    protected function truncate(string $text, int $length = 80): string
    {
        return mb_strlen($text) > $length ? mb_substr($text, 0, $length - 3) . '...' : $text;
    }
}
