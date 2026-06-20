<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests\Config;

use Eris\TestTrait;
use Mariusz\LogViewer\Config\ConfigManager;
use PHPUnit\Framework\TestCase;

class ConfigManagerPropertyTest extends TestCase
{
    use TestTrait;

    private ConfigManager $configManager;
    private string $tempConfig;
    private string $tempEnv;

    protected function setUp(): void
    {
        $this->tempConfig = sys_get_temp_dir() . '/test_config_' . uniqid() . '.json';
        $this->tempEnv = sys_get_temp_dir() . '/test_env_' . uniqid();
        $this->configManager = new ConfigManager($this->tempConfig, $this->tempEnv);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempConfig)) {
            unlink($this->tempConfig);
        }
        if (file_exists($this->tempEnv)) {
            unlink($this->tempEnv);
        }
    }

    /**
     * Property 2: Generated InstallationIds are valid UUID v4
     */
    public function testGeneratedInstallationIdsAreValidUuidV4(): void
    {
        $generatedIds = [];

        $this->forAll()
            ->then(function () use (&$generatedIds) {
                $id = $this->configManager->generateInstallationId();
                
                $this->assertMatchesRegularExpression(
                    '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
                    $id,
                    "Generated ID $id is not a valid UUID v4"
                );
                
                $this->assertNotContains($id, $generatedIds, "Generated ID $id is not unique");
                $generatedIds[] = $id;
            });
    }

    /**
     * Property 3: Generated EncryptionKeys are 64-char hex
     */
    public function testGeneratedEncryptionKeysAre64CharHex(): void
    {
        $generatedKeys = [];

        $this->forAll()
            ->then(function () use (&$generatedKeys) {
                $key = $this->configManager->generateEncryptionKey();
                
                $this->assertEquals(64, strlen($key));
                $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $key);
                
                $this->assertNotContains($key, $generatedKeys, "Generated key $key is not unique");
                $generatedKeys[] = $key;
            });
    }

    /**
     * Property 4: Config serialization is reversible
     */
    public function testConfigSerializationIsReversible(): void
    {
        $this->forAll(
            \Eris\Generator\associative([
                'installation_id' => \Eris\Generator\string(),
                'app_name' => \Eris\Generator\string(),
                'debug' => \Eris\Generator\bool(),
                'nested' => \Eris\Generator\associative([
                    'foo' => \Eris\Generator\string(),
                    'bar' => \Eris\Generator\int()
                ])
            ])
        )
        ->then(function ($data) {
            $this->configManager->saveConfig($data);
            $loaded = $this->configManager->getConfig();
            
            $this->assertEquals($data, $loaded);
            
            // Sprawdź czy plik JSON jest poprawnie sformatowany
            $content = file_get_contents($this->tempConfig);
            $this->assertStringContainsString("\n", $content); // PRETTY_PRINT
            
            // Sprawdź uprawnienia
            $this->assertEquals(0600, fileperms($this->tempConfig) & 0777);
        });
    }

    /**
     * Property 5: Sensitive fields never leave the system
     */
    public function testSensitiveFieldsNeverLeaveTheSystem(): void
    {
        $this->forAll(
            \Eris\Generator\associative([
                'ssh_password' => \Eris\Generator\string(),
                'ssh_key_passphrase' => \Eris\Generator\string(),
                'encryption_key_raw' => \Eris\Generator\string(),
                'public_field' => \Eris\Generator\string(),
                'ssh_connections' => \Eris\Generator\vector(1, \Eris\Generator\associative([
                    'name' => \Eris\Generator\string(),
                    'ssh_password' => \Eris\Generator\string()
                ]))
            ])
        )
        ->then(function ($data) {
            $this->configManager->saveConfig($data);
            $public = $this->configManager->getPublicConfig();
            
            $this->assertEquals('********', $public['ssh_password']);
            $this->assertEquals('********', $public['ssh_key_passphrase']);
            $this->assertEquals('********', $public['encryption_key_raw']);
            $this->assertArrayHasKey('public_field', $public);
            
            // Sprawdź rekursywnie
            if (isset($public['ssh_connections'])) {
                foreach ($public['ssh_connections'] as $conn) {
                    $this->assertEquals('********', $conn['ssh_password']);
                }
            }
        });
    }
}
