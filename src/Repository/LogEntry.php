<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Repository;

/**
 * Log entry model representing a single log line.
 */
class LogEntry
{
    public function __construct(
        public string $timestamp,
        public string $level,
        public string $message,
        public ?string $context = null,
        public ?string $file = null,
        public ?int $line = null
    ) {}

    public function toArray(): array
    {
        return [
            'timestamp' => $this->timestamp,
            'level' => $this->level,
            'message' => $this->message,
            'context' => $this->context,
            'file' => $this->file,
            'line' => $this->line
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['timestamp'] ?? '',
            $data['level'] ?? 'INFO',
            $data['message'] ?? '',
            $data['context'] ?? null,
            $data['file'] ?? null,
            $data['line'] ?? null
        );
    }
}