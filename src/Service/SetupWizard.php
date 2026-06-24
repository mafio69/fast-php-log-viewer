<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Service;

use Exception;
use InvalidArgumentException;
use Mariusz\LogViewer\Config\ConfigManager;
use Mariusz\LogViewer\Config\LogConfig;

class SetupWizard
{
    public const STEPS = ['generate_keys', 'ssh_config', 'local_directories', 'finalize'];

    public function __construct(
        private readonly ConfigManager $configManager,
        private readonly LogConfig $logConfig,
    ) {
    }

    /**
     * Zwraca aktualny SetupState i listę kroków ze statusem pending|complete|skipped.
     */
    public function getStatus(): array
    {
        $config = $this->configManager->getConfig();
        $setupSteps = $config['setup_steps'] ?? [];

        $steps = [];
        foreach (self::STEPS as $step) {
            $status = $setupSteps[$step] ?? 'pending';
            $steps[] = [
                'name' => $step,
                'status' => $status,
            ];
        }

        return [
            'state' => $this->configManager->getSetupState(),
            'steps' => $steps,
        ];
    }

    /**
     * Zwraca następny krok z STEPS lub null.
     */
    public function getNextStep(string $currentStep): ?string
    {
        $index = array_search($currentStep, self::STEPS, true);
        if ($index === false || $index === count(self::STEPS) - 1) {
            return null;
        }

        return self::STEPS[$index + 1];
    }

    /**
     * Zwraca komunikat ostrzeżenia dla każdego kroku.
     */
    public function getSkipWarning(string $step): string
    {
        return match ($step) {
            'generate_keys' => 'Backup konfiguracji nie będzie szyfrowany. Dane w pliku backup będą przechowywane w postaci jawnej.',
            'ssh_config' => 'Funkcja SSH jest wyłączona. Aby przeglądać logi zdalne, musisz skonfigurować połączenia SSH.',
            'local_directories' => 'Brak skonfigurowanych katalogów lokalnych. Możesz dodać katalogi później w ustawieniach. Aplikacja będzie szukać logów w katalogu /home/mariusz/PhpstormProjects/fast-php-log-viewer/logs.',
            'finalize' => 'Finalizacja setupu zostanie pominięta. Konfiguracja może być niekompletna.',
            default => 'Ten krok zostanie pominięty.',
        };
    }

    /**
     * Przetwarza krok wizarda.
     */
    public function processStep(string $step, array $data, bool $skip): array
    {
        return match ($step) {
            'generate_keys' => $this->processGenerateKeys($data, $skip),
            'ssh_config' => $this->processSSHConfig($data, $skip),
            'local_directories' => $this->processLocalDirectories($data, $skip),
            'finalize' => $this->processFinalize($data, $skip),
            default => throw new InvalidArgumentException("Unknown step: $step"),
        };
    }

    /**
     * Migruje połączenia SSH z localStorage.
     */
    public function migrateSSHFromLocalStorage(array $connections): array
    {
        $migrated = 0;
        $warnings = [];

        foreach ($connections as $conn) {
            try {
                // Walidacja wymaganych pól
                if (empty($conn['ssh_host']) || empty($conn['ssh_user'])) {
                    $warnings[] = "Pominięto połączenie bez hosta lub użytkownika: " . ($conn['name'] ?? 'unnamed');
                    continue;
                }

                // Przygotuj profil SSH
                $profile = [
                    'id' => 'profile_' . uniqid(),
                    'name' => $conn['name'] ?? 'Migrated SSH',
                    'ssh_host' => $conn['ssh_host'],
                    'ssh_user' => $conn['ssh_user'],
                    'ssh_port' => $conn['ssh_port'] ?? 22,
                    'ssh_auth_method' => $conn['ssh_auth_method'] ?? 'password',
                    'remote_path' => $conn['remote_path'] ?? '/var/log',
                    'all_files' => $conn['all_files'] ?? false,
                    'migrated_from_localstorage' => true,
                ];

                // Dodaj ścieżkę klucza jeśli używana
                if (!empty($conn['ssh_key_path'])) {
                    $profile['ssh_key_path'] = $conn['ssh_key_path'];
                    $profile['ssh_key_path_original'] = $conn['ssh_key_path'];
                    $profile['ssh_key_path_warning'] = true;
                }

                // Zapisz profil (bez hasła)
                $this->configManager->saveSSHProfile($profile);
                $migrated++;
            } catch (Exception $e) {
                $warnings[] = "Błąd migracji połączenia " . ($conn['name'] ?? 'unnamed') . ": " . $e->getMessage();
            }
        }

        // Włącz SSH jeśli migrowano jakiekolwiek połączenia
        if ($migrated > 0) {
            $this->configManager->updateConfig(['ssh_enabled' => true]);
        }

        return [
            'migrated' => $migrated,
            'warnings' => $warnings,
        ];
    }

