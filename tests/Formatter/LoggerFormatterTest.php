<?php

namespace Tourze\ProfilerMarkdownBundle\Tests\Formatter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\DataCollector\LoggerDataCollector;
use Symfony\Component\VarDumper\Cloner\Data;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\ProfilerMarkdownBundle\Formatter\LoggerFormatter;

/**
 * @internal
 */
#[CoversClass(LoggerFormatter::class)]
#[RunTestsInSeparateProcesses] final class LoggerFormatterTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 服务通过容器自动注入，无需手动设置
    }

    #[Test]
    public function testSupports(): void
    {
        $formatter = self::getService(LoggerFormatter::class);
        $loggerCollector = $this->createMock(LoggerDataCollector::class);
        $this->assertTrue($formatter->supports($loggerCollector));

        $otherCollector = $this->createMock(DataCollectorInterface::class);
        $this->assertFalse($formatter->supports($otherCollector));
    }

    #[Test]
    public function testFormat(): void
    {
        $formatter = self::getService(LoggerFormatter::class);
        $collector = $this->createMock(LoggerDataCollector::class);
        $collector->method('countErrors')->willReturn(2);
        $collector->method('countWarnings')->willReturn(3);

        $logs = [
            [
                'priority' => 500,
                'priorityName' => 'ERROR',
                'message' => 'An error occurred',
                'context' => [],
                'channel' => 'app',
                'timestamp' => time(),
            ],
            [
                'priority' => 300,
                'priorityName' => 'WARNING',
                'message' => 'A warning occurred',
                'context' => ['user' => 'test'],
                'channel' => 'security',
                'timestamp' => time(),
            ],
            [
                'priority' => 200,
                'priorityName' => 'INFO',
                'message' => 'Info log with {placeholder}',
                'context' => ['placeholder' => 'value'],
                'channel' => 'app',
                'timestamp' => time(),
            ],
        ];

        $data = $this->createMock(Data::class);
        $data->method('getValue')->with(true)->willReturn($logs);

        $collector->method('getLogs')->willReturn($data);

        $result = $formatter->format($collector);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertStringContainsString('## 📋 Logs', $result[0]);

        $markdown = implode("\n", $result);
        $this->assertStringContainsString('ERROR', $markdown);
        $this->assertStringContainsString('WARNING', $markdown);
        $this->assertStringContainsString('An error occurred', $markdown);
        $this->assertStringContainsString('A warning occurred', $markdown);
    }

    #[Test]
    public function testFormatWithEmptyLogs(): void
    {
        $formatter = self::getService(LoggerFormatter::class);
        $collector = $this->createMock(LoggerDataCollector::class);
        $collector->method('countErrors')->willReturn(0);
        $collector->method('countWarnings')->willReturn(0);

        $data = $this->createMock(Data::class);
        $data->method('getValue')->with(true)->willReturn([]);
        $collector->method('getLogs')->willReturn($data);

        $result = $formatter->format($collector);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertStringContainsString('## 📋 Logs', $result[0]);
    }

    #[Test]
    public function testFormatWithNonLoggerCollector(): void
    {
        $formatter = self::getService(LoggerFormatter::class);
        $collector = $this->createMock(DataCollectorInterface::class);
        $result = $formatter->format($collector);
        $this->assertSame([], $result);
    }

    #[Test]
    public function testGetPriority(): void
    {
        $formatter = self::getService(LoggerFormatter::class);
        $this->assertSame(50, $formatter->getPriority());
    }
}
