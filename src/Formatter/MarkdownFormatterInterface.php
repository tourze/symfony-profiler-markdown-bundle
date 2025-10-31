<?php

namespace Tourze\ProfilerMarkdownBundle\Formatter;

use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;

interface MarkdownFormatterInterface
{
    public function supports(DataCollectorInterface $collector): bool;

    /**
     * @return array<int, string>
     */
    public function format(DataCollectorInterface $collector): array;

    public function getPriority(): int;
}
