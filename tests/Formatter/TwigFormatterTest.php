<?php

namespace Tourze\ProfilerMarkdownBundle\Tests\Formatter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bridge\Twig\DataCollector\TwigDataCollector;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\ProfilerMarkdownBundle\Formatter\TwigFormatter;
use Twig\Markup;

/**
 * @internal
 */
#[CoversClass(TwigFormatter::class)]
#[RunTestsInSeparateProcesses] final class TwigFormatterTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 服务通过容器自动注入，无需手动设置
    }

    #[Test]
    public function testSupports(): void
    {
        $formatter = self::getService(TwigFormatter::class);
        $collector = $this->createMock(TwigDataCollector::class);
        $this->assertTrue($formatter->supports($collector));
    }

    #[Test]
    public function testFormat(): void
    {
        $formatter = self::getService(TwigFormatter::class);
        $collector = $this->createMock(TwigDataCollector::class);
        $collector->method('getTime')->willReturn(0.025);
        $collector->method('getTemplateCount')->willReturn(1);
        $collector->method('getBlockCount')->willReturn(0);
        $collector->method('getMacroCount')->willReturn(0);
        $collector->method('getTemplates')->willReturn([]);

        $result = $formatter->format($collector);
        $content = implode("\n", $result);

        $this->assertStringContainsString('## 🎨 Templates (Twig)', $content);
    }

    #[Test]
    public function supportsTwigDataCollector(): void
    {
        $formatter = self::getService(TwigFormatter::class);
        $collector = $this->createMock(TwigDataCollector::class);
        $this->assertTrue($formatter->supports($collector));
    }

    #[Test]
    public function doesNotSupportOtherCollectors(): void
    {
        $formatter = self::getService(TwigFormatter::class);
        $collector = $this->createMock(DataCollectorInterface::class);
        $this->assertFalse($formatter->supports($collector));
    }

    #[Test]
    public function formatReturnsEmptyArrayForUnsupportedCollector(): void
    {
        $formatter = self::getService(TwigFormatter::class);
        $collector = $this->createMock(DataCollectorInterface::class);
        $result = $formatter->format($collector);
        $this->assertSame([], $result);
    }

    #[Test]
    public function formatReturnsMarkdownForTwigCollector(): void
    {
        $formatter = self::getService(TwigFormatter::class);
        $collector = $this->createMock(TwigDataCollector::class);
        $collector->method('getTime')->willReturn(0.025);
        $collector->method('getTemplateCount')->willReturn(10);
        $collector->method('getBlockCount')->willReturn(5);
        $collector->method('getMacroCount')->willReturn(2);
        $collector->method('getTemplates')->willReturn([]);

        $result = $formatter->format($collector);

        $this->assertIsArray($result);
        $this->assertContains('## 🎨 Templates (Twig)', $result);
        $this->assertContains('### Rendering Summary', $result);

        $content = implode("\n", $result);
        $this->assertStringContainsString('25.00', $content);
        $this->assertStringContainsString('10', $content);
        $this->assertStringContainsString('5', $content);
        $this->assertStringContainsString('2', $content);
    }

    #[Test]
    public function formatHandlesTemplatesList(): void
    {
        $formatter = self::getService(TwigFormatter::class);
        $collector = $this->createMock(TwigDataCollector::class);
        $collector->method('getTime')->willReturn(0.1);
        $collector->method('getTemplateCount')->willReturn(3);
        $collector->method('getBlockCount')->willReturn(0);
        $collector->method('getMacroCount')->willReturn(0);
        $collector->method('getTemplates')->willReturn([
            'base.html.twig' => 2,
            '@EasyAdmin/layout.html.twig' => 1,
            'form/fields.html.twig' => 3,
        ]);

        $result = $formatter->format($collector);
        $content = implode("\n", $result);

        $this->assertStringContainsString('### Rendered Templates', $content);
        $this->assertStringContainsString('base.html.twig', $content);
        $this->assertStringContainsString('@EasyAdmin/layout.html.twig', $content);
        $this->assertStringContainsString('form/fields.html.twig', $content);
        $this->assertStringContainsString('📄 App', $content);
        $this->assertStringContainsString('🛠️ Admin', $content);
        $this->assertStringContainsString('📝 Form', $content);
    }

    #[Test]
    public function formatHandlesLongTemplateNames(): void
    {
        $formatter = self::getService(TwigFormatter::class);
        $longTemplateName = 'very/long/path/to/template/that/needs/to/be/shortened/template.html.twig';

        $collector = $this->createMock(TwigDataCollector::class);
        $collector->method('getTime')->willReturn(0.01);
        $collector->method('getTemplateCount')->willReturn(1);
        $collector->method('getBlockCount')->willReturn(0);
        $collector->method('getMacroCount')->willReturn(0);
        $collector->method('getTemplates')->willReturn([
            $longTemplateName => 1,
        ]);

        $result = $formatter->format($collector);
        $content = implode("\n", $result);

        $this->assertStringContainsString('.../shortened/template.html.twig', $content);
    }

    #[Test]
    public function formatGroupsTemplatesByType(): void
    {
        $formatter = self::getService(TwigFormatter::class);
        $collector = $this->createMock(TwigDataCollector::class);
        $collector->method('getTime')->willReturn(0.05);
        $collector->method('getTemplateCount')->willReturn(6);
        $collector->method('getBlockCount')->willReturn(0);
        $collector->method('getMacroCount')->willReturn(0);
        $collector->method('getTemplates')->willReturn([
            'index.html.twig' => 1,
            'about.html.twig' => 1,
            '@Security/login.html.twig' => 1,
            '@Twig/Exception/error.html.twig' => 1,
            'form/custom_widget.html.twig' => 1,
            'component/header.html.twig' => 1,
        ]);

        $result = $formatter->format($collector);
        $content = implode("\n", $result);

        $this->assertStringContainsString('### Template Organization', $content);
        $this->assertStringContainsString('**📄 Application Templates:** 2', $content);
        $this->assertStringContainsString('**📝 Form Templates:** 1', $content);
        $this->assertStringContainsString('**🧩 Component Templates:** 1', $content);
        $this->assertStringContainsString('**📦 Bundle Templates:** 2 across 2 bundles', $content);
    }

    #[Test]
    public function formatHandlesManyTemplates(): void
    {
        $formatter = self::getService(TwigFormatter::class);
        $templates = [];
        for ($i = 1; $i <= 25; ++$i) {
            $templates["template_{$i}.html.twig"] = 25 - $i + 1;
        }

        $collector = $this->createMock(TwigDataCollector::class);
        $collector->method('getTime')->willReturn(0.5);
        $collector->method('getTemplateCount')->willReturn(25);
        $collector->method('getBlockCount')->willReturn(10);
        $collector->method('getMacroCount')->willReturn(5);
        $collector->method('getTemplates')->willReturn($templates);

        $result = $formatter->format($collector);
        $content = implode("\n", $result);

        $this->assertStringContainsString('... and 5 more templates', $content);
    }

    #[Test]
    public function formatShowsCallGraphAvailability(): void
    {
        $formatter = self::getService(TwigFormatter::class);
        $collector = $this->createMock(TwigDataCollector::class);
        $collector->method('getTime')->willReturn(0.01);
        $collector->method('getTemplateCount')->willReturn(1);
        $collector->method('getBlockCount')->willReturn(0);
        $collector->method('getMacroCount')->willReturn(0);
        $collector->method('getTemplates')->willReturn([]);
        $collector->method('getHtmlCallGraph')->willReturn(new Markup('<div>Call graph</div>', 'UTF-8'));

        $result = $formatter->format($collector);
        $content = implode("\n", $result);

        $this->assertStringContainsString('Call Graph Available', $content);
        $this->assertStringContainsString('✅', $content);
    }

    #[Test]
    public function getPriorityReturnsCorrectValue(): void
    {
        $formatter = self::getService(TwigFormatter::class);
        $this->assertSame(40, $formatter->getPriority());
    }

    #[Test]
    public function formatHandlesEmailTemplates(): void
    {
        $formatter = self::getService(TwigFormatter::class);
        $collector = $this->createMock(TwigDataCollector::class);
        $collector->method('getTime')->willReturn(0.02);
        $collector->method('getTemplateCount')->willReturn(2);
        $collector->method('getBlockCount')->willReturn(0);
        $collector->method('getMacroCount')->willReturn(0);
        $collector->method('getTemplates')->willReturn([
            'email/welcome.html.twig' => 1,
            'email/reset_password.html.twig' => 1,
        ]);

        $result = $formatter->format($collector);
        $content = implode("\n", $result);

        $this->assertStringContainsString('📧 Email', $content);
    }
}
