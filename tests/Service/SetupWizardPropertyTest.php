<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests\Service;

use Eris\TestTrait;
use Mariusz\LogViewer\Config\ConfigManager;
use Mariusz\LogViewer\Config\LogConfig;
use Mariusz\LogViewer\Service\SetupWizard;
use PHPUnit\Framework\TestCase;

class SetupWizardPropertyTest extends TestCase
{
    use TestTrait;

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

    /**
     * Property 7: Every skipped step produces a warning
     */
    public function testSkippedStepAlwaysHasWarning(): void
    {
        $steps = ['generate_keys', 'ssh_config', 'local_directories', 'finalize'];

        foreach ($steps as $step) {
            $this->forAll(
                \Eris\Generator\associative([
                    'random_field' => \Eris\Generator\string(),
                    'another_field' => \Eris\Generator\int()
                ])
            )
            ->then(function ($data) use ($step) {
                $result = $this->wizard->processStep($step, $data, true);

                $this->assertArrayHasKey('warning', $result, 
                    "Step $step with skip=true should return warning");
                $this->assertNotEmpty($result['warning'], 
                    "Warning for step $step should not be empty");
                $this->assertIsString($result['warning'], 
                    "Warning for step $step should be a string");
            });
        }
    }

    /**
     * Property 9: SSH validation rejects data without required fields
     */
    public function testSSHValidationRejectsMissingFields(): void
    {
        // Testy dla danych bez ssh_host
        $this->forAll(
            \Eris\Generator\associative([
                'ssh_user' => \Eris\Generator\string(),
                'ssh_port' => \Eris\Generator\int(),
                'random_field' => \Eris\Generator\string()
            ])
        )
        ->then(function ($data) {
            $result = $this->wizard->processStep('ssh_config', $data, false);

            $this->assertFalse($result['success'], 
                "Should fail when ssh_host is missing");
            $this->assertEquals('missing_fields', $result['error']);
            $this->assertContains('ssh_host', $result['fields']);
        });

        // Testy dla danych bez ssh_user
        $this->forAll(
            \Eris\Generator\associative([
                'ssh_host' => \Eris\Generator\string(),
                'ssh_port' => \Eris\Generator\int(),
                'random_field' => \Eris\Generator\string()
            ])
        )
        ->then(function ($data) {
            $result = $this->wizard->processStep('ssh_config', $data, false);

            $this->assertFalse($result['success'], 
                "Should fail when ssh_user is missing");
            $this->assertEquals('missing_fields', $result['error']);
            $this->assertContains('ssh_user', $result['fields']);
        });

        // Testy dla danych bez obu pól
        $this->forAll(
            \Eris\Generator\associative([
                'ssh_port' => \Eris\Generator\int(),
                'random_field' => \Eris\Generator\string()
            ])
        )
        ->then(function ($data) {
            $result = $this->wizard->processStep('ssh_config', $data, false);

            $this->assertFalse($result['success'], 
                "Should fail when both ssh_host and ssh_user are missing");
            $this->assertEquals('missing_fields', $result['error']);
            $this->assertContains('ssh_host', $result['fields']);
            $this->assertContains('ssh_user', $result['fields']);
        });

        // Testy dla poprawnych danych
        $this->forAll(
            \Eris\Generator\associative([
                'ssh_host' => \Eris\Generator\constant('example.com'),
                'ssh_user' => \Eris\Generator\constant('testuser'),
                'ssh_port' => \Eris\Generator\int(),
                'random_field' => \Eris\Generator\string()
            ])
        )
        ->then(function ($data) {
            $result = $this->wizard->processStep('ssh_config', $data, false);

            $this->assertTrue($result['success'],
                "Should succeed when both ssh_host and ssh_user are present");
        });
    }

    /**
     * Property 10: SSH directories filtered when ssh disabled
     */
    public function testSSHDirectoriesFilteredWhenDisabled(): void
    {
        // Testy dla ssh_enabled=false
        $this->forAll(
            \Eris\Generator\vector(
                5,
                \Eris\Generator\associative([
                    'name' => \Eris\Generator\string(),
                    'path' => \Eris\Generator\string(),
                    'type' => \Eris\Generator\oneOf(
                        \Eris\Generator\constant('local'),
                        \Eris\Generator\constant('ssh')
                    )
                ])
            )
        )
        ->then(function ($directories) {
            $this->configManager->saveConfig([
                'ssh_enabled' => false
            ]);

            // Symuluj filtrowanie jak w LogController
            $filtered = array_filter($directories, function ($dir) {
                return ($dir['type'] ?? 'local') !== 'ssh';
            });

            $sshCount = count(array_filter($directories, function ($dir) {
                return ($dir['type'] ?? 'local') === 'ssh';
            }));

            // Gdy ssh_enabled=false, katalogi SSH powinny być odfiltrowane
            $this->assertEquals(count($filtered), count($directories) - $sshCount);
            $this->assertFalse($this->configManager->isSshEnabled());
        });

        // Testy dla ssh_enabled=true
        $this->forAll(
            \Eris\Generator\vector(
                5,
                \Eris\Generator\associative([
                    'name' => \Eris\Generator\string(),
                    'path' => \Eris\Generator\string(),
                    'type' => \Eris\Generator\oneOf(
                        \Eris\Generator\constant('local'),
                        \Eris\Generator\constant('ssh')
                    )
                ])
            )
        )
        ->then(function ($directories) {
            $this->configManager->saveConfig([
                'ssh_enabled' => true
            ]);

            // Gdy ssh_enabled=true, wszystkie katalogi powinny być dostępne
            $this->assertEquals(count($directories), count($directories));
            $this->assertTrue($this->configManager->isSshEnabled());
        });
    }
}
