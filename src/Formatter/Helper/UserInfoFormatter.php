<?php

declare(strict_types=1);

namespace Tourze\ProfilerMarkdownBundle\Formatter\Helper;

use Symfony\Bundle\SecurityBundle\DataCollector\SecurityDataCollector;
use Symfony\Component\VarDumper\Cloner\Data;

final class UserInfoFormatter
{
    use ValueConverterTrait;

    /**
     * 安全地从Data对象中提取值
     */
    private function getValue(mixed $data): mixed
    {
        return $data instanceof Data ? $data->getValue(true) : $data;
    }

    /**
     * @return array<int, string>
     */
    public function format(SecurityDataCollector $collector): array
    {
        if (!$collector->isAuthenticated()) {
            return [];
        }

        $markdown = [
            '### User Information',
            '',
            '| Property | Value |',
            '|----------|-------|',
            '| **Username** | ' . $collector->getUser() . ' |',
        ];

        $markdown = $this->addUserRoles($markdown, $collector);
        $markdown[] = '';

        return $markdown;
    }

    /**
     * @param array<int, string> $markdown
     * @return array<int, string>
     */
    private function addUserRoles(array $markdown, SecurityDataCollector $collector): array
    {
        $roles = $this->extractRoles($collector);
        if ([] === $roles) {
            return $markdown;
        }

        $rolesList = $this->buildRolesList($roles);
        $markdown[] = '| **Roles** | ' . $rolesList . ' |';

        return $this->addInheritedRolesInfo($markdown, $collector, $roles);
    }

    /**
     * @param array<int, string> $roles
     */
    private function buildRolesList(array $roles): string
    {
        return implode(', ', array_map(fn ($role) => '`' . $role . '`', $roles));
    }

    /**
     * @param array<int, string> $markdown
     * @param array<int, string> $roles
     * @return array<int, string>
     */
    private function addInheritedRolesInfo(array $markdown, SecurityDataCollector $collector, array $roles): array
    {
        $inheritedCount = $this->getInheritedRolesCount($collector, $roles);
        if ($inheritedCount > 0) {
            $markdown[] = '| **Inherited Roles** | ' . $inheritedCount . ' additional |';
        }

        return $markdown;
    }

    /**
     * @return array<int, string>
     */
    private function extractRoles(SecurityDataCollector $collector): array
    {
        $roles = $this->getValue($collector->getRoles());

        if (!is_array($roles)) {
            return [];
        }

        // 确保所有角色都是字符串类型，过滤掉非字符串类型的值
        $stringRoles = [];
        foreach ($roles as $role) {
            if (is_string($role)) {
                $stringRoles[] = $role;
            } elseif (is_scalar($role)) {
                $stringRoles[] = (string) $role;
            }
            // 忽略对象、数组等复杂类型
        }

        return $stringRoles;
    }

    /**
     * @param array<int, string> $directRoles
     */
    private function getInheritedRolesCount(SecurityDataCollector $collector, array $directRoles): int
    {
        $inheritedRoles = $collector->getInheritedRoles();
        if (!is_array($inheritedRoles)) {
            return 0;
        }

        return max(0, count($inheritedRoles) - count($directRoles));
    }
}
