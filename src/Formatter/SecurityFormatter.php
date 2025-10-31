<?php

declare(strict_types=1);

namespace Tourze\ProfilerMarkdownBundle\Formatter;

use Symfony\Bundle\SecurityBundle\DataCollector\SecurityDataCollector;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Tourze\ProfilerMarkdownBundle\Formatter\Helper\AccessDecisionFormatter;
use Tourze\ProfilerMarkdownBundle\Formatter\Helper\FirewallConfigFormatter;
use Tourze\ProfilerMarkdownBundle\Formatter\Helper\UserInfoFormatter;
use Tourze\ProfilerMarkdownBundle\Formatter\Helper\ValueConverterTrait;

class SecurityFormatter extends AbstractMarkdownFormatter
{
    use ValueConverterTrait;

    private readonly UserInfoFormatter $userInfoFormatter;

    private readonly FirewallConfigFormatter $firewallFormatter;

    private readonly AccessDecisionFormatter $accessDecisionFormatter;

    public function __construct()
    {
        $this->userInfoFormatter = new UserInfoFormatter();
        $this->firewallFormatter = new FirewallConfigFormatter();
        $this->accessDecisionFormatter = new AccessDecisionFormatter();
    }

    public function supports(DataCollectorInterface $collector): bool
    {
        return $collector instanceof SecurityDataCollector;
    }

    /**
     * @return array<int, string>
     */
    public function format(DataCollectorInterface $collector): array
    {
        if (!$collector instanceof SecurityDataCollector) {
            return [];
        }

        $markdown = [
            '## 🔒 Security',
            '',
        ];

        if (!$collector->isEnabled()) {
            return array_merge($markdown, ['_Security is not enabled for this request_', '']);
        }

        $sections = [
            $this->formatAuthenticationStatus($collector),
            $this->userInfoFormatter->format($collector),
            $this->firewallFormatter->format($collector->getFirewall()),
            $this->accessDecisionFormatter->format($collector),
            $this->formatSecurityListeners($collector),
        ];

        foreach ($sections as $section) {
            $markdown = array_merge($markdown, $section);
        }

        return $markdown;
    }

    /**
     * @return array<int, string>
     */
    private function formatAuthenticationStatus(SecurityDataCollector $collector): array
    {
        $markdown = [
            '### Authentication Status',
            '',
            '| Property | Value |',
            '|----------|-------|',
            '| **Enabled** | ✅ Yes |',
            '| **Authenticated** | ' . ($collector->isAuthenticated() ? '✅ Yes' : '❌ No') . ' |',
            '| **Impersonated** | ' . ($collector->isImpersonated() ? '⚠️ Yes' : 'No') . ' |',
        ];

        $tokenClass = $collector->getTokenClass();
        if (is_string($tokenClass) && '' !== $tokenClass) {
            $markdown[] = '| **Token Type** | `' . $this->getShortClassName($tokenClass) . '` |';
        }

        $markdown[] = '';

        return $markdown;
    }

    /**
     * @return array<int, string>
     */
    private function formatSecurityListeners(SecurityDataCollector $collector): array
    {
        $listeners = $collector->getListeners();
        if ([] === $listeners) {
            return [];
        }

        $markdown = [
            '### Security Event Listeners',
            '',
        ];

        foreach ($listeners as $event => $eventListeners) {
            $markdown[] = '**' . $this->convertToString($event) . '**';
            foreach ($eventListeners as $listener) {
                $shortName = $this->formatListenerName($listener);
                $markdown[] = '- ' . $shortName;
            }
        }

        $markdown[] = '';

        return $markdown;
    }

    private function formatListenerName(mixed $listener): string
    {
        if (!is_array($listener) || !isset($listener[0]) || !is_object($listener[0])) {
            return $this->convertToString($listener);
        }

        $className = $this->getShortClassName(get_class($listener[0]));
        $method = isset($listener[1]) ? $this->convertToString($listener[1]) : 'unknown';

        return $className . '::' . $method;
    }

    public function getPriority(): int
    {
        return 70;
    }
}
