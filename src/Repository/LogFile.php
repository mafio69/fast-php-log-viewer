<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Repository;

/**
 * Log file model representing a log file.
 */
class LogFile
{
    public function __construct(
        public string $path,
        public string $name,
        public ?string $directory = null,
        public ?int $size = null,
        public ?string $modified = null
    ) {}

    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'name' => $this->name,
            'directory' => $this->directory,
            'size' => $this->size,
            'modified' => $this->modified
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['path'] ?? '',
            $data['name'] ?? '',
            $data['directory'] ?? null,
            $data['size'] ?? null,
            $data['modified'] ?? null
        );
    }
}