<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests\Service;

use Mariusz\LogViewer\Config\ConfigManager;
use Mariusz\LogViewer\Config\LogConfig;
use Mariusz\LogViewer\Service\SetupWizard;
use PHPUnit\Framework\TestCase;

class SetupWizardTest extends TestCase
{
    private SetupWizard $wizard;
    private ConfigManager $configManager;
    private LogConfig $logConfig;
    private string $tempConfig;
    private string $tempEnv;

    protected function setUp(): void
    {
        $this->tempConfig = sys_get_temp_dir() . '/config_' . bin2hex(random_bytes(8)) . '.json';
        $this->tempEnv = sys_get_temp_dir() . '/env_' . bin2hex(random_bytes(8));

        $this->configManager = new ConfigManager($this->tempConfig, $this->tempEnv);
        $this->logConfig = $this->createMock(LogConfig::class);
        $this->wizard = new SetupWizard($this->configManager, $this->logConfig);
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

    public function testStepsConstant(): void
    {
        $this->assertEquals(['generate_keys', 'ssh_config', 'local_directories', 'finalize'], SetupWizard::STEPS);
    }

    public function testGetStatusReturnsInitialState(): void
    {
        $status = $this->wizard->getStatus();

        $this->assertEquals('not_started', $status['state']);
        $this->assertCount(4, $status['steps']);

        foreach ($status['steps'] as $step) {
            $this->assertArrayHasKey('name', $step);
            $this->assertArrayHasKey('status', $step);
            $this->assertEquals('pending', $step['status']);
        }
    }

    public function testGetStatusReturnsCompletedSteps(): void
    {
        $this->configManager->saveConfig([
            'setup_steps' => [
                'generate_keys' => 'complete',
                'ssh_config' => 'skipped',
                'local_directories' => 'pending',
                'finalize' => 'pending',
            ],
        ]);

        $status = $this->wizard->getStatus();

        $this->assertEquals('complete', $status['steps'][0]['status']);
        $this->assertEquals('skipped', $status['steps'][1]['status']);
        $this->assertEquals('pending', $status['steps'][2]['status']);
        $this->assertEquals('pending', $status['steps'][3]['status']);
    }

    public function testGetNextStepReturnsCorrectSequence(): void
    {
        $this->assertEquals('ssh_config', $this->wizard->getNextStep('generate_keys'));
        $this->assertEquals('local_directories', $this->wizard->getNextStep('ssh_config'));
        $this->assertEquals('finalize', $this->wizard->getNextStep('local_directories'));
        $this->assertNull($this->wizard->getNextStep('finalize'));
    }

    public function testGetSkipWarningReturnsCorrectMessages(): void
    {
        $this->assertStringContainsString('Backup konfiguracji nie będzie szyfrowany', $this->wizard->getSkipWarning('generate_keys'));
        $this->assertStringContainsString('Funkcja SSH jest wyłączona', $this->wizard->getSkipWarning('ssh_config'));
        $this->assertStringContainsString('Brak skonfigurowanych katalogów lokalnych', $this->wizard->getSkipWarning('local_directories'));
        $this->assertStringContainsString('/home/mariusz/PhpstormProjects/fast-php-log-viewer/logs', $this->wizard->getSkipWarning('local_directories'));
        $this->assertStringContainsString('Finalizacja setupu zostanie pominięta', $this->wizard->getSkipWarning('finalize'));
    }

    public function testProcessGenerateKeysReturnsEncryptionKeyDisplay(): void
    {
        $result = $this->wizard->processStep('generate_keys', [], false);

        $this->assertTrue($result['success']);
        $this->assertEquals('ssh_config', $result['next_step']);
        $this->assertArrayHasKey('encryption_key_display', $result);
        $this->assertArrayHasKey('installation_id', $result);
        $this->assertArrayHasKey('env_saved', $result);
        $this->assertEquals(64, strlen($result['encryption_key_display']));

        // Sprawdź czy konfiguracja została zapisana
        $config = $this->configManager->getConfig();
        $this->assertEquals($result['installation_id'], $config['installation_id']);
        $this->assertEquals('complete', $config['setup_steps']['generate_keys']);
        $this->assertTrue($config['backup_encryption_enabled']);
    }

    public function testProcessGenerateKeysSkipReturnsWarning(): void
    {
        $result = $this->wizard->processStep('generate_keys', [], true);

        $this->assertTrue($result['success']);
        $this->assertEquals('ssh_config', $result['next_step']);
        $this->assertArrayHasKey('warning', $result);
        $this->assertStringContainsString('Backup konfiguracji nie będzie szyfrowany', $result['warning']);

        $config = $this->configManager->getConfig();
        $this->assertEquals('skipped', $config['setup_steps']['generate_keys']);
        $this->assertFalse($config['backup_encryption_enabled']);
    }

    public function testProcessSSHConfigSkipSetsSSHDisabled(): void
    {
        $result = $this->wizard->processStep('ssh_config', [], true);

        $this->assertTrue($result['success']);
        $this->assertEquals('local_directories', $result['next_step']);
        $this->assertArrayHasKey('warning', $result);

        $config = $this->configManager->getConfig();
        $this->assertEquals('skipped', $config['setup_steps']['ssh_config']);
        $this->assertFalse($config['ssh_enabled']);
    }

    public function testProcessSSHConfigRequiresSshHostAndUser(): void
    {
        $result = $this->wizard->processStep('ssh_config', ['ssh_host' => 'example.com'], false);

        $this->assertFalse($result['success']);
        $this->assertEquals('missing_fields', $result['error']);
        $this->assertContains('ssh_user', $result['fields']);

        $result = $this->wizard->processStep('ssh_config', ['ssh_user' => 'admin'], false);

        $this->assertFalse($result['success']);
        $this->assertEquals('missing_fields', $result['error']);
        $this->assertContains('ssh_host', $result['fields']);
    }

    public function testProcessSSHConfigWithValidData(): void
    {
        $data = [
            'ssh_host' => 'example.com',
            'ssh_user' => 'admin',
            'ssh_port' => 2222,
            'ssh_auth_method' => 'key',
            'ssh_key_path' => '/home/user/.ssh/id_rsa',
            'remote_path' => '/var/log',
            'all_files' => true,
        ];

        $result = $this->wizard->processStep('ssh_config', $data, false);

        $this->assertTrue($result['success']);
        $this->assertEquals('local_directories', $result['next_step']);

        $config = $this->configManager->getConfig();
        $this->assertEquals('complete', $config['setup_steps']['ssh_config']);
        $this->assertTrue($config['ssh_enabled']);
        $this->assertNotEmpty($config['ssh_profiles']);
    }

    public function testProcessSSHConfigWithKeyPathWarnsWhenKeyNotFound(): void
    {
        $data = [
            'ssh_host' => 'example.com',
            'ssh_user' => 'admin',
            'ssh_key_path' => '/nonexistent/key',
        ];

        $result = $this->wizard->processStep('ssh_config', $data, false);

        $this->assertTrue($result['success']);

        $config = $this->configManager->getConfig();
        $profile = $config['ssh_profiles'][array_key_first($config['ssh_profiles'])];
        $this->assertTrue($profile['ssh_key_path_warning']);
    }

    public function testProcessLocalDirectoriesSkipReturnsWarning(): void
    {
        $result = $this->wizard->processStep('local_directories', [], true);

        $this->assertTrue($result['success']);
        $this->assertEquals('finalize', $result['next_step']);
        $this->assertArrayHasKey('warning', $result);
        $this->assertStringContainsString('Brak skonfigurowanych katalogów lokalnych', $result['warning']);

        $config = $this->configManager->getConfig();
        $this->assertEquals('skipped', $config['setup_steps']['local_directories']);
    }

    public function testProcessLocalDirectoriesWithValidData(): void
    {
        $tempDir = sys_get_temp_dir() . '/test_log_dir_' . bin2hex(random_bytes(4));
        mkdir($tempDir);

        $this->logConfig->expects($this->once())
            ->method('addDirectory')
            ->willReturn(1);

        $data = [
            'directories' => [
                ['name' => 'Test Dir', 'path' => $tempDir],
            ],
        ];

        $result = $this->wizard->processStep('local_directories', $data, false);

        $this->assertTrue($result['success']);
        $this->assertEquals('finalize', $result['next_step']);
        $this->assertEquals(1, $result['added']);

        $config = $this->configManager->getConfig();
        $this->assertEquals('complete', $config['setup_steps']['local_directories']);
        $this->assertNotEmpty($config['local_directories']);

        rmdir($tempDir);
    }

    public function testProcessFinalizeMarksSetupComplete(): void
    {
        $result = $this->wizard->processStep('finalize', [], false);

        $this->assertTrue($result['success']);
        $this->assertNull($result['next_step']);
        $this->assertTrue($result['setup_complete']);

        $this->assertTrue($this->configManager->isSetupComplete());
        $config = $this->configManager->getConfig();
        $this->assertEquals('complete', $config['setup_steps']['finalize']);
    }

    public function testProcessFinalizeSkipReturnsWarning(): void
    {
        $result = $this->wizard->processStep('finalize', [], true);

        $this->assertTrue($result['success']);
        $this->assertNull($result['next_step']);
        $this->assertArrayHasKey('warning', $result);

        $this->assertFalse($this->configManager->isSetupComplete());
    }

    public function testMigrateSSHFromLocalStorageReturnsCountAndWarnings(): void
    {
        $connections = [
            [
                'name' => 'Test Server',
                'ssh_host' => 'example.com',
                'ssh_user' => 'admin',
                'ssh_port' => 22,
                'ssh_auth_method' => 'password',
                'remote_path' => '/var/log',
            ],
            [
                'name' => 'Invalid Connection',
                'ssh_host' => 'invalid.com',
                // Brak ssh_user
            ],
        ];

        $result = $this->wizard->migrateSSHFromLocalStorage($connections);

        $this->assertEquals(1, $result['migrated']);
        $this->assertCount(1, $result['warnings']);
        $this->assertStringContainsString('użytkownika', $result['warnings'][0]);
    }

    public function testMigrateSSHFromLocalStorageWithEmptyArrayReturnsZero(): void
    {
        $result = $this->wizard->migrateSSHFromLocalStorage([]);

        $this->assertEquals(0, $result['migrated']);
        $this->assertEmpty($result['warnings']);
    }

    public function testMigrateSSHFromLocalStorageWithKeyPath(): void
    {
        $connections = [
            [
                'name' => 'Key Auth Server',
                'ssh_host' => 'example.com',
                'ssh_user' => 'admin',
                'ssh_auth_method' => 'key',
                'ssh_key_path' => '/home/user/.ssh/id_rsa',
                'remote_path' => '/var/log',
            ],
        ];

        $result = $this->wizard->migrateSSHFromLocalStorage($connections);

        $this->assertEquals(1, $result['migrated']);

        $config = $this->configManager->getConfig();
        $profile = $config['ssh_profiles'][array_key_first($config['ssh_profiles'])];
        $this->assertEquals('/home/user/.ssh/id_rsa', $profile['ssh_key_path_original']);
        $this->assertTrue($profile['ssh_key_path_warning']);
        $this->assertTrue($profile['migrated_from_localstorage']);
    }

    public function testProcessStepWithUnknownStepThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown step');

        $this->wizard->processStep('unknown_step', [], false);
    }

    public function testSkipGenerateKeysReturnNoEncryptionWarning(): void
    {
        $result = $this->wizard->processStep('generate_keys', [], true);

        $this->assertArrayHasKey('warning', $result);
        $this->assertStringContainsString('nie będzie szyfrowany', $result['warning']);
    }

    public function testFinalizeMarksSetsupComplete(): void
    {
        $result = $this->wizard->processStep('finalize', [], false);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['setup_complete']);
        $this->assertNull($result['next_step']);
        $this->assertTrue($this->configManager->isSetupComplete());
    }
}
