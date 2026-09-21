<?php

declare(strict_types=1);

namespace App\Support\Filament\Administration;

final class InvoiceDocumentNumber
{
    /**
     * Deja solo dígitos para que el PDF anteponga V- (individual) o J- (corporativo).
     */
    public static function digitsOnly(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $normalized) ?? '';

        return $digits === '' ? null : $digits;
    }
}
