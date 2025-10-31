<?php

namespace Tourze\ProfilerMarkdownBundle\Formatter;

use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;

class DoctrineFormatter extends AbstractMarkdownFormatter
{
    public function supports(DataCollectorInterface $collector): bool
    {
        return $collector instanceof DoctrineDataCollector
            || $collector instanceof \Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector;
    }

    /**
     * @return array<int, string>
     */
    public function format(DataCollectorInterface $collector): array
    {
        if (!$collector instanceof DoctrineDataCollector && !$collector instanceof \Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector) {
            return [];
        }

        $markdown = [];
        $markdown[] = '## 💾 Database';
        $markdown[] = '';

        $markdown = array_merge($markdown, $this->formatStatistics($collector));
        $markdown = array_merge($markdown, $this->formatConnections($collector));
        $markdown = array_merge($markdown, $this->formatQueries($collector));

        return array_merge($markdown, $this->formatCacheStatistics($collector));
    }

    /**
     * @param DoctrineDataCollector|\Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector $collector
     * @return array<string>
     */
    private function formatStatistics($collector): array
    {
        $markdown = [
            '### Statistics',
            '',
            '| Metric | Value |',
            '|--------|-------|',
            '| **Total Queries** | ' . $collector->getQueryCount() . ' |',
            '| **Total Time** | ' . number_format($collector->getTime() * 1000, 2) . ' ms |',
        ];

        if (method_exists($collector, 'getInvalidEntityCount')) {
            /** @var int $invalidCount */
            $invalidCount = $collector->getInvalidEntityCount();
            if ($invalidCount > 0) {
                $markdown[] = '| **Invalid Entities** | ⚠️ ' . $invalidCount . ' |';
            }
        }

        $markdown[] = '';

        return $markdown;
    }

    /**
     * @param DoctrineDataCollector|\Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector $collector
     * @return array<string>
     */
    private function formatConnections($collector): array
    {
        $connections = $collector->getConnections();
        if ([] === $connections) {
            return [];
        }

        $markdown = [
            '### Connections',
            '',
        ];

        foreach ($connections as $name => $config) {
            $driver = $this->getValue($config['driver'] ?? 'unknown');
            $markdown[] = "- **{$name}**: " . (is_string($driver) ? $driver : 'unknown');
        }
        $markdown[] = '';

        return $markdown;
    }

    /**
     * @param DoctrineDataCollector|\Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector $collector
     * @return array<string>
     */
    private function formatQueries($collector): array
    {
        $queries = $collector->getQueries();
        if ([] === $queries) {
            return [];
        }

        $allQueries = $this->extractAllQueries($queries);
        $allQueries = $this->sortQueriesByExecutionTime($allQueries);

        $markdown = [];
        $markdown = array_merge($markdown, $this->formatSlowQueries($allQueries));
        $markdown = array_merge($markdown, $this->formatDuplicateQueries($allQueries));

        return array_merge($markdown, $this->formatQueryDetails($allQueries));
    }

    /**
     * @param array<string, array<array<string, mixed>>> $queries
     * @return array<array<string, mixed>>
     */
    private function extractAllQueries(array $queries): array
    {
        $allQueries = [];
        foreach ($queries as $connection => $connectionQueries) {
            foreach ($connectionQueries as $query) {
                $query['connection'] = $connection;
                $allQueries[] = $query;
            }
        }

        return $allQueries;
    }

    /**
     * @param array<array<string, mixed>> $queries
     * @return array<array<string, mixed>>
     */
    private function sortQueriesByExecutionTime(array $queries): array
    {
        usort($queries, function (array $a, array $b): int {
            $timeA = $a['executionMS'] ?? 0;
            $timeB = $b['executionMS'] ?? 0;

            return $timeB <=> $timeA;
        });

        return $queries;
    }

    /**
     * @param array<array<string, mixed>> $allQueries
     * @return array<string>
     */
    private function formatSlowQueries(array $allQueries): array
    {
        $slowQueries = array_filter($allQueries, function ($q) {
            return ($q['executionMS'] ?? 0) > 10;
        });

        if ([] === $slowQueries) {
            return [];
        }

        $markdown = [
            '### ⚠️ Slow Queries (>10ms)',
            '',
        ];

        foreach (array_slice($slowQueries, 0, 5) as $query) {
            $executionMS = $query['executionMS'];
            $time = number_format(is_numeric($executionMS) ? (float) $executionMS : 0.0, 2);
            $sql = $this->truncate(is_string($query['sql']) ? $query['sql'] : '', 100);
            $connection = is_string($query['connection']) ? $query['connection'] : 'unknown';
            $markdown[] = "- **{$time}ms** [`{$connection}`]: `{$sql}`";
        }
        $markdown[] = '';

        return $markdown;
    }

    /**
     * @param array<array<string, mixed>> $allQueries
     * @return array<string>
     */
    private function formatDuplicateQueries(array $allQueries): array
    {
        $duplicates = $this->findDuplicateQueries($allQueries);
        if ([] === $duplicates) {
            return [];
        }

        $markdown = [
            '### 🔄 Duplicate Queries',
            '',
        ];

        foreach (array_slice($duplicates, 0, 5) as $sql => $info) {
            $markdown[] = "- **{$info['count']}x**: `" . $this->truncate($sql, 80) . '`';
        }
        $markdown[] = '';

        return $markdown;
    }

