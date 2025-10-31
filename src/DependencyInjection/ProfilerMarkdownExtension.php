<?php

namespace Tourze\ProfilerMarkdownBundle\DependencyInjection;

use Tourze\SymfonyDependencyServiceLoader\AutoExtension;

class ProfilerMarkdownExtension extends AutoExtension
{
    protected function getConfigDir(): string
    {
        return __DIR__ . '/../Resources/config';
    }
}
