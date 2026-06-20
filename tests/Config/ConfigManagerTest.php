<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests\Config;

use Mariusz\LogViewer\Config\ConfigManager;
use PHPUnit\Framework\TestCase;

class ConfigManagerTest extends TestCase
{
    private ConfigManager $configManager;
    private string $tempConfig;
    private string $tempEnv;

    protected function setUp(): void
    {
        $this->tempConfig = sys_get_temp_dir() . '/config_' . bin2hex(random_bytes(8)) . '.json';
        $this->tempEnv = sys_get_temp_dir() . '/env_' . bin2hex(random_bytes(8));
        $this->configManager = new ConfigManager($this->tempConfig, $this->tempEnv);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempConfig)) {
            @unlink($this->tempConfig);
        }
        if (file_exists($this->tempEnv)) {
            @unlink($this->tempEnv);
        }
    }

    public function testIsSetupCompleteReturnsFalseIfFileDoesNotExist(): void
    {
        $this->assertFalse($this->configManager->isSetupComplete());
    }

    public function testIsSetupCompleteReturnsFalseIfJsonInvalid(): void
    {
        file_put_contents($this->tempConfig, '{ invalid json ]');
        $this->assertFalse($this->configManager->isSetupComplete());
    }

    public function testIsSetupCompleteReturnsTrueOnlyIfSetupCompleteIsTrue(): void
    {
        $this->configManager->saveConfig(['setup_complete' => false]);
        $this->assertFalse($this->configManager->isSetupComplete());

        $this->configManager->saveConfig(['setup_complete' => true]);
        $this->assertTrue($this->configManager->isSetupComplete());
    }

    public function testSaveConfigWritesAtomicAndSetsPermissions(): void
    {
        $config = ['test' => 'value'];
        $this->configManager->saveConfig($config);
        
        $this->assertFileExists($this->tempConfig);
        $this->assertEquals($config, json_decode(file_get_contents($this->tempConfig), true));
        
        // Check permissions (0600)
        $this->assertEquals('0600', substr(sprintf('%o', fileperms($this->tempConfig)), -4));
    }

    public function testUpdateConfigMergesDataRecursive(): void
    {
        $this->configManager->saveConfig([
            'a' => ['b' => 1],
            'c' => 2
        ]);

        $this->configManager->updateConfig([
            'a' => ['d' => 3],
            'c' => 4
        ]);

        $expected = [
            'a' => ['b' => 1, 'd' => 3],
            'c' => 4
        ];
        $this->assertEquals($expected, $this->configManager->getConfig());
    }

    public function testGetPublicConfigFiltersSensitiveFields(): void
    {
        $config = [
            'ssh_password' => 'secret',
            'ssh_user' => 'admin',
            'nested' => [
                'encryption_key' => 'very-secret'
            ]
        ];
        $this->configManager->saveConfig($config);

        $public = $this->configManager->getPublicConfig();

        $this->assertEquals('********', $public['ssh_password']);
        $this->assertEquals('admin', $public['ssh_user']);
        $this->assertEquals('********', $public['nested']['encryption_key']);
    }

    public function testGetConfigReturnsEmptyArrayOnError(): void
    {
        file_put_contents($this->tempConfig, '{ invalid }');
        $this->assertEquals([], $this->configManager->getConfig());
    }
}
