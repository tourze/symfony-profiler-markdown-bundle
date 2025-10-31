<?php

namespace Tourze\ProfilerMarkdownBundle\Formatter;

use Symfony\Bridge\Twig\DataCollector\TwigDataCollector;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;

class TwigFormatter extends AbstractMarkdownFormatter
{
    public function supports(DataCollectorInterface $collector): bool
    {
        return $collector instanceof TwigDataCollector;
    }

    /**
     * @return array<int, string>
     */
    public function format(DataCollectorInterface $collector): array
    {
        if (!$collector instanceof TwigDataCollector) {
            return [];
        }

        $markdown = [];
        $markdown[] = '## 🎨 Templates (Twig)';
        $markdown[] = '';

        // Summary
        $markdown[] = '### Rendering Summary';
        $markdown[] = '';
        $markdown[] = '| Metric | Value |';
        $markdown[] = '|--------|-------|';
        $markdown[] = '| **Render Time** | ' . number_format($collector->getTime() * 1000, 2) . ' ms |';
        $markdown[] = '| **Template Count** | ' . $collector->getTemplateCount() . ' |';
        $markdown[] = '| **Block Count** | ' . $collector->getBlockCount() . ' |';
        $markdown[] = '| **Macro Count** | ' . $collector->getMacroCount() . ' |';

        $markdown[] = '| **Call Graph Available** | ✅ |';

        $markdown[] = '';

        // Templates
        $templates = $collector->getTemplates();
        if ([] !== $templates) {
            $markdown[] = '### Rendered Templates';
            $markdown[] = '';

            // Sort by render count
            arsort($templates);

            $markdown[] = '| Template | Render Count | Type |';
            $markdown[] = '|----------|--------------|------|';

            foreach (array_slice($templates, 0, 20, true) as $template => $count) {
                $type = $this->getTemplateType($template);
                $shortName = $this->shortenTemplateName($template);
                $markdown[] = '| `' . $shortName . '` | ' . $count . ' | ' . $type . ' |';
            }

            if (count($templates) > 20) {
                $markdown[] = '';
                $markdown[] = '_... and ' . (count($templates) - 20) . ' more templates_';
            }

            $markdown[] = '';

            // Template hierarchy
            $markdown = $this->addTemplateHierarchy($markdown, $templates);
        }

        // Note: getComputedData() is a private method with required parameter
        // Skipping computed data extraction for now

        return $markdown;
    }

    private function getTemplateType(string $template): string
    {
        if (0 === strpos($template, '@EasyAdmin')) {
            return '🛠️ Admin';
        }
        if (0 === strpos($template, '@')) {
            return '📦 Bundle';
        }
        if (false !== strpos($template, 'form/')) {
            return '📝 Form';
        }
        if (false !== strpos($template, 'email/')) {
            return '📧 Email';
        }
        if (false !== strpos($template, 'component/')) {
            return '🧩 Component';
        }

        return '📄 App';
    }

    private function shortenTemplateName(string $template): string
    {
        if (strlen($template) > 50) {
            if (false !== strpos($template, '/')) {
                $parts = explode('/', $template);

                return '.../' . implode('/', array_slice($parts, -2));
            }

            return substr($template, 0, 47) . '...';
        }

        return $template;
    }

    /**
     * @param array<int, string> $markdown
     * @param array<string, int> $templates
     * @return array<int, string>
     */
    private function addTemplateHierarchy(array $markdown, array $templates): array
    {
        $categorized = $this->categorizeTemplates($templates);

        if ($this->hasAnyTemplates($categorized)) {
            $hierarchySection = $this->formatTemplateOrganization($categorized);

            return array_merge($markdown, $hierarchySection);
        }

        return $markdown;
    }

    /**
     * @param array<string, int> $templates
     * @return array{bundles: array<string, array<int, string>>, forms: array<int, string>, components: array<int, string>, app: array<int, string>}
     */
    private function categorizeTemplates(array $templates): array
    {
        $categorized = [
            'bundles' => [],
            'forms' => [],
            'components' => [],
            'app' => [],
        ];

        foreach (array_keys($templates) as $template) {
            $categorized = $this->categorizeTemplate($template, $categorized);
        }

        return $categorized;
    }

    /**
     * @param array{bundles: array<string, array<int, string>>, forms: array<int, string>, components: array<int, string>, app: array<int, string>} $categorized
     * @return array{bundles: array<string, array<int, string>>, forms: array<int, string>, components: array<int, string>, app: array<int, string>}
     */
    private function categorizeTemplate(string $template, array $categorized): array
    {
        if (str_starts_with($template, '@EasyAdmin')) {
            $categorized['bundles']['EasyAdmin'][] = $template;
        } elseif (str_starts_with($template, '@')) {
            $slashPos = strpos($template, '/');
            $bundleName = false !== $slashPos ? substr($template, 1, $slashPos - 1) : substr($template, 1);
            $categorized['bundles'][$bundleName][] = $template;
        } elseif (str_contains($template, 'form/')) {
            $categorized['forms'][] = $template;
        } elseif (str_contains($template, 'component/')) {
            $categorized['components'][] = $template;
        } else {
            $categorized['app'][] = $template;
        }

        return $categorized;
    }

    /**
     * @param array{bundles: array<string, array<int, string>>, forms: array<int, string>, components: array<int, string>, app: array<int, string>} $categorized
     */
    private function hasAnyTemplates(array $categorized): bool
    {
        return [] !== $categorized['bundles']
               || [] !== $categorized['forms']
               || [] !== $categorized['components']
               || [] !== $categorized['app'];
    }

    /**
     * @param array{bundles: array<string, array<int, string>>, forms: array<int, string>, components: array<int, string>, app: array<int, string>} $categorized
     * @return array<int, string>
     */
    private function formatTemplateOrganization(array $categorized): array
    {
        $markdown = [
            '### Template Organization',
            '',
        ];

        if ([] !== $categorized['app']) {
            $markdown[] = '**📄 Application Templates:** ' . count($categorized['app']);
        }
        if ([] !== $categorized['forms']) {
            $markdown[] = '**📝 Form Templates:** ' . count($categorized['forms']);
        }
        if ([] !== $categorized['components']) {
            $markdown[] = '**🧩 Component Templates:** ' . count($categorized['components']);
        }
        if ([] !== $categorized['bundles']) {
            $totalBundleTemplates = array_sum(array_map('count', $categorized['bundles']));
            $bundleCount = count($categorized['bundles']);
            $markdown[] = "**📦 Bundle Templates:** {$totalBundleTemplates} across {$bundleCount} bundles";
        }

        $markdown[] = '';

        return $markdown;
    }

    public function getPriority(): int
    {
        return 40;
    }
}
