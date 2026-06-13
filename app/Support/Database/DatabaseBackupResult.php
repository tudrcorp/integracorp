<?php

declare(strict_types=1);

namespace App\Support\Database;

final readonly class DatabaseBackupResult
{
    public function __construct(
        public string $filename,
        public string $absolutePath,
        public string $publicRelativePath,
        public int $bytes,
        public string $driver,
        public string $database,
        public float $durationSeconds,
    ) {}

    public function formattedSize(): string
    {
        if ($this->bytes >= 1048576) {
            return number_format($this->bytes / 1048576, 2).' MB';
        }

        if ($this->bytes >= 1024) {
            return number_format($this->bytes / 1024, 2).' KB';
        }

        return $this->bytes.' bytes';
    }
}