    /**
     * Przetwarza krok generate_keys.
     */
    private function processGenerateKeys(array $data, bool $skip): array
    {
        $config = $this->configManager->getConfig();
        $setupSteps = $config['setup_steps'] ?? [];

        if ($skip) {
            $setupSteps['generate_keys'] = 'skipped';
            $this->configManager->updateConfig([
                'setup_steps' => $setupSteps,
                'backup_encryption_enabled' => false,
                'setup_state' => 'in_progress',
            ]);

            return [
                'success' => true,
                'next_step' => $this->getNextStep('generate_keys'),
                'warning' => $this->getSkipWarning('generate_keys'),
            ];
        }

        // Generuj klucze
        $installationId = $this->configManager->generateInstallationId();
        $encryptionKey = $this->configManager->generateEncryptionKey();

        // Zapisz klucz do .env
        $envSaved = $this->configManager->saveEncryptionKeyToEnv($encryptionKey);

        $setupSteps['generate_keys'] = 'complete';
        $this->configManager->updateConfig([
            'installation_id' => $installationId,
            'setup_steps' => $setupSteps,
            'backup_encryption_enabled' => true,
            'setup_state' => 'in_progress',
            'created_at' => date('c'),
        ]);

        return [
            'success' => true,
            'next_step' => $this->getNextStep('generate_keys'),
            'encryption_key_display' => $encryptionKey,
            'installation_id' => $installationId,
            'env_saved' => $envSaved,
        ];
    }

    /**
     * Przetwarza krok ssh_config.
     */
    private function processSSHConfig(array $data, bool $skip): array
    {
        $config = $this->configManager->getConfig();
        $setupSteps = $config['setup_steps'] ?? [];

        if ($skip) {
            $setupSteps['ssh_config'] = 'skipped';
            $this->configManager->updateConfig([
                'setup_steps' => $setupSteps,
                'ssh_enabled' => false,
                'setup_state' => 'in_progress',
            ]);

            return [
                'success' => true,
                'next_step' => $this->getNextStep('ssh_config'),
                'warning' => $this->getSkipWarning('ssh_config'),
            ];
        }

        // Walidacja pól SSH
        $validation = $this->validateSSHFields($data);
        if ($validation !== null) {
            return [
                'success' => false,
                'error' => 'missing_fields',
                'fields' => $validation,
            ];
        }

        // Zapisz profil SSH
        $profile = [
            'id' => 'profile_' . uniqid(),
            'name' => $data['name'] ?? 'SSH Profile',
            'ssh_host' => $data['ssh_host'],
            'ssh_user' => $data['ssh_user'],
            'ssh_port' => $data['ssh_port'] ?? 22,
            'ssh_auth_method' => $data['ssh_auth_method'] ?? 'password',
            'remote_path' => $data['remote_path'] ?? '/var/log',
            'all_files' => $data['all_files'] ?? false,
        ];

        if (!empty($data['ssh_key_path'])) {
            $profile['ssh_key_path'] = $data['ssh_key_path'];
            $profile['ssh_key_path_original'] = $data['ssh_key_path'];
            $profile['ssh_key_path_warning'] = true;
        }

        $this->configManager->saveSSHProfile($profile);

        $setupSteps['ssh_config'] = 'complete';
        $this->configManager->updateConfig([
            'setup_steps' => $setupSteps,
            'ssh_enabled' => true,
            'setup_state' => 'in_progress',
        ]);

        return [
            'success' => true,
            'next_step' => $this->getNextStep('ssh_config'),
        ];
    }

    /**
     * Przetwarza krok local_directories.
     */
    private function processLocalDirectories(array $data, bool $skip): array
    {
        $config = $this->configManager->getConfig();
        $setupSteps = $config['setup_steps'] ?? [];

        if ($skip) {
            $setupSteps['local_directories'] = 'skipped';
            $this->configManager->updateConfig([
                'setup_steps' => $setupSteps,
                'setup_state' => 'in_progress',
            ]);

            return [
                'success' => true,
                'next_step' => $this->getNextStep('local_directories'),
                'warning' => $this->getSkipWarning('local_directories'),
            ];
        }

        // Dodaj katalogi lokalne
        $localDirs = $data['directories'] ?? [];
        $added = 0;

        foreach ($localDirs as $dir) {
            try {
                $this->logConfig->addDirectory([
                    'name' => $dir['name'] ?? 'Local Directory',
                    'path' => $dir['path'],
                    'type' => 'local',
                ]);
                $added++;
            } catch (Exception $e) {
                // Kontynuuj z kolejnym katalogiem
            }
        }

        $setupSteps['local_directories'] = 'complete';
        $this->configManager->updateConfig([
            'setup_steps' => $setupSteps,
            'local_directories' => $localDirs,
            'setup_state' => 'in_progress',
        ]);

        return [
            'success' => true,
            'next_step' => $this->getNextStep('local_directories'),
            'added' => $added,
        ];
    }

    /**
     * Przetwarza krok finalize.
     */
    private function processFinalize(array $data, bool $skip): array
    {
        $config = $this->configManager->getConfig();
        $setupSteps = $config['setup_steps'] ?? [];

        if ($skip) {
            $setupSteps['finalize'] = 'skipped';
            $this->configManager->updateConfig([
                'setup_steps' => $setupSteps,
                'setup_state' => 'in_progress',
            ]);

            return [
                'success' => true,
                'next_step' => null,
                'warning' => $this->getSkipWarning('finalize'),
            ];
        }

        // Zakończ setup
        $setupSteps['finalize'] = 'complete';
        $this->configManager->updateConfig([
            'setup_steps' => $setupSteps,
            'setup_state' => 'complete',
            'updated_at' => date('c'),
        ]);

        $this->configManager->markSetupComplete();

        return [
            'success' => true,
            'next_step' => null,
            'setup_complete' => true,
        ];
    }

    /**
     * Waliduje pola SSH. Zwraca null lub tablicę z brakującymi polami.
     */
    private function validateSSHFields(array $data): ?array
    {
        $missing = [];

        if (empty($data['ssh_host'])) {
            $missing[] = 'ssh_host';
        }

        if (empty($data['ssh_user'])) {
            $missing[] = 'ssh_user';
        }

        return empty($missing) ? null : $missing;
    }
}
