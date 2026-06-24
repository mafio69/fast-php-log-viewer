<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests\Config;

use Eris\TestTrait;
use Mariusz\LogViewer\Config\ConfigManager;
use PHPUnit\Framework\TestCase;
use function Eris\Generator\associative;
use function Eris\Generator\bool;
use function Eris\Generator\int;
use function Eris\Generator\oneOf;
use function Eris\Generator\string;
use function Eris\Generator\vector;

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
            associative([
                'installation_id' => string(),
                'app_name' => string(),
                'debug' => bool(),
                'nested' => associative([
                    'foo' => string(),
                    'bar' => int()
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
            associative([
                'ssh_password' => string(),
                'ssh_key_passphrase' => string(),
                'encryption_key_raw' => string(),
                'public_field' => string(),
                'ssh_connections' => vector(1, associative([
                    'name' => string(),
                    'ssh_password' => string()
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

    /**
     * Property 6: Every config save sets 0600 permissions
     */
    public function testEveryConfigSaveSets0600Permissions(): void
    {
        $this->forAll(
            associative([
                'foo' => string()
            ])
        )
        ->then(function ($data) {
            $this->configManager->saveConfig($data);
            $this->assertEquals(0600, fileperms($this->tempConfig) & 0777);
        });
    }

    /**
     * Property 1: Setup detection is always correct
     */
    public function testSetupDetectionIsAlwaysCorrect(): void
    {
        $this->forAll(
            oneOf(
                \Eris\Generator\constant(null),
                \Eris\Generator\constant(false),
                \Eris\Generator\constant(true),
                string(),
                associative(['setup_complete' => bool()]),
                associative(['setup_complete' => \Eris\Generator\constant('yes')])
            )
        )
        ->then(function ($input) {
            if ($input === null) {
                if (file_exists($this->tempConfig)) unlink($this->tempConfig);
            } else {
                file_put_contents($this->tempConfig, is_array($input) ? json_encode($input) : (string)$input);
            }

            $expected = false;
            if (is_array($input) && isset($input['setup_complete']) && $input['setup_complete'] === true) {
                $expected = true;
            }

            $this->assertEquals($expected, $this->configManager->isSetupComplete(), "Failed for input: " . json_encode($input));
        });
    }

    /**
     * Property 8: Setup is complete for any complete/skipped combination
     */
    public function testSetupIsCompleteForAnyCompleteSkippedCombination(): void
    {
        $this->forAll(
            associative([
                'generate_keys' => oneOf(
                    \Eris\Generator\constant('complete'),
                    \Eris\Generator\constant('skipped'),
                    \Eris\Generator\constant('pending')
                ),
                'ssh_config' => oneOf(
                    \Eris\Generator\constant('complete'),
                    \Eris\Generator\constant('skipped'),
                    \Eris\Generator\constant('pending')
                ),
                'local_directories' => oneOf(
                    \Eris\Generator\constant('complete'),
                    \Eris\Generator\constant('skipped'),
                    \Eris\Generator\constant('pending')
                ),
                'finalize' => oneOf(
                    \Eris\Generator\constant('complete'),
                    \Eris\Generator\constant('skipped'),
                    \Eris\Generator\constant('pending')
                )
            ])
        )
        ->then(function ($setupSteps) {
            // Sprawdź czy setup jest kompletny tylko gdy wszystkie kroki są complete lub skipped
            $allCompleteOrSkipped = true;
            foreach ($setupSteps as $step => $status) {
                if ($status === 'pending') {
                    $allCompleteOrSkipped = false;
                    break;
                }
            }
            
            // Ustaw setup_complete zgodnie z logiką kroków
            $config = [
                'setup_steps' => $setupSteps,
                'setup_complete' => $allCompleteOrSkipped
            ];
            
            $this->configManager->saveConfig($config);
            
            // Sprawdź czy isSetupComplete zwraca poprawną wartość
            $this->assertEquals($allCompleteOrSkipped, $this->configManager->isSetupComplete(), 
                "isSetupComplete() should return " . ($allCompleteOrSkipped ? 'true' : 'false') . 
                " for steps: " . json_encode($setupSteps));
        });
    }
}
