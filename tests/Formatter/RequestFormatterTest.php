<?php

namespace Tourze\ProfilerMarkdownBundle\Tests\Formatter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\DataCollector\RequestDataCollector;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\ProfilerMarkdownBundle\Formatter\RequestFormatter;

/**
 * @internal
 */
#[CoversClass(RequestFormatter::class)]
#[RunTestsInSeparateProcesses] final class RequestFormatterTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 服务通过容器自动注入，无需手动设置
    }

    #[Test]
    public function testSupports(): void
    {
        $formatter = self::getService(RequestFormatter::class);
        $requestCollector = $this->createMock(RequestDataCollector::class);
        $this->assertTrue($formatter->supports($requestCollector));

        $otherCollector = $this->createMock(DataCollectorInterface::class);
        $this->assertFalse($formatter->supports($otherCollector));
    }

    #[Test]
    public function testFormat(): void
    {
        $collector = $this->createMock(RequestDataCollector::class);
        $collector->method('getRoute')->willReturn('app_home');
        $collector->method('getController')->willReturn([
            'class' => 'App\Controller\HomeController',
            'method' => 'index',
        ]);
        $collector->method('getMethod')->willReturn('GET');
        $collector->method('getPathInfo')->willReturn('/home');
        $collector->method('getFormat')->willReturn('html');
        $collector->method('getLocale')->willReturn('en');
        $collector->method('getStatusCode')->willReturn(200);
        $collector->method('getRedirect')->willReturn(false);

        $attributes = $this->createMock(ParameterBag::class);
        $attributes->method('all')->willReturn(['_route' => 'app_home', '_controller' => 'App\Controller\HomeController::index']);
        $collector->method('getRequestAttributes')->willReturn($attributes);

        $headerBag = $this->createMock(ParameterBag::class);
        $headerBag->method('all')->willReturn(['Content-Type' => ['text/html'], 'Accept' => ['*/*']]);
        $collector->method('getRequestHeaders')->willReturn($headerBag);
        $collector->method('getResponseHeaders')->willReturn($headerBag);

        $query = $this->createMock(ParameterBag::class);
        $query->method('all')->willReturn(['page' => 1]);
        $collector->method('getRequestQuery')->willReturn($query);

        $post = $this->createMock(ParameterBag::class);
        $post->method('all')->willReturn([]);
        $collector->method('getRequestRequest')->willReturn($post);

        $cookies = $this->createMock(ParameterBag::class);
        $cookies->method('all')->willReturn(['session_id' => 'abc123']);
        $collector->method('getRequestCookies')->willReturn($cookies);

        $collector->method('getSessionAttributes')->willReturn(['user_id' => 1]);
        $collector->method('getFlashes')->willReturn(['success' => ['Operation completed']]);

        $formatter = self::getService(RequestFormatter::class);
        $result = $formatter->format($collector);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertStringContainsString('## 📨 Request & Response', $result[0]);

        $markdown = implode("\n", $result);
        $this->assertStringContainsString('app_home', $markdown);
        $this->assertStringContainsString('HomeController::index', $markdown);
        $this->assertStringContainsString('GET', $markdown);
        $this->assertStringContainsString('/home', $markdown);
        $this->assertStringContainsString('200', $markdown);
        $this->assertStringContainsString('Request Headers', $markdown);
        $this->assertStringContainsString('Response Headers', $markdown);
        $this->assertStringContainsString('Cookies', $markdown);
        $this->assertStringContainsString('Session Data', $markdown);
        $this->assertStringContainsString('Flash Messages', $markdown);
    }

    #[Test]
    public function testFormatWithRedirect(): void
    {
        $collector = $this->createMock(RequestDataCollector::class);
        $collector->method('getRoute')->willReturn('app_login');
        $collector->method('getController')->willReturn('App\Controller\SecurityController::login');
        $collector->method('getMethod')->willReturn('POST');
        $collector->method('getPathInfo')->willReturn('/login');
        $collector->method('getFormat')->willReturn('html');
        $collector->method('getLocale')->willReturn('en');
        $collector->method('getStatusCode')->willReturn(302);
        $collector->method('getRedirect')->willReturn([
            'location' => '/dashboard',
            'status_code' => 302,
        ]);

        $emptyBag = $this->createMock(ParameterBag::class);
        $emptyBag->method('all')->willReturn([]);

        $collector->method('getRequestAttributes')->willReturn($emptyBag);
        $collector->method('getRequestHeaders')->willReturn($emptyBag);
        $collector->method('getResponseHeaders')->willReturn($emptyBag);
        $collector->method('getRequestQuery')->willReturn($emptyBag);
        $collector->method('getRequestRequest')->willReturn($emptyBag);
        $collector->method('getRequestCookies')->willReturn($emptyBag);

        $formatter = self::getService(RequestFormatter::class);
        $result = $formatter->format($collector);

        $markdown = implode("\n", $result);
        $this->assertStringContainsString('Redirect', $markdown);
        $this->assertStringContainsString('/dashboard', $markdown);
        $this->assertStringContainsString('302', $markdown);
    }

    #[Test]
    public function testFormatWithNonRequestCollector(): void
    {
        $formatter = self::getService(RequestFormatter::class);
        $collector = $this->createMock(DataCollectorInterface::class);
        $result = $formatter->format($collector);
        $this->assertSame([], $result);
    }

    #[Test]
    public function testGetPriority(): void
    {
        $formatter = self::getService(RequestFormatter::class);
        $this->assertSame(100, $formatter->getPriority());
    }
}
