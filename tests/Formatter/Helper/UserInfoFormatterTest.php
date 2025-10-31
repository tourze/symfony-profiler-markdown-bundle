<?php

declare(strict_types=1);

namespace Tourze\ProfilerMarkdownBundle\Tests\Formatter\Helper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\DataCollector\SecurityDataCollector;
use Symfony\Component\VarDumper\Cloner\Data;
use Tourze\ProfilerMarkdownBundle\Formatter\Helper\UserInfoFormatter;

/**
 * @internal
 */
#[CoversClass(UserInfoFormatter::class)]
final class UserInfoFormatterTest extends TestCase
{
    private UserInfoFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new UserInfoFormatter();
    }

    #[Test]
    public function testFormatWithUnauthenticatedUser(): void
    {
        $collector = $this->createMock(SecurityDataCollector::class);
        $collector->method('isAuthenticated')->willReturn(false);

        $result = $this->formatter->format($collector);

        $this->assertSame([], $result);
    }

    #[Test]
    public function testFormatWithAuthenticatedUserAndRoles(): void
    {
        $collector = $this->createMock(SecurityDataCollector::class);
        $collector->method('isAuthenticated')->willReturn(true);
        $collector->method('getUser')->willReturn('admin');

        $roles = ['ROLE_USER', 'ROLE_ADMIN'];
        $rolesData = $this->createMock(Data::class);
        $rolesData->method('getValue')->with(true)->willReturn($roles);
        $collector->method('getRoles')->willReturn($rolesData);

        $inheritedRoles = ['ROLE_USER', 'ROLE_ADMIN', 'ROLE_ALLOWED_TO_SWITCH'];
        $collector->method('getInheritedRoles')->willReturn($inheritedRoles);

        $result = $this->formatter->format($collector);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertStringContainsString('### User Information', $result[0]);

        $markdown = implode("\n", $result);
        $this->assertStringContainsString('admin', $markdown);
        $this->assertStringContainsString('`ROLE_USER`', $markdown);
        $this->assertStringContainsString('`ROLE_ADMIN`', $markdown);
        $this->assertStringContainsString('1 additional', $markdown); // 继承的角色
    }

    #[Test]
    public function testFormatWithAuthenticatedUserButNoRoles(): void
    {
        $collector = $this->createMock(SecurityDataCollector::class);
        $collector->method('isAuthenticated')->willReturn(true);
        $collector->method('getUser')->willReturn('user_no_roles');

        $emptyRolesData = $this->createMock(Data::class);
        $emptyRolesData->method('getValue')->with(true)->willReturn([]);
        $collector->method('getRoles')->willReturn($emptyRolesData);

        $collector->method('getInheritedRoles')->willReturn([]);

        $result = $this->formatter->format($collector);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        $markdown = implode("\n", $result);
        $this->assertStringContainsString('user_no_roles', $markdown);
        $this->assertStringNotContainsString('Roles', $markdown);
        $this->assertStringNotContainsString('Inherited Roles', $markdown);
    }

    #[Test]
    public function testFormatWithNullRolesData(): void
    {
        $collector = $this->createMock(SecurityDataCollector::class);
        $collector->method('isAuthenticated')->willReturn(true);
        $collector->method('getUser')->willReturn('user_null_roles');

        $nullRolesData = $this->createMock(Data::class);
        $nullRolesData->method('getValue')->with(true)->willReturn(null);
        $collector->method('getRoles')->willReturn($nullRolesData);

        $collector->method('getInheritedRoles')->willReturn([]);

        $result = $this->formatter->format($collector);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        $markdown = implode("\n", $result);
        $this->assertStringContainsString('user_null_roles', $markdown);
        $this->assertStringNotContainsString('Roles', $markdown);
    }

    #[Test]
    public function testFormatWithDirectArrayRoles(): void
    {
        $collector = $this->createMock(SecurityDataCollector::class);
        $collector->method('isAuthenticated')->willReturn(true);
        $collector->method('getUser')->willReturn('direct_user');

        // 直接返回数组而不是Data对象
        $roles = ['ROLE_EDITOR', 'ROLE_WRITER'];
        $collector->method('getRoles')->willReturn($roles);

        $collector->method('getInheritedRoles')->willReturn(['ROLE_EDITOR', 'ROLE_WRITER']);

        $result = $this->formatter->format($collector);

        $markdown = implode("\n", $result);
        $this->assertStringContainsString('direct_user', $markdown);
        $this->assertStringContainsString('`ROLE_EDITOR`', $markdown);
        $this->assertStringContainsString('`ROLE_WRITER`', $markdown);
        $this->assertStringNotContainsString('Inherited Roles', $markdown); // 继承角色数量与直接角色相同
    }

    #[Test]
    public function testFormatWithManyInheritedRoles(): void
    {
        $collector = $this->createMock(SecurityDataCollector::class);
        $collector->method('isAuthenticated')->willReturn(true);
        $collector->method('getUser')->willReturn('super_admin');

        $directRoles = ['ROLE_SUPER_ADMIN'];
        $rolesData = $this->createMock(Data::class);
        $rolesData->method('getValue')->with(true)->willReturn($directRoles);
        $collector->method('getRoles')->willReturn($rolesData);

        $inheritedRoles = [
            'ROLE_SUPER_ADMIN',
            'ROLE_ADMIN',
            'ROLE_USER',
            'ROLE_ALLOWED_TO_SWITCH',
            'ROLE_PREVIOUS_ADMIN',
        ];
        $collector->method('getInheritedRoles')->willReturn($inheritedRoles);

        $result = $this->formatter->format($collector);

        $markdown = implode("\n", $result);
        $this->assertStringContainsString('super_admin', $markdown);
        $this->assertStringContainsString('`ROLE_SUPER_ADMIN`', $markdown);
        $this->assertStringContainsString('4 additional', $markdown); // 5 - 1 = 4 继承角色
    }

    #[Test]
    public function testFormatWithLessInheritedThanDirect(): void
    {
        $collector = $this->createMock(SecurityDataCollector::class);
        $collector->method('isAuthenticated')->willReturn(true);
        $collector->method('getUser')->willReturn('weird_user');

        $directRoles = ['ROLE_A', 'ROLE_B', 'ROLE_C'];
        $rolesData = $this->createMock(Data::class);
        $rolesData->method('getValue')->with(true)->willReturn($directRoles);
        $collector->method('getRoles')->willReturn($rolesData);

        // 继承角色比直接角色少（不应该发生，但要处理这种情况）
        $inheritedRoles = ['ROLE_A'];
        $collector->method('getInheritedRoles')->willReturn($inheritedRoles);

        $result = $this->formatter->format($collector);

        $markdown = implode("\n", $result);
        $this->assertStringContainsString('weird_user', $markdown);
        $this->assertStringNotContainsString('Inherited Roles', $markdown); // count(inherited) - count(direct) = 1 - 3 = -2, max(0, -2) = 0
    }

    #[Test]
    public function testFormatWithSpecialCharactersInUsername(): void
    {
        $collector = $this->createMock(SecurityDataCollector::class);
        $collector->method('isAuthenticated')->willReturn(true);
        $collector->method('getUser')->willReturn('user@example.com');

        $roles = ['ROLE_USER'];
        $rolesData = $this->createMock(Data::class);
        $rolesData->method('getValue')->with(true)->willReturn($roles);
        $collector->method('getRoles')->willReturn($rolesData);

        $collector->method('getInheritedRoles')->willReturn(['ROLE_USER']);

        $result = $this->formatter->format($collector);

        $markdown = implode("\n", $result);
        $this->assertStringContainsString('user@example.com', $markdown);
        $this->assertStringContainsString('`ROLE_USER`', $markdown);
    }

    #[Test]
    public function testFormatWithSingleRole(): void
    {
        $collector = $this->createMock(SecurityDataCollector::class);
        $collector->method('isAuthenticated')->willReturn(true);
        $collector->method('getUser')->willReturn('simple_user');

        $roles = ['ROLE_USER'];
        $rolesData = $this->createMock(Data::class);
        $rolesData->method('getValue')->with(true)->willReturn($roles);
        $collector->method('getRoles')->willReturn($rolesData);

        $collector->method('getInheritedRoles')->willReturn(['ROLE_USER']);

        $result = $this->formatter->format($collector);

        $markdown = implode("\n", $result);
        $this->assertStringContainsString('simple_user', $markdown);
        $this->assertStringContainsString('`ROLE_USER`', $markdown);
        $this->assertStringNotContainsString('Inherited Roles', $markdown);
    }

    #[Test]
    public function testFormatWithEmptyInheritedRoles(): void
    {
        $collector = $this->createMock(SecurityDataCollector::class);
        $collector->method('isAuthenticated')->willReturn(true);
        $collector->method('getUser')->willReturn('test_user');

        $roles = ['ROLE_TEST'];
        $rolesData = $this->createMock(Data::class);
        $rolesData->method('getValue')->with(true)->willReturn($roles);
        $collector->method('getRoles')->willReturn($rolesData);

        // 返回空数组，表示没有继承角色
        $collector->method('getInheritedRoles')->willReturn([]);

        $result = $this->formatter->format($collector);

        $markdown = implode("\n", $result);
        $this->assertStringContainsString('test_user', $markdown);
        $this->assertStringContainsString('`ROLE_TEST`', $markdown);
        $this->assertStringNotContainsString('Inherited Roles', $markdown);
    }
}
