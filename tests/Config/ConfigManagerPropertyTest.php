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

    /**
     * Property 2: Generated InstallationIds are valid UUID v4
     */
    public function testGeneratedInstallationIdsAreValidUuidV4(): void
    {
        $this->limitTo(100)
            ->forAll(
            // We don't really need a generator for input here since the method doesn't take parameters,
            // but Eris requires at least one generator. We use a dummy one.
            \Eris\Generator\constant(null)
        )
            ->then(function () {
                $id = $this->configManager->generateInstallationId();
                
                // UUID v4 pattern
                $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
                $this->assertMatchesRegularExpression($pattern, $id, "ID $id does not match UUID v4 pattern");
            });
    }

    /**
     * Unique IDs check (Property 2 part 2)
     */
    public function testGeneratedInstallationIdsAreUnique(): void
    {
        $ids = [];
        for ($i = 0; $i < 100; $i++) {
            $ids[] = $this->configManager->generateInstallationId();
        }
        
        $this->assertCount(100, array_unique($ids), "Duplicate IDs found");
    }

    /**
     * Property 3: Generated EncryptionKeys are 64-char hex
     */
    public function testGeneratedEncryptionKeysAre64CharHex(): void
    {
        $this->limitTo(100)
            ->forAll(
            \Eris\Generator\constant(null)
        )
            ->then(function () {
                $key = $this->configManager->generateEncryptionKey();
                
                $this->assertEquals(64, strlen($key));
                $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $key);
            });
    }

    /**
     * Unique Keys check (Property 3 part 2)
     */
    public function testGeneratedEncryptionKeysAreUnique(): void
    {
        $keys = [];
        for ($i = 0; $i < 100; $i++) {
            $keys[] = $this->configManager->generateEncryptionKey();
        }
        
        $this->assertCount(100, array_unique($keys), "Duplicate keys found");
    }
}
