<?php

declare(strict_types=1);

namespace Tourze\ProfilerMarkdownBundle\Formatter;

use Symfony\Component\HttpKernel\DataCollector\LoggerDataCollector;
use Symfony\Component\VarDumper\Cloner\Data;

/**
 * LoggerFormatter的辅助类，用于降低主类的复杂度
 */
class LoggerFormatterHelper
{
    /**
     * @return array<int, string>
     */
    public function formatLogSummary(LoggerDataCollector $collector): array
    {
        $errorCount = $collector->countErrors();
        $warningCount = $collector->countWarnings();

        $markdown = [
            '### Log Summary',
            '',
            '| Level | Count | Icon |',
            '|-------|-------|------|',
        ];

        if ($errorCount > 0) {
            $markdown[] = '| **ERROR** | ' . $errorCount . ' | 🔴 |';
        }
        if ($warningCount > 0) {
            $markdown[] = '| **WARNING** | ' . $warningCount . ' | 🟡 |';
        }

        $markdown[] = '';

        return $markdown;
    }

    /**
     * @return array<int, mixed>
     */
    public function extractLogs(LoggerDataCollector $collector): array
    {
        $logs = $collector->getLogs();
        if ($logs instanceof Data) {
            $logs = $logs->getValue(true);
        }

        return is_array($logs) ? $logs : [];
    }

    /**
     * @param array<int, mixed> $logs
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function groupLogsByLevel(array $logs, AbstractMarkdownFormatter $formatter): array
    {
        $groupedLogs = [
            'error' => [],
            'warning' => [],
            'notice' => [],
            'info' => [],
            'debug' => [],
        ];

        foreach ($logs as $log) {
            if (!is_array($log)) {
                continue;
            }
            /** @var array<string, mixed> $logArray */
            $logArray = $log;
            $logEntry = $this->createLogEntry($logArray, $formatter);
            $level = $log['priority'] ?? 200;

            if ($level >= 500) {
                $groupedLogs['error'][] = $logEntry;
            } elseif ($level >= 300) {
                $groupedLogs['warning'][] = $logEntry;
            } elseif ($level >= 250) {
                $groupedLogs['notice'][] = $logEntry;
            } elseif ($level >= 200) {
                $groupedLogs['info'][] = $logEntry;
            } else {
                $groupedLogs['debug'][] = $logEntry;
            }
        }

        return $groupedLogs;
    }

    /**
     * @param array<string, mixed> $log
     * @return array<string, mixed>
     */
    private function createLogEntry(array $log, AbstractMarkdownFormatter $formatter): array
    {
        $message = $this->extractValue($log['message'] ?? '');
        $context = $this->extractValue($log['context'] ?? []);

        if (is_string($message) && is_array($context) && [] !== $context) {
            /** @var array<string, mixed> $contextArray */
            $contextArray = $context;
            $message = $this->interpolateMessageInternal($message, $contextArray);
        }

        return [
            'message' => $message,
            'context' => $context,
            'channel' => $log['channel'] ?? 'app',
            'timestamp' => $log['timestamp'] ?? null,
        ];
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function extractValue($value)
    {
        if ($value instanceof Data) {
            return $value->getValue(true);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function interpolateMessageInternal(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            $replace['{' . $key . '}'] = $this->formatContextValueInternal($val);
        }

        return strtr($message, $replace);
    }

    /**
     * @param mixed $value
     */
    private function formatContextValueInternal($value): string
    {
        if (is_array($value)) {
            return $this->formatArrayValueInternal($value);
        }

        if (is_object($value)) {
            return $this->formatObjectValueInternal($value);
        }

        return $this->formatScalarValueInternal($value);
    }

    /**
     * @param array<mixed> $value
     */
    private function formatArrayValueInternal(array $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return false !== $encoded ? $encoded : '';
    }

    /**
     * @param object $value
     */
    private function formatObjectValueInternal(object $value): string
    {
        if (method_exists($value, '__toString')) {
            return (string) $value;
        }

        return get_class($value);
    }

    /**
     * @param mixed $value
     */
    private function formatScalarValueInternal($value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @return array<int, string>
     */
    public function formatDeprecations(LoggerDataCollector $collector): array
    {
        $deprecations = $collector->countDeprecations();
        if ($deprecations <= 0) {
            return [];
        }

        return [
            '### ⚠️ Deprecations',
            '',
            '**Total Deprecations:** ' . $deprecations,
            '',
        ];
    }

    /**
     * @param array<int, mixed> $logs
     * @return array<int, string>
     */
    public function formatChannelsSummary(array $logs): array
    {
        $channels = $this->countLogsByChannel($logs);

        if (count($channels) <= 1) {
            return [];
        }

        arsort($channels);

        $markdown = [
            '### Log Channels',
            '',
            '| Channel | Log Count |',
            '|---------|-----------|',
        ];

        foreach ($channels as $channel => $count) {
            $markdown[] = "| {$channel} | {$count} |";
        }
        $markdown[] = '';

        return $markdown;
    }

    /**
     * @param array<int, mixed> $logs
     * @return array<string, int>
     */
    private function countLogsByChannel(array $logs): array
    {
        $channels = [];
        foreach ($logs as $log) {
            if (!is_array($log)) {
                continue;
            }
            $channel = is_string($log['channel'] ?? null) ? $log['channel'] : 'app';
            $channels[$channel] = ($channels[$channel] ?? 0) + 1;
        }

        return $channels;
    }
}
