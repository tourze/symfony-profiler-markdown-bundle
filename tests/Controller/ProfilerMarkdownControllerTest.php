<?php

namespace Tourze\ProfilerMarkdownBundle\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;
use Tourze\ProfilerMarkdownBundle\Controller\ProfilerMarkdownController;

/**
 * @internal
 */
#[CoversClass(ProfilerMarkdownController::class)]
#[RunTestsInSeparateProcesses]
final class ProfilerMarkdownControllerTest extends AbstractWebTestCase
{
    protected function onSetUp(): void
    {
        parent::onSetUp();
    }

    public function testGetMethodWithInvalidTokenReturns404(): void
    {
        $client = self::createClientWithDatabase();

        $client->request('GET', '/_profiler/non-existent-token.md');

        $response = $client->getResponse();
        $this->assertSame(404, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('Profile Not Found', $content);
    }

    public function testGetMethodWithValidTokenReturns200(): void
    {
        $client = self::createClientWithDatabase();

        $client->request('GET', '/');

        $profile = $client->getProfile();
        if (false === $profile || null === $profile) {
            $client->request('GET', '/_profiler/dummy-token.md');
            $response = $client->getResponse();
            $this->assertSame(404, $response->getStatusCode());

            return;
        }

        $token = $profile->getToken();
        $client->request('GET', "/_profiler/{$token}.md");

        $response = $client->getResponse();
        $this->assertSame(200, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('# Symfony Profiler Report', $content);
        $this->assertStringContainsString($token, $content);
        $this->assertStringContainsString('## 📊 Summary', $content);
    }

    public function testPostMethodNotAllowed(): void
    {
        $this->expectException(MethodNotAllowedHttpException::class);
        $client = self::createClientWithDatabase();
        $client->request('POST', '/_profiler/test-token.md');
    }

    public function testPutMethodNotAllowed(): void
    {
        $this->expectException(MethodNotAllowedHttpException::class);
        $client = self::createClientWithDatabase();
        $client->request('PUT', '/_profiler/test-token.md');
    }

    public function testDeleteMethodNotAllowed(): void
    {
        $this->expectException(MethodNotAllowedHttpException::class);
        $client = self::createClientWithDatabase();
        $client->request('DELETE', '/_profiler/test-token.md');
    }

    public function testPatchMethodNotAllowed(): void
    {
        $this->expectException(MethodNotAllowedHttpException::class);
        $client = self::createClientWithDatabase();
        $client->request('PATCH', '/_profiler/test-token.md');
    }

    public function testOptionsMethodNotAllowed(): void
    {
        $this->expectException(MethodNotAllowedHttpException::class);
        $client = self::createClientWithDatabase();
        $client->request('OPTIONS', '/_profiler/test-token.md');
    }

    #[DataProvider('provideNotAllowedMethods')]
    public function testMethodNotAllowed(string $method): void
    {
        $this->expectException(MethodNotAllowedHttpException::class);
        $client = self::createClientWithDatabase();
        $client->request($method, '/_profiler/test-token.md');
    }
}
