<?php

declare(strict_types=1);

namespace Tourze\ProfilerMarkdownBundle\Tests\Formatter\Helper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Cloner\Data;
use Tourze\ProfilerMarkdownBundle\Formatter\Helper\FirewallConfigFormatter;

/**
 * @internal
 */
#[CoversClass(FirewallConfigFormatter::class)]
final class FirewallConfigFormatterTest extends TestCase
{
    private FirewallConfigFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new FirewallConfigFormatter();
    }

    #[Test]
    public function testFormatWithNullFirewall(): void
    {
        $result = $this->formatter->format(null);

        $this->assertSame([], $result);
    }

    #[Test]
    public function testFormatWithArrayFirewall(): void
    {
        $firewall = [
            'name' => 'main',
            'pattern' => '^/',
            'context' => 'main',
            'stateless' => false,
            'provider' => 'app_user_provider',
            'entry_point' => 'form_login',
            'authenticators' => [
                'App\Security\LoginFormAuthenticator',
                'App\Security\ApiKeyAuthenticator',
            ],
        ];

        $result = $this->formatter->format($firewall);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertStringContainsString('### Firewall Configuration', $result[0]);

        $markdown = implode("\n", $result);
        $this->assertStringContainsString('main', $markdown);
        $this->assertStringContainsString('`^/`', $markdown);
        $this->assertStringContainsString('app_user_provider', $markdown);
        $this->assertStringContainsString('form_login', $markdown);
        $this->assertStringContainsString('No', $markdown); // stateless = false
        $this->assertStringContainsString('#### Authenticators', $markdown);
        $this->assertStringContainsString('LoginFormAuthenticator', $markdown);
        $this->assertStringContainsString('ApiKeyAuthenticator', $markdown);
    }

    #[Test]
    public function testFormatWithMinimalArrayFirewall(): void
    {
        $firewall = [
            'name' => 'api',
            'pattern' => '^/api',
        ];

        $result = $this->formatter->format($firewall);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        $markdown = implode("\n", $result);
        $this->assertStringContainsString('api', $markdown);
        $this->assertStringContainsString('`^/api`', $markdown);
        $this->assertStringNotContainsString('Authenticators', $markdown);
    }

    #[Test]
    public function testFormatWithStatelessFirewall(): void
    {
        $firewall = [
            'name' => 'stateless_api',
            'stateless' => true,
        ];

        $result = $this->formatter->format($firewall);

        $markdown = implode("\n", $result);
        $this->assertStringContainsString('Yes', $markdown); // stateless = true
    }

    #[Test]
    public function testFormatWithEmptyAuthenticators(): void
    {
        $firewall = [
            'name' => 'no_auth',
            'authenticators' => [],
        ];

        $result = $this->formatter->format($firewall);

        $markdown = implode("\n", $result);
        $this->assertStringNotContainsString('#### Authenticators', $markdown);
    }

    #[Test]
    public function testFormatWithInvalidAuthenticators(): void
    {
        $firewall = [
            'name' => 'invalid_auth',
            'authenticators' => 'not_an_array',
        ];

        $result = $this->formatter->format($firewall);

        $markdown = implode("\n", $result);
        $this->assertStringNotContainsString('#### Authenticators', $markdown);
    }

    #[Test]
    public function testFormatWithScalarFirewall(): void
    {
        $result = $this->formatter->format('simple_string');

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        $markdown = implode("\n", $result);
        $this->assertStringContainsString('### Firewall Configuration', $markdown);
        $this->assertStringContainsString('**Firewall:** simple_string', $markdown);
    }

    #[Test]
    public function testFormatWithDataObjectFirewall(): void
    {
        $firewallArray = [
            'name' => 'from_data',
            'pattern' => '^/secure',
        ];

        $firewallData = $this->createMock(Data::class);
        $firewallData->method('getValue')->with(true)->willReturn($firewallArray);

        $result = $this->formatter->format($firewallData);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        $markdown = implode("\n", $result);
        $this->assertStringContainsString('from_data', $markdown);
        $this->assertStringContainsString('^\/secure', $markdown);
    }

    #[Test]
    public function testFormatWithComplexAuthenticators(): void
    {
        $firewall = [
            'name' => 'complex',
            'authenticators' => [
                'Very\Long\Namespace\That\Should\Be\Shortened\AuthenticatorClass',
                123, // 非字符串值
                new \stdClass(), // 对象
            ],
        ];

        $result = $this->formatter->format($firewall);

        $markdown = implode("\n", $result);
        $this->assertStringContainsString('#### Authenticators', $markdown);
        $this->assertStringContainsString('AuthenticatorClass', $markdown);
        $this->assertStringContainsString('123', $markdown);
        $this->assertStringContainsString('stdClass', $markdown);
    }

    #[Test]
    public function testFormatWithAllProperties(): void
    {
        $firewall = [
            'name' => 'complete',
            'pattern' => '^/admin',
            'context' => 'admin_context',
            'provider' => 'admin_provider',
            'entry_point' => 'admin_entry',
            'stateless' => false,
            'authenticators' => ['AdminAuthenticator'],
            'extra_property' => 'should_be_ignored', // 额外属性应该被忽略
        ];

        $result = $this->formatter->format($firewall);

        $markdown = implode("\n", $result);
        $this->assertStringContainsString('complete', $markdown);
        $this->assertStringContainsString('`^/admin`', $markdown);
        $this->assertStringContainsString('admin_context', $markdown);
        $this->assertStringContainsString('admin_provider', $markdown);
        $this->assertStringContainsString('admin_entry', $markdown);
        $this->assertStringContainsString('No', $markdown);
        $this->assertStringContainsString('AdminAuthenticator', $markdown);
        $this->assertStringNotContainsString('extra_property', $markdown);
    }

    #[Test]
    public function testFormatWithJsonInvalidArray(): void
    {
        // 创建一个会导致JSON编码失败的标量值（包含资源）
        $resource = fopen('php://memory', 'r');
        $invalidData = ['resource' => $resource];

        $result = $this->formatter->format($invalidData);

        $markdown = implode("\n", $result);
        // 数组格式的firewall会使用表格形式，不会包含'Array'
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        if (is_resource($resource)) {
            fclose($resource);
        }
    }

    #[Test]
    public function testFormatWithBooleanValue(): void
    {
        $result = $this->formatter->format(true);

        $markdown = implode("\n", $result);
        $this->assertStringContainsString('**Firewall:** 1', $markdown);
    }

    #[Test]
    public function testFormatWithNumericValue(): void
    {
        $result = $this->formatter->format(42);

        $markdown = implode("\n", $result);
        $this->assertStringContainsString('**Firewall:** 42', $markdown);
    }
}
