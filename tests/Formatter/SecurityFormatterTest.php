<?php

namespace Tourze\ProfilerMarkdownBundle\Tests\Formatter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\SecurityBundle\DataCollector\SecurityDataCollector;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\VarDumper\Cloner\Data;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\ProfilerMarkdownBundle\Formatter\SecurityFormatter;

/**
 * @internal
 */
#[CoversClass(SecurityFormatter::class)]
#[RunTestsInSeparateProcesses] final class SecurityFormatterTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 服务通过容器自动注入，无需手动设置
    }

    #[Test]
    public function testSupports(): void
    {
        $formatter = self::getService(SecurityFormatter::class);
        $securityCollector = $this->createMock(SecurityDataCollector::class);
        $this->assertTrue($formatter->supports($securityCollector));

        $otherCollector = $this->createMock(DataCollectorInterface::class);
        $this->assertFalse($formatter->supports($otherCollector));
    }

    #[Test]
    public function testFormat(): void
    {
        $collector = $this->createMock(SecurityDataCollector::class);
        $collector->method('isEnabled')->willReturn(true);
        $collector->method('isAuthenticated')->willReturn(true);
        $collector->method('isImpersonated')->willReturn(false);
        $collector->method('getTokenClass')->willReturn('Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken');
        $collector->method('getUser')->willReturn('admin');

        $roles = ['ROLE_USER', 'ROLE_ADMIN'];
        $rolesData = $this->createMock(Data::class);
        $rolesData->method('getValue')->with(true)->willReturn($roles);
        $collector->method('getRoles')->willReturn($rolesData);

        $collector->method('getInheritedRoles')->willReturn(['ROLE_USER', 'ROLE_ADMIN', 'ROLE_ALLOWED_TO_SWITCH']);

        $firewall = [
            'name' => 'main',
            'pattern' => '^/',
            'context' => 'main',
            'stateless' => false,
            'provider' => 'app_user_provider',
            'entry_point' => 'form_login',
            'authenticators' => [
                'App\Security\LoginFormAuthenticator',
            ],
        ];
        $collector->method('getFirewall')->willReturn($firewall);

        $accessLog = [
            [
                'result' => true,
                'object' => 'App\Entity\User',
                'attributes' => ['ROLE_ADMIN'],
                'voter_details' => [
                    ['class' => 'Symfony\Component\Security\Core\Authorization\Voter\RoleVoter', 'vote' => 1],
                ],
            ],
        ];
        $accessLogData = $this->createMock(Data::class);
        $accessLogData->method('getValue')->with(true)->willReturn($accessLog);
        $collector->method('getAccessDecisionLog')->willReturn($accessLogData);

        $formatter = self::getService(SecurityFormatter::class);
        $result = $formatter->format($collector);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertStringContainsString('## 🔒 Security', $result[0]);

        $markdown = implode("\n", $result);
        $this->assertStringContainsString('✅ Yes', $markdown);
        $this->assertStringContainsString('admin', $markdown);
        $this->assertStringContainsString('ROLE_USER', $markdown);
        $this->assertStringContainsString('ROLE_ADMIN', $markdown);
        $this->assertStringContainsString('main', $markdown);
        $this->assertStringContainsString('Access Control Decisions', $markdown);
    }

    #[Test]
    public function testFormatWhenDisabled(): void
    {
        $collector = $this->createMock(SecurityDataCollector::class);
        $collector->method('isEnabled')->willReturn(false);

        $formatter = self::getService(SecurityFormatter::class);
        $result = $formatter->format($collector);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertStringContainsString('## 🔒 Security', $result[0]);
        $this->assertStringContainsString('Security is not enabled for this request', implode("\n", $result));
    }

    #[Test]
    public function testFormatWithNonSecurityCollector(): void
    {
        $formatter = self::getService(SecurityFormatter::class);
        $collector = $this->createMock(DataCollectorInterface::class);
        $result = $formatter->format($collector);
        $this->assertSame([], $result);
    }

    #[Test]
    public function testGetPriority(): void
    {
        $formatter = self::getService(SecurityFormatter::class);
        $this->assertSame(70, $formatter->getPriority());
    }
}
