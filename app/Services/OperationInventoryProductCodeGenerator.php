<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OperationInventoryProduct;
use Illuminate\Support\Facades\DB;

class OperationInventoryProductCodeGenerator
{
    public const PREFIX = 'TDG-';

    public const PAD_LENGTH = 5;

    public function next(): string
    {
        return DB::transaction(function (): string {
            $maxSequence = OperationInventoryProduct::query()
                ->where('code', 'like', self::PREFIX.'%')
                ->lockForUpdate()
                ->pluck('code')
                ->map(fn (string $code): int => $this->sequenceFromCode($code))
                ->filter(fn (int $sequence): bool => $sequence > 0)
                ->max() ?? 0;

            return $this->format($maxSequence + 1);
        });
    }

    public function format(int $sequence): string
    {
        return self::PREFIX.str_pad((string) max(0, $sequence), self::PAD_LENGTH, '0', STR_PAD_LEFT);
    }

    public function sequenceFromCode(string $code): int
    {
        if (! preg_match('/^'.preg_quote(self::PREFIX, '/').'(\d+)$/', trim($code), $matches)) {
            return 0;
        }

        return (int) $matches[1];
    }
}
