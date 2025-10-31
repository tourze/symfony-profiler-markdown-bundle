<?php

namespace Tourze\ProfilerMarkdownBundle\Formatter;

use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\DataCollector\TimeDataCollector;
use Symfony\Component\Stopwatch\StopwatchEvent;

class TimeFormatter extends AbstractMarkdownFormatter
{
    public function supports(DataCollectorInterface $collector): bool
    {
        return $collector instanceof TimeDataCollector;
    }

    /**
     * @return array<int, string>
     */
    public function format(DataCollectorInterface $collector): array
    {
        if (!$collector instanceof TimeDataCollector) {
            return [];
        }

        $markdown = [
            '## ⏱️ Performance',
            '',
        ];

        $events = $collector->getEvents();
        $markdown = array_merge($markdown, $this->formatPerformanceSummary($collector, $events));

        if ([] !== $events) {
            $eventList = $this->extractEventList($events);
            $markdown = array_merge($markdown, $this->formatTimeByCategory($collector, $events));
            $markdown = array_merge($markdown, $this->formatSlowestEvents($eventList));
            $markdown = array_merge($markdown, $this->formatTimelineVisualization($collector, $eventList));
        }

        return $markdown;
    }

    /**
     * @param array<string, StopwatchEvent> $events
     * @return array<int, string>
     */
    private function formatPerformanceSummary(TimeDataCollector $collector, array $events): array
    {
        $markdown = [
            '### Performance Summary',
            '',
            '| Metric | Value |',
            '|--------|-------|',
            '| **Total Time** | ' . number_format($collector->getDuration(), 2) . ' ms |',
            '| **Initialization Time** | ' . number_format($collector->getInitTime(), 2) . ' ms |',
            '| **PHP Execution** | ' . number_format($collector->getDuration() - $collector->getInitTime(), 2) . ' ms |',
        ];

        if ([] !== $events) {
            $maxMemory = $this->findMaxMemory($events);
            if ($maxMemory > 0) {
                $markdown[] = '| **Peak Memory** | ' . $this->formatBytes($maxMemory) . ' |';
            }
            $markdown[] = '| **Total Events** | ' . count($events) . ' |';
        }

        $markdown[] = '';

        return $markdown;
    }

    /**
     * @param array<string, StopwatchEvent> $events
     */
    private function findMaxMemory(array $events): int
    {
        $maxMemory = 0;
        foreach ($events as $event) {
            $memory = $event->getMemory();
            if ($memory > $maxMemory) {
                $maxMemory = $memory;
            }
        }

        return $maxMemory;
    }

    /**
     * @param array<string, StopwatchEvent> $events
     * @return array<int, string>
     */
    private function formatTimeByCategory(TimeDataCollector $collector, array $events): array
    {
        $categoryTimes = $this->calculateCategoryTimes($events);
        if ([] === $categoryTimes) {
            return [];
        }

        arsort($categoryTimes);

        $markdown = [
            '### Time by Category',
            '',
            '| Category | Time (ms) | % of Total |',
            '|----------|-----------|------------|',
        ];

        $totalTime = $collector->getDuration();
        foreach ($categoryTimes as $category => $time) {
            $percentage = $totalTime > 0 ? round(($time / $totalTime) * 100, 1) : 0;
            $markdown[] = '| ' . ucfirst($category) . ' | ' . number_format($time, 2) . ' | ' . $percentage . '% |';
        }
        $markdown[] = '';

        return $markdown;
    }

    /**
     * @param array<string, StopwatchEvent> $events
     * @return array<string, float>
     */
    private function calculateCategoryTimes(array $events): array
    {
        $categoryTimes = [];

        foreach ($events as $event) {
            $category = $event->getCategory();
            if (!isset($categoryTimes[$category])) {
                $categoryTimes[$category] = 0;
            }
            $categoryTimes[$category] += $event->getDuration();
        }

        return $categoryTimes;
    }

    /**
     * @param array<string, StopwatchEvent> $events
     * @return array<int, array{name: string, duration: float|int, category: string, memory: int}>
     */
    private function extractEventList(array $events): array
    {
        $eventList = [];

        foreach ($events as $name => $event) {
            $eventList[] = [
                'name' => $name,
                'duration' => $event->getDuration(),
                'category' => $event->getCategory(),
                'memory' => $event->getMemory(),
            ];
        }

        usort($eventList, function (array $a, array $b): int {
            return (float) $b['duration'] <=> (float) $a['duration'];
        });

        return $eventList;
    }

    /**
     * @param array<int, array{name: string, duration: float|int, category: string, memory: int}> $eventList
     * @return array<int, string>
     */
    private function formatSlowestEvents(array $eventList): array
    {
        $markdown = [
            '### Top 10 Slowest Events',
            '',
            '| Event | Category | Duration (ms) | Memory (MB) |',
            '|-------|----------|---------------|-------------|',
        ];

        foreach (array_slice($eventList, 0, 10) as $event) {
            $name = $this->truncateEventName($event['name']);
            $memory = $this->formatEventMemory($event['memory']);
            $markdown[] = "| {$name} | {$event['category']} | " . number_format($event['duration'], 2) . " | {$memory} |";
        }
        $markdown[] = '';

        return $markdown;
    }

    private function truncateEventName(string $name): string
    {
        return strlen($name) > 40 ? substr($name, 0, 40) . '...' : $name;
    }

    private function formatEventMemory(int $memory): string
    {
        return $memory > 0 ? number_format($memory / 1048576, 2) : 'N/A';
    }

    /**
     * @param array<int, array{name: string, duration: float|int, category: string, memory: int}> $eventList
     * @return array<int, string>
     */
    private function formatTimelineVisualization(TimeDataCollector $collector, array $eventList): array
    {
        if ([] === $eventList) {
            return [];
        }

        $markdown = [
            '### Timeline Visualization',
            '',
            '```',
        ];

        $maxDuration = $collector->getDuration();
        $scale = 50;

        foreach (array_slice($eventList, 0, 15) as $event) {
            $barLength = $maxDuration > 0 ? round(((float) $event['duration'] / $maxDuration) * $scale) : 0;
            $bar = str_repeat('█', max(1, (int) $barLength));
            $name = substr($event['name'], 0, 30);
            $markdown[] = sprintf('%-30s %s %.2fms', $name, $bar, $event['duration']);
        }

        $markdown[] = '```';
        $markdown[] = '';

        return $markdown;
    }

    public function getPriority(): int
    {
        return 90;
    }
}
