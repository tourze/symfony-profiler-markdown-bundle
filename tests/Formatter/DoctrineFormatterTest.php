<?php

namespace Tourze\ProfilerMarkdownBundle\Tests\Formatter;

use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector as BridgeDoctrineDataCollector;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\ProfilerMarkdownBundle\Formatter\DoctrineFormatter;

/**
 * @internal
 */
#[CoversClass(DoctrineFormatter::class)]
#[RunTestsInSeparateProcesses] final class DoctrineFormatterTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 这个测试类不需要特殊的设置
    }

    #[Test]
    public function supportsDoctrineDataCollector(): void
    {
        $formatter = self::getService(DoctrineFormatter::class);
        $collector = $this->createMock(DoctrineDataCollector::class);
        $this->assertTrue($formatter->supports($collector));
    }

    #[Test]
    public function supportsBridgeDoctrineDataCollector(): void
    {
        $formatter = self::getService(DoctrineFormatter::class);
        $collector = $this->createMock(BridgeDoctrineDataCollector::class);
        $this->assertTrue($formatter->supports($collector));
    }

    #[Test]
    public function doesNotSupportOtherCollectors(): void
    {
        $formatter = self::getService(DoctrineFormatter::class);
        $collector = $this->createMock(DataCollectorInterface::class);
        $this->assertFalse($formatter->supports($collector));
    }

    #[Test]
    public function formatReturnsEmptyArrayForUnsupportedCollector(): void
    {
        $formatter = self::getService(DoctrineFormatter::class);
        $collector = $this->createMock(DataCollectorInterface::class);
        $result = $formatter->format($collector);
        $this->assertSame([], $result);
    }

    #[Test]
    public function formatReturnsMarkdownForDoctrineCollector(): void
    {
        $formatter = self::getService(DoctrineFormatter::class);
        $collector = $this->createMock(DoctrineDataCollector::class);
        $collector->method('getQueryCount')->willReturn(5);
        $collector->method('getTime')->willReturn(0.125);
        $collector->method('getQueries')->willReturn([]);

        $result = $formatter->format($collector);

        $this->assertIsArray($result);
        $this->assertContains('## 💾 Database', $result);
        $this->assertContains('### Statistics', $result);
        $this->assertStringContainsString('5', implode("\n", $result));
        $this->assertStringContainsString('125.00', implode("\n", $result));
    }

    #[Test]
    public function formatHandlesQueriesWithDetails(): void
    {
        $collector = $this->createMock(DoctrineDataCollector::class);
        $collector->method('getQueryCount')->willReturn(2);
        $collector->method('getTime')->willReturn(0.05);
        $collector->method('getQueries')->willReturn([
            'default' => [
                [
                    'sql' => 'SELECT * FROM users WHERE id = ?',
                    'params' => [1],
                    'executionMS' => 15.5,
                    'memory' => 2048,
                    'row_count' => 1,
                    'explainable' => true,
                ],
                [
                    'sql' => 'UPDATE users SET name = ? WHERE id = ?',
                    'params' => ['John', 1],
                    'executionMS' => 5.2,
                ],
            ],
        ]);

        $result = self::getService(DoctrineFormatter::class)->format($collector);
        $content = implode("\n", $result);

        $this->assertStringContainsString('SELECT * FROM users', $content);
        $this->assertStringContainsString('UPDATE users SET name', $content);
        $this->assertStringContainsString('15.5', $content);
        $this->assertStringContainsString('### ⚠️ Slow Queries', $content);
    }

    #[Test]
    public function formatDetectsDuplicateQueries(): void
    {
        $collector = $this->createMock(DoctrineDataCollector::class);
        $collector->method('getQueryCount')->willReturn(4);
        $collector->method('getTime')->willReturn(0.1);
        $collector->method('getQueries')->willReturn([
            'default' => [
                ['sql' => 'SELECT * FROM users WHERE id = ?', 'params' => [1], 'executionMS' => 5],
                ['sql' => 'SELECT * FROM users WHERE id = ?', 'params' => [2], 'executionMS' => 5],
                ['sql' => 'SELECT * FROM users WHERE id = ?', 'params' => [3], 'executionMS' => 5],
                ['sql' => 'SELECT * FROM posts', 'params' => [], 'executionMS' => 10],
            ],
        ]);

        $result = self::getService(DoctrineFormatter::class)->format($collector);
        $content = implode("\n", $result);

        $this->assertStringContainsString('### 🔄 Duplicate Queries', $content);
        $this->assertStringContainsString('3x', $content);
    }

    #[Test]
    public function formatHandlesInvalidEntities(): void
    {
        $collector = $this->createMock(DoctrineDataCollector::class);
        $collector->method('getQueryCount')->willReturn(1);
        $collector->method('getTime')->willReturn(0.01);
        $collector->method('getQueries')->willReturn([]);
        $collector->method('getInvalidEntityCount')->willReturn(2);

        $result = self::getService(DoctrineFormatter::class)->format($collector);
        $content = implode("\n", $result);

        $this->assertStringContainsString('Invalid Entities', $content);
        $this->assertStringContainsString('⚠️ 2', $content);
    }

    #[Test]
    public function testSupports(): void
    {
        $collector = $this->createMock(DoctrineDataCollector::class);
        $this->assertTrue(self::getService(DoctrineFormatter::class)->supports($collector));
    }

    #[Test]
    public function testFormat(): void
    {
        $collector = $this->createMock(DoctrineDataCollector::class);
        $collector->method('getQueryCount')->willReturn(5);
        $collector->method('getTime')->willReturn(0.1);
        $collector->method('getQueries')->willReturn([]);

        $result = self::getService(DoctrineFormatter::class)->format($collector);
        $content = implode("\n", $result);

        $this->assertStringContainsString('## 💾 Database', $content);
        $this->assertStringContainsString('5', $content);
    }

    #[Test]
    public function formatHandlesCacheStatistics(): void
    {
        $collector = $this->createMock(DoctrineDataCollector::class);
        $collector->method('getQueryCount')->willReturn(0);
        $collector->method('getTime')->willReturn(0.0);
        $collector->method('getQueries')->willReturn([]);
        $collector->method('getCacheHitsCount')->willReturn(80);
        $collector->method('getCacheMissesCount')->willReturn(20);
        $collector->method('getCachePutsCount')->willReturn(15);

        $result = self::getService(DoctrineFormatter::class)->format($collector);
        $content = implode("\n", $result);

        $this->assertStringContainsString('### Cache Statistics', $content);
        $this->assertStringContainsString('80', $content);
        $this->assertStringContainsString('20', $content);
        $this->assertStringContainsString('80%', $content);
    }

    #[Test]
    public function formatHandlesConnections(): void
    {
        $collector = $this->createMock(DoctrineDataCollector::class);
        $collector->method('getQueryCount')->willReturn(0);
        $collector->method('getTime')->willReturn(0.0);
        $collector->method('getQueries')->willReturn([]);
        $collector->method('getConnections')->willReturn([
            'default' => ['driver' => 'pdo_mysql'],
            'secondary' => ['driver' => 'pdo_pgsql'],
        ]);

        $result = self::getService(DoctrineFormatter::class)->format($collector);
        $content = implode("\n", $result);

        $this->assertStringContainsString('### Connections', $content);
        $this->assertStringContainsString('default', $content);
        $this->assertStringContainsString('pdo_mysql', $content);
        $this->assertStringContainsString('secondary', $content);
        $this->assertStringContainsString('pdo_pgsql', $content);
    }

    #[Test]
    public function formatLimitsDisplayedQueries(): void
    {
        $queries = [];
        for ($i = 1; $i <= 15; ++$i) {
            $queries[] = [
                'sql' => "SELECT * FROM table_{$i}",
                'params' => [],
                'executionMS' => $i,
            ];
        }

        $collector = $this->createMock(DoctrineDataCollector::class);
        $collector->method('getQueryCount')->willReturn(15);
        $collector->method('getTime')->willReturn(0.2);
        $collector->method('getQueries')->willReturn(['default' => $queries]);

        $result = self::getService(DoctrineFormatter::class)->format($collector);
        $content = implode("\n", $result);

        $this->assertStringContainsString('Showing 10 of 15 queries', $content);
    }
}
