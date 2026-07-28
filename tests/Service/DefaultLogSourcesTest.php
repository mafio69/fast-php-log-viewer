<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests\Service;

use Mariusz\LogViewer\Service\DefaultLogSources;
use PHPUnit\Framework\TestCase;

final class DefaultLogSourcesTest extends TestCase
{
    public function testThreeDefaultPaths(): void
    {
        $this->assertSame(
            ['/var/log', '~/logs', './logs'],
            DefaultLogSources::DEFAULTS,
        );
    }

    public function testDefaultsDoesNotIncludeTmp(): void
    {
        $this->assertNotContains('/tmp', DefaultLogSources::DEFAULTS);
    }

    public function testDefaultsDoesNotIncludeVarWwwHtmlLogs(): void
    {
        $this->assertNotContains('/var/www/html/logs', DefaultLogSources::DEFAULTS);
    }

    public function testDefaultsDoesNotIncludeParentLogs(): void
    {
        $this->assertNotContains('../logs', DefaultLogSources::DEFAULTS);
    }

    public function testPresetsReturnsNginxApache2PhpFpm(): void
    {
        $presets = DefaultLogSources::presets();

        $this->assertSame(
            ['/var/log/nginx', '/var/log/apache2', '/var/log/php-fpm'],
            $presets,
        );
    }

    public function testPresetsDoNotLeakIntoDefaults(): void
    {
        foreach (DefaultLogSources::presets() as $preset) {
            $this->assertNotContains($preset, DefaultLogSources::DEFAULTS);
        }
    }
}
