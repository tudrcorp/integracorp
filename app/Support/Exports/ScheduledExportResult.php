<?php

declare(strict_types=1);

namespace App\Support\Exports;

final readonly class ScheduledExportResult
{
    public function __construct(
        public string $filename,
        public string $absolutePath,
        public string $publicRelativePath,
        public int $bytes,
        public float $durationSeconds,
        public int $affiliationCount = 0,
        public int $affiliateCount = 0,
        public int $rowCount = 0,
        public int $planCount = 0,
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
