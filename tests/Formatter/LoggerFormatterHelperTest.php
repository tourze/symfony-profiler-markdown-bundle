<?php

declare(strict_types=1);

namespace Tourze\ProfilerMarkdownBundle\Tests\Formatter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\DataCollector\LoggerDataCollector;
use Symfony\Component\VarDumper\Cloner\Data;
use Tourze\ProfilerMarkdownBundle\Formatter\AbstractMarkdownFormatter;
use Tourze\ProfilerMarkdownBundle\Formatter\LoggerFormatterHelper;

/**
 * @internal
 */
#[CoversClass(LoggerFormatterHelper::class)]
class LoggerFormatterHelperTest extends TestCase
{
    private LoggerFormatterHelper $helper;

    private LoggerDataCollector $collector;

    private AbstractMarkdownFormatter $formatter;

    protected function setUp(): void
    {
        $this->helper = new LoggerFormatterHelper();
        $this->collector = $this->createMock(LoggerDataCollector::class);
        $this->formatter = $this->createMock(AbstractMarkdownFormatter::class);
    }

    public function testFormatLogSummaryWithNoLogs(): void
    {
        $this->collector->method('countErrors')->willReturn(0);
        $this->collector->method('countWarnings')->willReturn(0);

        $result = $this->helper->formatLogSummary($this->collector);

        $this->assertSame([
            '### Log Summary',
            '',
            '| Level | Count | Icon |',
            '|-------|-------|------|',
            '',
        ], $result);
    }

    public function testFormatLogSummaryWithErrors(): void
    {
        $this->collector->method('countErrors')->willReturn(5);
        $this->collector->method('countWarnings')->willReturn(0);

        $result = $this->helper->formatLogSummary($this->collector);

        $this->assertStringContainsString('| **ERROR** | 5 | 🔴 |', implode("\n", $result));
    }

    public function testFormatLogSummaryWithWarnings(): void
    {
        $this->collector->method('countErrors')->willReturn(0);
        $this->collector->method('countWarnings')->willReturn(3);

        $result = $this->helper->formatLogSummary($this->collector);

        $this->assertStringContainsString('| **WARNING** | 3 | 🟡 |', implode("\n", $result));
    }

    public function testExtractLogsWithArray(): void
    {
        $logs = [
            ['message' => 'test message', 'priority' => 200],
        ];
        $this->collector->method('getLogs')->willReturn($logs);

        $result = $this->helper->extractLogs($this->collector);

        $this->assertSame($logs, $result);
    }

    public function testExtractLogsWithData(): void
    {
        $logs = [
            ['message' => 'test message', 'priority' => 200],
        ];
        $data = $this->createMock(Data::class);
        $data->method('getValue')->with(true)->willReturn($logs);
        $this->collector->method('getLogs')->willReturn($data);

        $result = $this->helper->extractLogs($this->collector);

        $this->assertSame($logs, $result);
    }

    public function testExtractLogsWithInvalidData(): void
    {
        // Mock 需要返回 Data|array 类型，所以我们返回一个空数组
        $this->collector->method('getLogs')->willReturn([]);

        $result = $this->helper->extractLogs($this->collector);

        $this->assertSame([], $result);
    }

    public function testGroupLogsByLevel(): void
    {
        $logs = [
            ['message' => 'error log', 'priority' => 500, 'channel' => 'app'],
            ['message' => 'warning log', 'priority' => 300, 'channel' => 'app'],
            ['message' => 'info log', 'priority' => 200, 'channel' => 'app'],
        ];

        $result = $this->helper->groupLogsByLevel($logs, $this->formatter);

        $this->assertCount(1, $result['error']);
        $this->assertCount(1, $result['warning']);
        $this->assertCount(1, $result['info']);
        $this->assertCount(0, $result['debug']);
    }

    public function testFormatDeprecationsWithNoDeprecations(): void
    {
        $this->collector->method('countDeprecations')->willReturn(0);

        $result = $this->helper->formatDeprecations($this->collector);

        $this->assertSame([], $result);
    }

    public function testFormatDeprecationsWithDeprecations(): void
    {
        $this->collector->method('countDeprecations')->willReturn(2);

        $result = $this->helper->formatDeprecations($this->collector);

        $this->assertStringContainsString('**Total Deprecations:** 2', implode("\n", $result));
    }

    public function testFormatChannelsSummaryWithSingleChannel(): void
    {
        $logs = [
            ['message' => 'test', 'channel' => 'app'],
        ];

        $result = $this->helper->formatChannelsSummary($logs);

        $this->assertSame([], $result);
    }

    public function testFormatChannelsSummaryWithMultipleChannels(): void
    {
        $logs = [
            ['message' => 'test1', 'channel' => 'app'],
            ['message' => 'test2', 'channel' => 'api'],
            ['message' => 'test3', 'channel' => 'app'],
        ];

        $result = $this->helper->formatChannelsSummary($logs);

        $resultString = implode("\n", $result);
        $this->assertStringContainsString('| Channel | Log Count |', $resultString);
        $this->assertStringContainsString('| app | 2 |', $resultString);
        $this->assertStringContainsString('| api | 1 |', $resultString);
    }
}
