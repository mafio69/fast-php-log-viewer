<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Config;

class ConfigManager
{
    public function __construct(
        private readonly string $configPath,
        private readonly string $envPath
    ) {
    }

    /**
     * Zwraca true wtedy i tylko wtedy, gdy plik istnieje, jest prawidłowym JSON
     * i zawiera setup_complete === true (strict).
     */
    public function isSetupComplete(): bool
    {
        if (!file_exists($this->configPath)) {
            return false;
        }

        $content = @file_get_contents($this->configPath);
        if ($content === false) {
            return false;
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            return isset($data['setup_complete']) && $data['setup_complete'] === true;
        } catch (\JsonException) {
            return false;
        }
    }

    /**
     * Zwraca {state, steps[{name, status}]}.
     */
    public function getSetupStatus(): array
    {
        $config = $this->getConfig();
        $state = $this->getSetupState();
        
        $steps = [
            ['name' => 'General Configuration', 'status' => isset($config['installation_id']) ? 'complete' : 'pending'],
            ['name' => 'SSH Configuration', 'status' => (isset($config['ssh_connections']) && count($config['ssh_connections']) > 0) ? 'complete' : 'pending'],
            ['name' => 'Encryption Key', 'status' => isset($config['encryption_key_raw']) ? 'complete' : 'pending'],
        ];

        return [
            'state' => $state,
            'steps' => $steps
        ];
    }

    /**
     * Zwraca not_started|in_progress|complete|skipped.
     */
    public function getSetupState(): string
    {
        if ($this->isSetupComplete()) {
            return 'complete';
        }

        $config = $this->getConfig();
        if (empty($config)) {
            return 'not_started';
        }

        if (isset($config['setup_state'])) {
            return (string)$config['setup_state'];
        }

        return 'in_progress';
    }

    /**
     * Ustawia setup_complete: true, setup_state: complete.
     */
    public function markSetupComplete(): void
    {
        $this->updateConfig([
            'setup_complete' => true,
            'setup_state' => 'complete'
        ]);
    }

