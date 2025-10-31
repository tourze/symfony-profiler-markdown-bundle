<?php

declare(strict_types=1);

namespace Tourze\ProfilerMarkdownBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;
use Tourze\ProfilerMarkdownBundle\ProfilerMarkdownBundle;

/**
 * @internal
 */
#[CoversClass(ProfilerMarkdownBundle::class)]
#[RunTestsInSeparateProcesses]
final class ProfilerMarkdownBundleTest extends AbstractBundleTestCase
{
}
