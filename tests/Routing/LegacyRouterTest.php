<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests\Routing;

use Mariusz\LogViewer\Routing\LegacyRouter;
use PHPUnit\Framework\TestCase;

class LegacyRouterTest extends TestCase
{
    private const EXPECTED_MAP = [
        'directories'        => '/api/directories',
        'files'              => '/api/files',
        'entries'            => '/api/entries',
        'config-add-dir'     => '/api/config/directories',
        'ssh-test-connection'=> '/api/ssh/test-connection',
        'ssh-list-files'     => '/api/ssh/list-files',
        'ssh-read-file'      => '/api/ssh/read-file',
        'ssh-download-file'  => '/api/ssh/download-file',
        'setup-status'       => '/api/setup/status',
        'setup-step'         => '/api/setup/step',
        'setup-migrate-ssh'  => '/api/setup/migrate-ssh',
        'app-config'         => '/api/app-config',
    ];

    public function testActionMapContainsAllExpectedEntries(): void
    {
        $this->assertSame(self::EXPECTED_MAP, LegacyRouter::getActionMap());
    }

    public function testHasActionReturnsTrueForAllMappedActions(): void
    {
        foreach (self::EXPECTED_MAP as $action => $expectedPath) {
            $this->assertTrue(LegacyRouter::hasAction($action), "Action '$action' should be recognized");
        }
    }

    public function testHasActionReturnsFalseForUnknownAction(): void
    {
        $this->assertFalse(LegacyRouter::hasAction('nonexistent'));
        $this->assertFalse(LegacyRouter::hasAction(''));
        $this->assertFalse(LegacyRouter::hasAction('config-update-dir'));
    }

    /** @dataProvider provideRewriteScenarios */
    public function testRewriteRequestUri(string $action, array $queryParams, string $expected): void
    {
        $this->assertSame($expected, LegacyRouter::rewriteRequestUri($action, $queryParams));
    }

    public static function provideRewriteScenarios(): array
    {
        return [
            'simple action without params' => [
                'directories',
                ['action' => 'directories'],
                '/api/directories',
            ],
            'action with additional params' => [
                'files',
                ['action' => 'files', 'dir' => 'test'],
                '/api/files?dir=test',
            ],
            'action with multiple params' => [
                'entries',
                ['action' => 'entries', 'file' => '/var/log/test.log', 'level' => 'ERROR'],
                '/api/entries?file=%2Fvar%2Flog%2Ftest.log&level=ERROR',
            ],
            'ssh config add dir with name' => [
                'config-add-dir',
                ['action' => 'config-add-dir', 'name' => 'my_dir', 'path' => '/var/log'],
                '/api/config/directories?name=my_dir&path=%2Fvar%2Flog',
            ],
            'ssh test connection' => [
                'ssh-test-connection',
                ['action' => 'ssh-test-connection', 'host' => 'example.com'],
                '/api/ssh/test-connection?host=example.com',
            ],
            'setup step with data' => [
                'setup-step',
                ['action' => 'setup-step', 'step' => 'generate_keys'],
                '/api/setup/step?step=generate_keys',
            ],
        ];
    }

    public function testActionMapCount(): void
    {
        $this->assertCount(12, LegacyRouter::getActionMap());
    }

    public function testAllActionsAreStrings(): void
    {
        foreach (LegacyRouter::getActionMap() as $action => $path) {
            $this->assertIsString($action);
            $this->assertIsString($path);
            $this->assertNotEmpty($action);
            $this->assertNotEmpty($path);
        }
    }

    public function testAllPathsStartWithApiPrefix(): void
    {
        foreach (LegacyRouter::getActionMap() as $path) {
            $this->assertStringStartsWith('/api/', $path, "Path '$path' should start with /api/");
        }
    }
}