    /**
     * Generuje UUID v4 przez random_bytes(16) z formatowaniem.
     */
    public function generateInstallationId(): string
    {
        $data = random_bytes(16);

        // Ustaw bity wersji (4) i wariantu (8, 9, a lub b)
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Generuje 64-znakowy klucz szyfrujący w formacie hex.
     */
    public function generateEncryptionKey(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Metoda pomocnicza do pobierania konfiguracji.
     */
    public function getConfig(): array
    {
        if (!file_exists($this->configPath)) {
            return [];
        }

        $content = @file_get_contents($this->configPath);
        if ($content === false) {
            error_log("Failed to read config file: {$this->configPath}");
            return [];
        }

        try {
            return json_decode($content, true, 512, JSON_THROW_ON_ERROR) ?: [];
        } catch (\JsonException $e) {
            error_log("Invalid JSON in config file: {$this->configPath}. Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Zwraca konfigurację z odfiltrowanymi polami wrażliwymi.
     */
    public function getPublicConfig(): array
    {
        return $this->filterSensitiveFields($this->getConfig());
    }

    /**
     * Zapisuje konfigurację w sposób atomowy.
     */
    public function saveConfig(array $config): void
    {
        $this->validateAndWriteJson($this->configPath, $config);
    }

    /**
     * Aktualizuje konfigurację (merge rekursywny) i zapisuje.
     */
    public function updateConfig(array $partial): void
    {
        $config = $this->getConfig();
        $newConfig = array_replace_recursive($config, $partial);
        $this->saveConfig($newConfig);
    }

    /**
     * Sprawdza uprawnienia pliku konfiguracji.
     */
    public function checkFilePermissions(): void
    {
        if (!file_exists($this->configPath)) {
            return;
        }

        $perms = fileperms($this->configPath) & 0777;
        if ($perms > 0640) {
            $errorLogPath = DATA_DIR . '/php_errors.log';
            $message = "[" . date('Y-m-d H:i:s') . "] WARNING: Config file permissions are too open: " . sprintf('%o', $perms) . ". Recommended: 0600\n";
            @file_put_contents($errorLogPath, $message, FILE_APPEND);
        }
    }

    /**
     * Atomowy zapis JSON: tmp-file + verify + rename + chmod 0600.
     */
    private function validateAndWriteJson(string $path, array $data): void
    {
        $tmpPath = $path . '.tmp.' . bin2hex(random_bytes(8));
        
        try {
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            
            if (file_put_contents($tmpPath, $json) === false) {
                throw new \RuntimeException("Failed to write temporary config file: $tmpPath");
            }

            // Weryfikacja zapisanego pliku
            $verifyContent = file_get_contents($tmpPath);
            json_decode($verifyContent, true, 512, JSON_THROW_ON_ERROR);

            if (!rename($tmpPath, $path)) {
                throw new \RuntimeException("Failed to rename temporary config file to: $path");
            }

            chmod($path, 0600);

        } catch (\Throwable $e) {
            if (file_exists($tmpPath)) {
                @unlink($tmpPath);
            }
            throw new \RuntimeException("Atomic JSON write failed for $path: " . $e->getMessage());
        }
    }

    /**
     * Zwraca profil SSH po ID (bez pól wrażliwych).
     */
    public function getSSHProfile(string $id): ?array
    {
        $config = $this->getConfig();
        $profile = $config['ssh_profiles'][$id] ?? null;
        return $profile ? $this->filterSensitiveFields($profile) : null;
    }

    /**
     * Zwraca listę profili SSH (bez pól wrażliwych).
     */
    public function getSSHProfiles(): array
    {
        $config = $this->getConfig();
        $profiles = $config['ssh_profiles'] ?? [];
        return $this->filterSensitiveFields($profiles);
    }

    /**
     * Zapisuje profil SSH.
     */
    public function saveSSHProfile(array $profileData): string
    {
        $config = $this->getConfig();
        
        if (!isset($config['ssh_profiles'])) {
            $config['ssh_profiles'] = [];
        }

        $id = $profileData['id'] ?? ('profile_' . (count($config['ssh_profiles']) + 1));
        $profileData['id'] = $id;

        $config['ssh_profiles'][$id] = $profileData;
        
        $this->saveConfig($config);
        return $id;
    }

    /**
     * Sprawdza czy SSH jest włączone.
     */
    public function isSshEnabled(): bool
    {
        $config = $this->getConfig();
        return (bool)($config['ssh_enabled'] ?? true);
    }

    /**
     * Zapisuje klucz szyfrowania do pliku .env.
     */
    public function saveEncryptionKeyToEnv(string $hexKey): bool
    {
        if (!preg_match('/^[0-9a-f]{64}$/i', $hexKey)) {
            throw new \InvalidArgumentException("Invalid encryption key format. Expected 64-char hex.");
        }

        $content = '';
        if (file_exists($this->envPath)) {
            $content = file_get_contents($this->envPath);
        }

        $line = "BACKUP_ENCRYPTION_KEY=$hexKey";
        
        if (preg_match('/^BACKUP_ENCRYPTION_KEY=.*$/m', $content)) {
            $newContent = preg_replace('/^BACKUP_ENCRYPTION_KEY=.*$/m', $line, $content);
        } else {
            $newContent = rtrim($content) . "\n" . $line . "\n";
        }

        if (@file_put_contents($this->envPath, $newContent) === false) {
            return false;
        }

        return true;
    }

    /**
     * Eksportuje backup konfiguracji (delegacja lub nowa logika).
     */
    public function exportBackup(): string
    {
        $config = $this->getConfig();
        $backupPath = DATA_DIR . '/logviewer_backup_' . date('Ymd_His') . '.json';
        
        // W backupie chcemy mieć wszystko, ale plik musi być chroniony
        $this->validateAndWriteJson($backupPath, $config);
        
        return $backupPath;
    }

    /**
     * Rekursywnie usuwa lub maskuje pola wrażliwe z tablicy.
     */
    private function filterSensitiveFields(array $data): array
    {
        $sensitive = ['ssh_password', 'ssh_key_passphrase', 'encryption_key_raw', 'encryption_key'];
        
        foreach ($data as $key => $value) {
            if (in_array($key, $sensitive, true)) {
                $data[$key] = '********';
            } elseif (is_array($value)) {
                $data[$key] = $this->filterSensitiveFields($value);
            }
        }

        return $data;
    }
}
