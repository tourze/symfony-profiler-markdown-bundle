<?php

namespace Tourze\ProfilerMarkdownBundle\Formatter;

use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\DataCollector\LoggerDataCollector;
use Symfony\Component\VarDumper\Cloner\Data;

class LoggerFormatter extends AbstractMarkdownFormatter
{
    public function supports(DataCollectorInterface $collector): bool
    {
        return $collector instanceof LoggerDataCollector;
    }

    /**
     * @return array<int, string>
     */
    public function format(DataCollectorInterface $collector): array
    {
        if (!$collector instanceof LoggerDataCollector) {
            return [];
        }

        $formatter = new LoggerFormatterHelper();

        $markdown = [
            '## 📋 Logs',
            '',
        ];

        $markdown = array_merge($markdown, $formatter->formatLogSummary($collector));

        $logs = $formatter->extractLogs($collector);
        if ([] !== $logs) {
            $groupedLogs = $formatter->groupLogsByLevel($logs, $this);
            $markdown = array_merge($markdown, $this->formatGroupedLogs($groupedLogs));
            $markdown = array_merge($markdown, $formatter->formatDeprecations($collector));
            $markdown = array_merge($markdown, $formatter->formatChannelsSummary($logs));
        }

        return $markdown;
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $groupedLogs
     * @return array<int, string>
     */
    private function formatGroupedLogs(array $groupedLogs): array
    {
        $markdown = [];

        // Format error logs with detailed view
        if ([] !== $groupedLogs['error']) {
            $markdown = array_merge($markdown, $this->formatErrorLogs($groupedLogs['error']));
        }

        // Format other log levels with simple view
        $simpleLogs = [
            'warning' => ['title' => '🟡 Warnings', 'logs' => $groupedLogs['warning']],
            'info' => ['title' => 'ℹ️ Info Logs', 'logs' => $groupedLogs['info']],
            'debug' => ['title' => '🐛 Debug Logs', 'logs' => $groupedLogs['debug'], 'collapsible' => true],
        ];

        foreach ($simpleLogs as $logData) {
            if ([] !== $logData['logs']) {
                $markdown = array_merge($markdown, $this->formatSimpleLogs($logData));
            }
        }

        return $markdown;
    }

    /**
     * @param array<int, array<string, mixed>> $errors
     * @return array<int, string>
     */
    private function formatErrorLogs(array $errors): array
    {
        $markdown = [
            '### 🔴 Errors (' . count($errors) . ')',
            '',
        ];

        foreach ($errors as $i => $log) {
            $markdown = array_merge($markdown, $this->formatDetailedLogEntry($log, $i + 1, 'Error'));
        }

        return $markdown;
    }

    /**
     * @param array{title: string, logs: array<int, array<string, mixed>>, collapsible?: bool} $logData
     * @return array<int, string>
     */
    private function formatSimpleLogs(array $logData): array
    {
        $title = $logData['title'];
        $logs = $logData['logs'];
        $collapsible = $logData['collapsible'] ?? false;

        $markdown = [
            "### {$title} (" . count($logs) . ')',
            '',
        ];

        if ($collapsible) {
            $markdown[] = '<details>';
            $markdown[] = '<summary>Show all ' . count($logs) . ' debug logs</summary>';
            $markdown[] = '';
        }

        foreach ($logs as $i => $log) {
            $markdown[] = $this->formatSimpleLogEntry($log, $i + 1);
            if ([] !== $log['context'] && null !== $log['context']) {
                $markdown[] = '   ```json';
                $markdown[] = '   ' . $this->formatJson($log['context']);
                $markdown[] = '   ```';
            }
        }

        if ($collapsible) {
            $markdown[] = '</details>';
        }

        $markdown[] = '';

        return $markdown;
    }

    /**
     * @param array<string, mixed> $log
     * @return string
     */
    private function formatSimpleLogEntry(array $log, int $index): string
    {
        return sprintf(
            '%d. **[%s]** %s',
            $index,
            is_string($log['channel'] ?? null) ? $log['channel'] : 'app',
            is_string($log['message'] ?? null) ? $log['message'] : ''
        );
    }

    /**
     * @param array<string, mixed> $log
     * @return array<int, string>
     */
    private function formatDetailedLogEntry(array $log, int $index, string $type): array
    {
        $markdown = [
            "#### {$type} #{$index}",
            '',
            '**Channel:** `' . $this->getLogChannel($log) . '`',
        ];

        $timestamp = $this->getLogTimestamp($log);
        if (null !== $timestamp) {
            $markdown[] = '**Time:** ' . date('H:i:s', $timestamp);
        }

        $markdown[] = '';
        $markdown[] = '**Message:**';
        $markdown[] = '```';
        $markdown[] = $this->getLogMessage($log);
        $markdown[] = '```';

        if ([] !== $log['context'] && null !== $log['context']) {
            $markdown[] = '';
            $markdown[] = '**Context:**';
            $markdown[] = '```json';
            $markdown[] = $this->formatJson($log['context']);
            $markdown[] = '```';
        }

        $markdown[] = '';
        $markdown[] = '---';
        $markdown[] = '';

        return $markdown;
    }

    /**
     * @param array<string, mixed> $log
     * @return string
     */
    private function getLogChannel(array $log): string
    {
        return is_string($log['channel'] ?? null) ? $log['channel'] : 'app';
    }

    /**
     * @param array<string, mixed> $log
     * @return string
     */
    private function getLogMessage(array $log): string
    {
        return is_string($log['message'] ?? null) ? $log['message'] : '';
    }

    /**
     * @param array<string, mixed> $log
     * @return int|null
     */
    private function getLogTimestamp(array $log): ?int
    {
        $timestamp = $log['timestamp'] ?? null;

        return (null !== $timestamp && '' !== $timestamp && is_int($timestamp)) ? $timestamp : null;
    }

    public function getPriority(): int
    {
        return 50;
    }
}
