<?php

namespace Tourze\ProfilerMarkdownBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;
use Tourze\ProfilerMarkdownBundle\DependencyInjection\ProfilerMarkdownExtension;

/**
 * @internal
 */
#[CoversClass(ProfilerMarkdownExtension::class)]
final class ProfilerMarkdownExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
    private ProfilerMarkdownExtension $extension;

    private ContainerBuilder $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extension = new ProfilerMarkdownExtension();
        $this->container = new ContainerBuilder();
        $this->container->setParameter('kernel.environment', 'test');
    }

    #[Test]
    public function testLoad(): void
    {
        $this->extension->load([], $this->container);

        // Check that services were registered using their FQCN
        $this->assertTrue($this->container->hasDefinition('Tourze\ProfilerMarkdownBundle\Controller\ProfilerMarkdownController'));
        $this->assertTrue($this->container->hasDefinition('Tourze\ProfilerMarkdownBundle\Formatter\FormatterRegistry'));

        // Check formatter services
        $this->assertTrue($this->container->hasDefinition('Tourze\ProfilerMarkdownBundle\Formatter\DoctrineFormatter'));
        $this->assertTrue($this->container->hasDefinition('Tourze\ProfilerMarkdownBundle\Formatter\LoggerFormatter'));
        $this->assertTrue($this->container->hasDefinition('Tourze\ProfilerMarkdownBundle\Formatter\RequestFormatter'));
        $this->assertTrue($this->container->hasDefinition('Tourze\ProfilerMarkdownBundle\Formatter\SecurityFormatter'));
        $this->assertTrue($this->container->hasDefinition('Tourze\ProfilerMarkdownBundle\Formatter\TimeFormatter'));
        $this->assertTrue($this->container->hasDefinition('Tourze\ProfilerMarkdownBundle\Formatter\TwigFormatter'));

        // Check service loader
        $this->assertTrue($this->container->hasDefinition('Tourze\ProfilerMarkdownBundle\Service\AttributeControllerLoader'));
    }

    #[Test]
    public function getAlias(): void
    {
        $alias = $this->extension->getAlias();
        $this->assertSame('profiler_markdown', $alias);
    }

    #[Test]
    public function loadWithEmptyConfigDoesNotThrow(): void
    {
        $this->expectNotToPerformAssertions();
        $this->extension->load([], $this->container);
    }
}
