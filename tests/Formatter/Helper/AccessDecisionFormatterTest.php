<?php

declare(strict_types=1);

namespace Tourze\ProfilerMarkdownBundle\Tests\Formatter\Helper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\DataCollector\SecurityDataCollector;
use Symfony\Component\VarDumper\Cloner\Data;
use Tourze\ProfilerMarkdownBundle\Formatter\Helper\AccessDecisionFormatter;

/**
 * @internal
 */
#[CoversClass(AccessDecisionFormatter::class)]
final class AccessDecisionFormatterTest extends TestCase
{
    private AccessDecisionFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new AccessDecisionFormatter();
    }

    #[Test]
    public function testFormatWithEmptyAccessLog(): void
    {
        $collector = $this->createMock(SecurityDataCollector::class);

        $emptyData = $this->createMock(Data::class);
        $emptyData->method('getValue')->with(true)->willReturn([]);
        $collector->method('getAccessDecisionLog')->willReturn($emptyData);

        $result = $this->formatter->format($collector);

        $this->assertSame([], $result);
    }

    #[Test]
    public function testFormatWithNullAccessLog(): void
    {
        $collector = $this->createMock(SecurityDataCollector::class);

        $nullData = $this->createMock(Data::class);
        $nullData->method('getValue')->with(true)->willReturn(null);
        $collector->method('getAccessDecisionLog')->willReturn($nullData);

        $result = $this->formatter->format($collector);

        $this->assertSame([], $result);
    }

    #[Test]
    public function testFormatWithValidAccessDecisions(): void
    {
        $collector = $this->createMock(SecurityDataCollector::class);

        $accessLog = [
            [
                'result' => true,
                'object' => 'App\Entity\User',
                'attributes' => ['ROLE_ADMIN'],
                'voter_details' => [
                    ['class' => 'Symfony\Component\Security\Core\Authorization\Voter\RoleVoter', 'vote' => 1],
                ],
            ],
            [
                'result' => null,
                'object' => 'App\Entity\Post',
                'attributes' => ['ROLE_MODERATOR'],
                'voter_details' => [
                    ['class' => 'App\Security\PostVoter', 'vote' => -1],
                ],
            ],
        ];

        $accessLogData = $this->createMock(Data::class);
        $accessLogData->method('getValue')->with(true)->willReturn($accessLog);
        $collector->method('getAccessDecisionLog')->willReturn($accessLogData);

        $result = $this->formatter->format($collector);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertStringContainsString('### Access Control Decisions', $result[0]);
        $this->assertStringContainsString('| Resource | Attributes | Result | Voter Decisions |', $result[2]);

        $markdown = implode("\n", $result);
        $this->assertStringContainsString('✅ Granted', $markdown);
        $this->assertStringContainsString('❌ Denied', $markdown);
        $this->assertStringContainsString('ROLE_ADMIN', $markdown);
        $this->assertStringContainsString('RoleVoter', $markdown);
    }

    #[Test]
    public function testFormatWithComplexObjectInDecision(): void
    {
        $collector = $this->createMock(SecurityDataCollector::class);

        $objectData = $this->createMock(Data::class);
        $objectData->method('getValue')->with(true)->willReturn(['key' => 'value', 'type' => 'array']);

        $accessLog = [
            [
                'result' => true,
                'object' => $objectData,
                'attributes' => ['VIEW'],
                'voter_details' => [],
            ],
        ];

        $accessLogData = $this->createMock(Data::class);
        $accessLogData->method('getValue')->with(true)->willReturn($accessLog);
        $collector->method('getAccessDecisionLog')->willReturn($accessLogData);

        $result = $this->formatter->format($collector);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        $markdown = implode("\n", $result);
        $this->assertStringContainsString('✅ Granted', $markdown);
        $this->assertStringContainsString('VIEW', $markdown);
    }

    #[Test]
    public function testFormatWithManyDecisions(): void
    {
        $collector = $this->createMock(SecurityDataCollector::class);

        // 创建12个决策，应该只显示前10个
        $accessLog = [];
        for ($i = 1; $i <= 12; ++$i) {
            $accessLog[] = [
                'result' => 0 === $i % 2,
                'object' => "Resource{$i}",
                'attributes' => ["ROLE_{$i}"],
                'voter_details' => [],
            ];
        }

        $accessLogData = $this->createMock(Data::class);
        $accessLogData->method('getValue')->with(true)->willReturn($accessLog);
        $collector->method('getAccessDecisionLog')->willReturn($accessLogData);

        $result = $this->formatter->format($collector);

        $this->assertIsArray($result);
        $markdown = implode("\n", $result);

        // 应该包含"2 more"的提示
        $this->assertStringContainsString('... and 2 more access decisions', $markdown);
    }

    #[Test]
    public function testFormatWithVoterDetails(): void
    {
        $collector = $this->createMock(SecurityDataCollector::class);

        $voterDetailsData = $this->createMock(Data::class);
        $voterDetailsData->method('getValue')->with(true)->willReturn([
            ['class' => 'RoleVoter', 'vote' => 1],
            ['class' => 'CustomVoter', 'vote' => -1],
            ['class' => 'AbstractVoter', 'vote' => 0],
        ]);

        $accessLog = [
            [
                'result' => true,
                'object' => 'TestObject',
                'attributes' => ['TEST'],
                'voter_details' => $voterDetailsData,
            ],
        ];

        $accessLogData = $this->createMock(Data::class);
        $accessLogData->method('getValue')->with(true)->willReturn($accessLog);
        $collector->method('getAccessDecisionLog')->willReturn($accessLogData);

        $result = $this->formatter->format($collector);

        $markdown = implode("\n", $result);
        $this->assertStringContainsString('RoleVoter: ✅ Grant', $markdown);
        $this->assertStringContainsString('CustomVoter: ❌ Deny', $markdown);
        $this->assertStringContainsString('AbstractVoter: ⏭️ Abstain', $markdown);
    }

    #[Test]
    public function testFormatWithMissingObjectAndAttributes(): void
    {
        $collector = $this->createMock(SecurityDataCollector::class);

        $accessLog = [
            [
                'result' => null,
                'voter_details' => [],
            ],
        ];

        $accessLogData = $this->createMock(Data::class);
        $accessLogData->method('getValue')->with(true)->willReturn($accessLog);
        $collector->method('getAccessDecisionLog')->willReturn($accessLogData);

        $result = $this->formatter->format($collector);

        $markdown = implode("\n", $result);
        $this->assertStringContainsString('❌ Denied', $markdown);
        $this->assertStringContainsString('N/A', $markdown);
    }

    #[Test]
    public function testFormatWithDirectArrayAsAccessLog(): void
    {
        $collector = $this->createMock(SecurityDataCollector::class);

        // 直接返回数组而不是Data对象
        $accessLog = [
            [
                'result' => true,
                'object' => 'DirectArray',
                'attributes' => ['DIRECT'],
                'voter_details' => [],
            ],
        ];

        $collector->method('getAccessDecisionLog')->willReturn($accessLog);

        $result = $this->formatter->format($collector);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        $markdown = implode("\n", $result);
        $this->assertStringContainsString('✅ Granted', $markdown);
        $this->assertStringContainsString('DirectArray', $markdown);
    }

    #[Test]
    public function testFormatWithInvalidVoterDetails(): void
    {
        $collector = $this->createMock(SecurityDataCollector::class);

        $accessLog = [
            [
                'result' => true,
                'object' => 'TestObject',
                'attributes' => ['TEST'],
                'voter_details' => 'invalid_string',
            ],
        ];

        $accessLogData = $this->createMock(Data::class);
        $accessLogData->method('getValue')->with(true)->willReturn($accessLog);
        $collector->method('getAccessDecisionLog')->willReturn($accessLogData);

        $result = $this->formatter->format($collector);

        $this->assertIsArray($result);
        $markdown = implode("\n", $result);
        $this->assertStringContainsString('✅ Granted', $markdown);
        // 不应该有voter信息，因为格式无效
    }
}
