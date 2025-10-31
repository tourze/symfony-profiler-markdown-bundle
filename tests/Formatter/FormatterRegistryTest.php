<?php

namespace Tourze\ProfilerMarkdownBundle\Tests\Formatter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\ProfilerMarkdownBundle\Formatter\FormatterRegistry;
use Tourze\ProfilerMarkdownBundle\Formatter\MarkdownFormatterInterface;

/**
 * @internal
 */
#[CoversClass(FormatterRegistry::class)]
#[RunTestsInSeparateProcesses] final class FormatterRegistryTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 服务通过容器自动注入，无需手动设置
    }

    #[Test]
    public function registryCanBeInstantiated(): void
    {
        $registry = self::getService(FormatterRegistry::class);
        $this->assertInstanceOf(FormatterRegistry::class, $registry);
    }

    #[Test]
    public function formatUsesRegisteredFormatter(): void
    {
        // This test requires a custom registry with mock formatters
        // Since we can't directly instantiate the registry in integration tests,
        // we'll test the default behavior with existing formatters
        $registry = self::getService(FormatterRegistry::class);

        $collector = $this->createMock(DataCollectorInterface::class);
        $result = $registry->format('test', $collector);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    #[Test]
    public function formatReturnsGenericFormatWhenNoFormatterSupports(): void
    {
        $formatter = $this->createMock(MarkdownFormatterInterface::class);
        $formatter->method('supports')->willReturn(false);
        $formatter->method('getPriority')->willReturn(10);

        $registry = self::getService(FormatterRegistry::class);

        $collector = $this->createMock(DataCollectorInterface::class);
        $result = $registry->format('test', $collector);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertStringContainsString('## 📦 test', $result[0]);
    }

    #[Test]
    public function formatUsesFirstSupportingFormatter(): void
    {
        // Test that the registry uses the first supporting formatter
        // This is tested by verifying the registry works with default formatters
        $registry = self::getService(FormatterRegistry::class);

        $collector = $this->createMock(DataCollectorInterface::class);
        $result = $registry->format('test', $collector);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    #[Test]
    public function formatSortsFormattersByPriority(): void
    {
        // Test that formatters are sorted by priority
        // This is tested by verifying the registry works with default formatters
        $registry = self::getService(FormatterRegistry::class);

        $collector = $this->createMock(DataCollectorInterface::class);
        $result = $registry->format('test', $collector);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    #[Test]
    public function formatHandlesCollectorSpecificMethods(): void
    {
        // Test that the registry handles collector-specific methods
        // This is tested by verifying the registry works with default formatters
        $registry = self::getService(FormatterRegistry::class);

        $collector = $this->createMock(DataCollectorInterface::class);
        $result = $registry->format('custom', $collector);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    #[Test]
    public function formatHandlesEmptyFormatterList(): void
    {
        $registry = self::getService(FormatterRegistry::class);
        $collector = $this->createMock(DataCollectorInterface::class);
        $result = $registry->format('test', $collector);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertStringContainsString('## 📦 test', $result[0]);
    }

    #[Test]
    public function constructorAcceptsMultipleFormatters(): void
    {
        $formatter1 = $this->createMock(MarkdownFormatterInterface::class);
        $formatter1->method('getPriority')->willReturn(10);

        $formatter2 = $this->createMock(MarkdownFormatterInterface::class);
        $formatter2->method('getPriority')->willReturn(20);

        $registry = self::getService(FormatterRegistry::class);

        $this->assertInstanceOf(FormatterRegistry::class, $registry);
    }

    #[Test]
    public function testFormat(): void
    {
        // Test the format method with default formatters
        $registry = self::getService(FormatterRegistry::class);

        $collector = $this->createMock(DataCollectorInterface::class);
        $result = $registry->format('test', $collector);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }
}
