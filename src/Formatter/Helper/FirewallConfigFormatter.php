<?php

declare(strict_types=1);

namespace Tourze\ProfilerMarkdownBundle\Formatter\Helper;

use Symfony\Component\VarDumper\Cloner\Data;

final class FirewallConfigFormatter
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
     * 确保数组键都是字符串类型
     *
     * @param array<mixed, mixed> $array
     * @return array<string, mixed>
     */
    private function ensureStringKeys(array $array): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $result[(string) $key] = $value;
        }
        return $result;
    }

    /**
     * @return array<int, string>
     */
    public function format(mixed $firewall): array
    {
        if (null === $firewall) {
            return [];
        }

        $markdown = [
            '### Firewall Configuration',
            '',
        ];

        $content = is_array($firewall)
            ? $this->formatFirewallArray($this->ensureStringKeys($firewall))
            : $this->formatFirewallScalar($firewall);

        return array_merge($markdown, $content);
    }

    /**
     * @return array<int, string>
     */
    private function formatFirewallScalar(mixed $firewall): array
    {
        $firewallValue = $this->getValue($firewall);
        if (is_array($firewallValue)) {
            $jsonContent = json_encode($firewallValue, JSON_PRETTY_PRINT);
            $content = '**Firewall:** ' . (false !== $jsonContent ? $jsonContent : 'Invalid JSON');
        } else {
            $content = '**Firewall:** ' . $this->convertToString($firewallValue);
        }

        return [$content, ''];
    }

    /**
     * @param array<string, mixed> $firewall
     * @return array<int, string>
     */
    private function formatFirewallArray(array $firewall): array
    {
        $markdown = [
            '| Property | Value |',
            '|----------|-------|',
        ];

        $markdown = array_merge($markdown, $this->formatFirewallProperties($firewall));
        $markdown[] = '';

        return $this->addAuthenticatorsSection($markdown, $firewall);
    }

    /**
     * @param array<string, mixed> $firewall
     * @return array<int, string>
     */
    private function formatFirewallProperties(array $firewall): array
    {
        $properties = [
            'name' => 'Name',
            'pattern' => 'Pattern',
            'context' => 'Context',
            'provider' => 'Provider',
            'entry_point' => 'Entry Point',
        ];

        $markdown = [];
        foreach ($properties as $key => $label) {
            if (isset($firewall[$key])) {
                $value = $this->formatFirewallPropertyValue($key, $firewall[$key]);
                $markdown[] = "| **{$label}** | {$value} |";
            }
        }

        return $this->addStatelessProperty($markdown, $firewall);
    }

    private function formatFirewallPropertyValue(string $key, mixed $value): string
    {
        return 'pattern' === $key ? '`' . $this->convertToString($value) . '`' : $this->convertToString($value);
    }

    /**
     * @param array<int, string> $markdown
     * @param array<string, mixed> $firewall
     * @return array<int, string>
     */
    private function addStatelessProperty(array $markdown, array $firewall): array
    {
        if (!isset($firewall['stateless'])) {
            return $markdown;
        }

        $stateless = (bool) $firewall['stateless'] ? 'Yes' : 'No';
        $markdown[] = "| **Stateless** | {$stateless} |";

        return $markdown;
    }

    /**
     * @param array<int, string> $markdown
     * @param array<string, mixed> $firewall
     * @return array<int, string>
     */
    private function addAuthenticatorsSection(array $markdown, array $firewall): array
    {
        if (!isset($firewall['authenticators']) || [] === $firewall['authenticators']) {
            return $markdown;
        }

        $authenticators = $firewall['authenticators'];
        if (!is_array($authenticators)) {
            return $markdown;
        }

        $stringAuthenticators = [];
        foreach ($authenticators as $auth) {
            $stringAuthenticators[] = $this->convertToString($auth);
        }

        return array_merge($markdown, $this->formatAuthenticators($stringAuthenticators));
    }

    /**
     * @param array<int, string> $authenticators
     * @return array<int, string>
     */
    private function formatAuthenticators(array $authenticators): array
    {
        $markdown = [
            '#### Authenticators',
            '',
        ];

        foreach ($authenticators as $authenticator) {
            $markdown[] = '- `' . $this->getShortClassName($authenticator) . '`';
        }
        $markdown[] = '';

        return $markdown;
    }
}
