<?php

namespace Tourze\ProfilerMarkdownBundle\Tests\Formatter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\DataCollector\TimeDataCollector;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\ProfilerMarkdownBundle\Formatter\TimeFormatter;

/**
 * @internal
 */
#[CoversClass(TimeFormatter::class)]
#[RunTestsInSeparateProcesses] final class TimeFormatterTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 服务通过容器自动注入，无需手动设置
    }

    #[Test]
    public function testSupports(): void
    {
        $formatter = self::getService(TimeFormatter::class);
        $timeCollector = $this->createMock(TimeDataCollector::class);
        $this->assertTrue($formatter->supports($timeCollector));

        $otherCollector = $this->createMock(DataCollectorInterface::class);
        $this->assertFalse($formatter->supports($otherCollector));
    }

    #[Test]
    public function testFormat(): void
    {
        $collector = $this->createMock(TimeDataCollector::class);
        $collector->method('getDuration')->willReturn(150.5);
        $collector->method('getInitTime')->willReturn(20.3);

        $event1 = new class {
            public function getDuration(): float
            {
                return 50.0;
            }

            public function getCategory(): string
            {
                return 'controller';
            }

            public function getMemory(): int
            {
                return 1048576; // 1 MB
            }
        };

        $event2 = new class {
            public function getDuration(): float
            {
                return 30.0;
            }

            public function getCategory(): string
            {
                return 'doctrine';
            }

            public function getMemory(): int
            {
                return 2097152; // 2 MB
            }
        };

        $event3 = new class {
            public function getDuration(): float
            {
                return 10.0;
            }

            public function getCategory(): string
            {
                return 'twig';
            }

            public function getMemory(): int
            {
                return 512000; // 0.5 MB
            }
        };

        $events = [
            'controller.action' => $event1,
            'doctrine.query' => $event2,
            'twig.render' => $event3,
        ];

        $collector->method('getEvents')->willReturn($events);

        $formatter = self::getService(TimeFormatter::class);
        $result = $formatter->format($collector);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertStringContainsString('## ⏱️ Performance', $result[0]);

        $markdown = implode("\n", $result);
        $this->assertStringContainsString('150.50 ms', $markdown);
        $this->assertStringContainsString('20.30 ms', $markdown);
        $this->assertStringContainsString('130.20 ms', $markdown); // PHP Execution
        $this->assertStringContainsString('Time by Category', $markdown);
        $this->assertStringContainsString('controller', $markdown);
        $this->assertStringContainsString('doctrine', $markdown);
        $this->assertStringContainsString('twig', $markdown);
        $this->assertStringContainsString('Top 10 Slowest Events', $markdown);
        $this->assertStringContainsString('Timeline Visualization', $markdown);
    }

    #[Test]
    public function testFormatWithEmptyEvents(): void
    {
        $collector = $this->createMock(TimeDataCollector::class);
        $collector->method('getDuration')->willReturn(100.0);
        $collector->method('getInitTime')->willReturn(10.0);
        $collector->method('getEvents')->willReturn([]);

        $formatter = self::getService(TimeFormatter::class);
        $result = $formatter->format($collector);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertStringContainsString('## ⏱️ Performance', $result[0]);

        $markdown = implode("\n", $result);
        $this->assertStringContainsString('100.00 ms', $markdown);
        $this->assertStringNotContainsString('Time by Category', $markdown);
        $this->assertStringNotContainsString('Top 10 Slowest Events', $markdown);
    }

    #[Test]
    public function testFormatWithNonTimeCollector(): void
    {
        $formatter = self::getService(TimeFormatter::class);
        $collector = $this->createMock(DataCollectorInterface::class);
        $result = $formatter->format($collector);
        $this->assertSame([], $result);
    }

    #[Test]
    public function testGetPriority(): void
    {
        $formatter = self::getService(TimeFormatter::class);
        $this->assertSame(90, $formatter->getPriority());
    }
}
