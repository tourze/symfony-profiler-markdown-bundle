<?php

namespace Tourze\ProfilerMarkdownBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\ProfilerMarkdownBundle\Controller\ProfilerMarkdownController;
use Tourze\ProfilerMarkdownBundle\Service\AttributeControllerLoader;
use Tourze\RoutingAutoLoaderBundle\Service\RoutingAutoLoaderInterface;

/**
 * @internal
 */
#[CoversClass(AttributeControllerLoader::class)]
#[RunTestsInSeparateProcesses] final class AttributeControllerLoaderTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 服务通过容器自动注入，无需手动设置
    }

    #[Test]
    public function itImplementsRoutingAutoLoaderInterface(): void
    {
        $loader = self::getService(AttributeControllerLoader::class);
        $this->assertInstanceOf(RoutingAutoLoaderInterface::class, $loader);
    }

    #[Test]
    public function testSupports(): void
    {
        $loader = self::getService(AttributeControllerLoader::class);
        // The supports method in AttributeControllerLoader always returns false
        $this->assertFalse($loader->supports(ProfilerMarkdownController::class));
        $this->assertFalse($loader->supports('any_resource'));
        $this->assertFalse($loader->supports(null));
    }

    #[Test]
    public function testLoad(): void
    {
        $loader = self::getService(AttributeControllerLoader::class);
        // The load method delegates to autoload
        $result = $loader->load('any_resource');
        $this->assertInstanceOf(RouteCollection::class, $result);

        // Should contain ProfilerMarkdownController route
        $this->assertGreaterThan(0, $result->count());
    }

    #[Test]
    public function testAutoload(): void
    {
        $loader = self::getService(AttributeControllerLoader::class);
        $routes = $loader->autoload();

        $this->assertInstanceOf(RouteCollection::class, $routes);
        $this->assertGreaterThan(0, $routes->count());

        // Check if ProfilerMarkdownController route is loaded
        $foundRoute = false;
        foreach ($routes as $route) {
            $this->assertInstanceOf(Route::class, $route);
            $controller = $route->getDefault('_controller');
            if (str_contains((string) $controller, 'ProfilerMarkdownController')) {
                $foundRoute = true;
                break;
            }
        }

        $this->assertTrue($foundRoute, 'ProfilerMarkdownController route should be loaded');
    }
}
