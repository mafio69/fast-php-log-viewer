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
     * Checks if setup is complete.
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
            $config = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            return isset($config['setup_complete']) && $config['setup_complete'] === true;
        } catch (\JsonException) {
            return false;
        }
    }

    /**
     * Gets current setup status.
     */
    public function getSetupStatus(): array
    {
        $config = $this->getConfig();
        return [
            'state' => $config['setup_state'] ?? 'not_started',
            'steps' => $config['setup_steps'] ?? []
        ];
    }

    /**
     * Gets current setup state.
     */
    public function getSetupState(): string
    {
        $config = $this->getConfig();
        return $config['setup_state'] ?? 'not_started';
    }

    /**
     * Marks setup as complete.
     */
    public function markSetupComplete(): void
    {
        $this->updateConfig([
            'setup_complete' => true,
            'setup_state' => 'complete'
        ]);
    }

    /**
     * Generates a valid UUID v4.
     */
    public function generateInstallationId(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variant [89ab]

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Generates a 64-char hex encryption key.
     */
    public function generateEncryptionKey(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Reads configuration from app_config.json.
     */
    public function getConfig(): array
    {
        if (!file_exists($this->configPath)) {
            return [];
        }

        $content = @file_get_contents($this->configPath);
        if ($content === false) {
            return [];
        }

        try {
            return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            error_log('ConfigManager: Failed to parse config JSON: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Gets public configuration (filtered).
     */
    public function getPublicConfig(): array
    {
        $config = $this->getConfig();
        return $this->filterSensitiveFields($config);
    }

    /**
     * Saves configuration atomically.
     */
    public function saveConfig(array $config): void
    {
        $tmpFile = dirname($this->configPath) . '/.tmp.' . bin2hex(random_bytes(8));
        
        try {
            $json = json_encode($config, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            if (file_put_contents($tmpFile, $json) === false) {
                throw new \RuntimeException('Failed to write temporary config file');
            }

            if (!rename($tmpFile, $this->configPath)) {
                throw new \RuntimeException('Failed to rename temporary config file to final destination');
            }

            @chmod($this->configPath, 0600);
        } catch (\Throwable $e) {
            if (file_exists($tmpFile)) {
                @unlink($tmpFile);
            }
            throw new \RuntimeException('Failed to save config: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Merges partial configuration.
     */
    public function updateConfig(array $partial): void
    {
        $config = $this->getConfig();
        $config = array_replace_recursive($config, $partial);
        $this->saveConfig($config);
    }

    /**
     * Filters sensitive fields from config.
     */
    private function filterSensitiveFields(array $config): array
    {
        $sensitive = ['ssh_password', 'ssh_key', 'encryption_key', 'password'];
        
        foreach ($config as $key => $value) {
            if (in_array($key, $sensitive, true)) {
                $config[$key] = '********';
            } elseif (is_array($value)) {
                $config[$key] = $this->filterSensitiveFields($value);
            }
        }
        
        return $config;
    }
}
