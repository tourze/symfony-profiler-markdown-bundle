<?php

namespace Tourze\ProfilerMarkdownBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Routing\Attribute\Route;
use Tourze\ProfilerMarkdownBundle\Formatter\FormatterRegistry;

final class ProfilerMarkdownController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'profiler')]
        private ?Profiler $profiler = null,
        private ?FormatterRegistry $formatterRegistry = null,
    ) {
    }

    #[Route(path: '/_profiler/{token}.md', name: 'profiler_markdown', methods: ['GET'], priority: 10)]
    public function __invoke(string $token): Response
    {
        if (null === $this->profiler) {
            return new Response('# Profiler Not Available

The profiler is not enabled in this environment.', 404, ['Content-Type' => 'text/plain']);
        }

        $profile = $this->profiler->loadProfile($token);

        if (null === $profile) {
            return new Response("# Profile Not Found

No profile found for token: `{$token}`", 404, ['Content-Type' => 'text/plain']);
        }

        $markdown = $this->generateMarkdown($profile);

        return new Response($markdown, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    private function generateMarkdown(Profile $profile): string
    {
        $collectors = $profile->getCollectors();
        $markdown = [];

        $markdown = array_merge($markdown, $this->generateHeader($profile));
        $markdown = array_merge($markdown, $this->generateTableOfContents($collectors));
        $markdown = array_merge($markdown, $this->generateCollectorSections($collectors));
        $markdown = array_merge($markdown, $this->generateFooter());

        return implode("\n", $markdown);
    }

    /**
     * @return array<int, string>
     */
    private function generateHeader(Profile $profile): array
    {
        $markdown = [
            '# Symfony Profiler Report',
            '',
            '---',
            '',
            '## 📊 Summary',
            '',
            '| Property | Value |',
            '|----------|-------|',
            "| **Token** | `{$profile->getToken()}` |",
            "| **URL** | {$profile->getUrl()} |",
            "| **Method** | {$profile->getMethod()} |",
            "| **Status** | {$profile->getStatusCode()} |",
            '| **Time** | ' . date('Y-m-d H:i:s', $profile->getTime()) . ' |',
            "| **IP** | {$profile->getIp()} |",
        ];

        $parent = $profile->getParent();
        if (null !== $parent) {
            $parentToken = $parent->getToken();
            $markdown[] = "| **Parent Token** | `{$parentToken}` |";
        }

        if (count($profile->getChildren()) > 0) {
            $markdown[] = '| **Sub-Requests** | ' . count($profile->getChildren()) . ' |';
        }

        $markdown[] = '';
        $markdown[] = '---';
        $markdown[] = '';

        return $markdown;
    }

    /**
     * @param array<string, DataCollectorInterface> $collectors
     * @return array<int, string>
     */
    private function generateTableOfContents(array $collectors): array
    {
        $markdown = [
            '## 📑 Table of Contents',
            '',
        ];

        $collectorNames = array_keys($collectors);
        $sections = [];

        foreach ($collectorNames as $name) {
            $displayName = $this->getCollectorDisplayName($name);
            $sections[] = "- [{$displayName}](#{$name})";
        }

        $markdown[] = implode("\n", $sections);
        $markdown[] = '';
        $markdown[] = '---';
        $markdown[] = '';

        return $markdown;
    }

    /**
     * @param array<string, DataCollectorInterface> $collectors
     * @return array<int, string>
     */
    private function generateCollectorSections(array $collectors): array
    {
        if (null !== $this->formatterRegistry) {
            return $this->generateFormattedCollectorSections($collectors);
        }

        return $this->generateFallbackCollectorSections($collectors);
    }

    /**
     * @param array<string, DataCollectorInterface> $collectors
     * @return array<int, string>
     */
    private function generateFormattedCollectorSections(array $collectors): array
    {
        $markdown = [];

        foreach ($collectors as $name => $collector) {
            $formattedData = $this->formatterRegistry?->format($name, $collector) ?? [];
            if ([] !== $formattedData) {
                $markdown = array_merge($markdown, $formattedData);
                $markdown[] = '';
                $markdown[] = '---';
                $markdown[] = '';
            }
        }

        return $markdown;
    }

    /**
     * @param array<string, DataCollectorInterface> $collectors
     * @return array<int, string>
     */
    private function generateFallbackCollectorSections(array $collectors): array
    {
        $markdown = [
            '## ⚠️ Formatter Registry Not Available',
            '',
            'The advanced formatting system is not configured. Showing basic collector information:',
            '',
        ];

        foreach ($collectors as $name => $collector) {
            $markdown[] = "### {$name}";
            $markdown[] = '';
            $markdown[] = '```';
            $markdown[] = get_class($collector);
            $markdown[] = '```';
            $markdown[] = '';
        }

        return $markdown;
    }

    /**
     * @return array<int, string>
     */
    private function generateFooter(): array
    {
        return [
            '---',
            '',
            '_Generated at ' . date('Y-m-d H:i:s') . '_',
            '',
        ];
    }

    private function getCollectorDisplayName(string $name): string
    {
        $displayNames = [
            'request' => '📨 Request',
            'time' => '⏱️ Performance',
            'memory' => '💾 Memory',
            'ajax' => '🔄 AJAX',
            'form' => '📝 Forms',
            'exception' => '❌ Exception',
            'logger' => '📋 Logs',
            'events' => '📡 Events',
            'router' => '🛤️ Routing',
            'cache' => '💽 Cache',
            'translation' => '🌍 Translation',
            'security' => '🔒 Security',
            'twig' => '🎨 Templates',
            'http_client' => '🌐 HTTP Client',
            'db' => '🗄️ Database',
            'doctrine' => '🗄️ Doctrine',
            'config' => '⚙️ Configuration',
            'validator' => '✅ Validation',
            'messenger' => '📬 Messenger',
            'mailer' => '📧 Mailer',
            'notifier' => '🔔 Notifier',
        ];

        return $displayNames[$name] ?? ucfirst($name);
    }
}
