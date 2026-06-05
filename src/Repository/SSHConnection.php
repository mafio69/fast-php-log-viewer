<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Repository;

/**
 * SSH connection model.
 */
class SSHConnection
{
    public function __construct(
        public ?int $id = null,
        public string $name = '',
        public string $host = '',
        public int $port = 22,
        public string $username = '',
        public ?string $password = null,
        public ?string $keyPath = null,
        public ?string $keyPassphrase = null
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'password' => $this->password,
            'keyPath' => $this->keyPath,
            'keyPassphrase' => $this->keyPassphrase
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'] ?? null,
            $data['name'] ?? '',
            $data['host'] ?? '',
            $data['port'] ?? 22,
            $data['username'] ?? '',
            $data['password'] ?? null,
            $data['keyPath'] ?? null,
            $data['keyPassphrase'] ?? null
        );
    }
}