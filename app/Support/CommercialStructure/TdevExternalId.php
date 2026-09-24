<?php

declare(strict_types=1);

namespace App\Support\CommercialStructure;

final class TdevExternalId
{
    public static function isRequired(mixed $commissionTdev): bool
    {
        if ($commissionTdev === null || $commissionTdev === '') {
            return false;
        }

        $normalized = str_replace(',', '.', trim((string) $commissionTdev));

        if (! is_numeric($normalized)) {
            return false;
        }

        return (float) $normalized > 0;
    }
}