    /**
     * @param array<array<string, mixed>> $allQueries
     * @return array<string>
     */
    private function formatQueryDetails(array $allQueries): array
    {
        $markdown = [
            '### Query Details',
            '',
        ];

        foreach (array_slice($allQueries, 0, 10) as $i => $query) {
            $markdown = array_merge($markdown, $this->formatSingleQuery($query, $i + 1));
        }

        if (count($allQueries) > 10) {
            $markdown[] = '_Showing 10 of ' . count($allQueries) . ' queries_';
            $markdown[] = '';
        }

        return $markdown;
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string>
     */
    private function formatSingleQuery(array $query, int $index): array
    {
        $markdown = [
            '#### Query #' . $index,
            '',
            '```sql',
            is_string($query['sql']) ? $query['sql'] : '',
            '```',
        ];

        if ([] !== $query['params'] && null !== $query['params']) {
            $params = $this->getValue($query['params']);
            $markdown[] = '';
            $markdown[] = '<details>';
            $markdown[] = '<summary>Parameters</summary>';
            $markdown[] = '';
            $markdown[] = '```json';
            $markdown[] = $this->formatJson($params);
            $markdown[] = '```';
            $markdown[] = '</details>';
        }

        return array_merge($markdown, $this->formatQueryMetadata($query));
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string>
     */
    private function formatQueryMetadata(array $query): array
    {
        $markdown = [
            '',
            '| Metric | Value |',
            '|--------|-------|',
            $this->formatMetadataRow('Connection', $this->getQueryConnection($query)),
        ];

        $markdown = array_merge($markdown, $this->formatOptionalMetadata($query));

        $markdown[] = '';

        return $markdown;
    }

    /**
     * @param array<string, mixed> $query
     * @return string
     */
    private function getQueryConnection(array $query): string
    {
        return is_string($query['connection']) ? $query['connection'] : 'unknown';
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string>
     */
    private function formatOptionalMetadata(array $query): array
    {
        $rows = [];

        if (isset($query['executionMS'])) {
            $rows[] = $this->formatMetadataRow('Execution Time', $this->formatExecutionTime($query['executionMS']) . ' ms');
        }

        if (isset($query['memory'])) {
            $rows[] = $this->formatMetadataRow('Memory', $this->formatMemory($query['memory']));
        }

        if (isset($query['row_count'])) {
            $rows[] = $this->formatMetadataRow('Rows', $this->formatRowCount($query['row_count']));
        }

        if (isset($query['explainable']) && (bool) $query['explainable']) {
            $rows[] = $this->formatMetadataRow('Explainable', '✅');
        }

        return $rows;
    }

    /**
     * @param mixed $executionTime
     * @return string
     */
    private function formatExecutionTime($executionTime): string
    {
        return number_format(is_numeric($executionTime) ? (float) $executionTime : 0.0, 2);
    }

    /**
     * @param mixed $memory
     * @return string
     */
    private function formatMemory($memory): string
    {
        return $this->formatBytes(is_int($memory) ? $memory : 0);
    }

    /**
     * @param mixed $rowCount
     * @return string
     */
    private function formatRowCount($rowCount): string
    {
        return is_numeric($rowCount) ? (string) $rowCount : '0';
    }

    /**
     * @param string $metric
     * @param string $value
     * @return string
     */
    private function formatMetadataRow(string $metric, string $value): string
    {
        return "| {$metric} | {$value} |";
    }

    /**
     * @param DoctrineDataCollector|\Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector $collector
     * @return array<string>
     */
    private function formatCacheStatistics($collector): array
    {
        if (!method_exists($collector, 'getCacheHitsCount') || !method_exists($collector, 'getCacheMissesCount') || !method_exists($collector, 'getCachePutsCount')) {
            return [];
        }

        /** @var int $cacheHits */
        $cacheHits = $collector->getCacheHitsCount();
        /** @var int $cacheMisses */
        $cacheMisses = $collector->getCacheMissesCount();
        /** @var int $cachePuts */
        $cachePuts = $collector->getCachePutsCount();

        if ($cacheHits <= 0 && $cacheMisses <= 0) {
            return [];
        }

        $total = (int) $cacheHits + (int) $cacheMisses;
        $hitRate = $total > 0 ? round(((float) $cacheHits / (float) $total) * 100, 1) : 0.0;

        return [
            '### Cache Statistics',
            '',
            '| Type | Count | Rate |',
            '|------|-------|------|',
            '| Hits | ' . $cacheHits . ' | ' . $hitRate . '% |',
            '| Misses | ' . $cacheMisses . ' | ' . (100 - $hitRate) . '% |',
            '| Puts | ' . $cachePuts . ' | - |',
            '',
        ];
    }

    /**
     * @param array<array<string, mixed>> $queries
     * @return array<string, array{count: int, time: float}>
     */
    private function findDuplicateQueries(array $queries): array
    {
        $counts = [];
        foreach ($queries as $query) {
            $rawSql = $query['sql'] ?? '';
            $sql = preg_replace('/\s+/', ' ', trim(is_string($rawSql) ? $rawSql : ''));
            if (!isset($counts[$sql])) {
                $counts[$sql] = ['count' => 0, 'time' => 0];
            }
            ++$counts[$sql]['count'];
            $executionMS = $query['executionMS'] ?? 0;
            $counts[$sql]['time'] += is_numeric($executionMS) ? (float) $executionMS : 0.0;
        }

        $duplicates = array_filter($counts, function ($info) {
            return $info['count'] > 1;
        });

        uasort($duplicates, function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        return $duplicates;
    }
}
