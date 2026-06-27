<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Config;

use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Throwable;

class ConfigManager
{
    private bool $loggingEnabled = true;

    public function __construct(
        private readonly string $configPath,
        private readonly string $envPath
    ) {
    }

    public function setLogging(bool $enabled): void
    {
        $this->loggingEnabled = $enabled;
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
        } catch (JsonException) {
            return false;
        }
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
            if ($this->loggingEnabled) {
                error_log("Failed to read config file: {$this->configPath}");
            }
            return [];
        }

        try {
            return json_decode($content, true, 512, JSON_THROW_ON_ERROR) ?: [];
        } catch (JsonException $e) {
            if ($this->loggingEnabled) {
                error_log("Invalid JSON in config file: {$this->configPath}. Error: ".$e->getMessage());
            }
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
     * Atomowy zapis JSON: tmp-file + verify + rename + chmod 0600.
     */
    private function validateAndWriteJson(string $path, array $data): void
    {
        $tmpPath = $path . '.tmp.' . bin2hex(random_bytes(8));
        
        try {
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            
            if (file_put_contents($tmpPath, $json) === false) {
                throw new RuntimeException("Failed to write temporary config file: $tmpPath");
            }

            // Weryfikacja zapisanego pliku
            $verifyContent = file_get_contents($tmpPath);
            json_decode($verifyContent, true, 512, JSON_THROW_ON_ERROR);

            if (!rename($tmpPath, $path)) {
                throw new RuntimeException("Failed to rename temporary config file to: $path");
            }

            chmod($path, 0600);

        } catch (Throwable $e) {
            if (file_exists($tmpPath)) {
                @unlink($tmpPath);
            }
            throw new RuntimeException("Atomic JSON write failed for $path: " . $e->getMessage());
        }
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
            throw new InvalidArgumentException("Invalid encryption key format. Expected 64-char hex.");
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
