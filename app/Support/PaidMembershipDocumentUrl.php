<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\CreditReconciliation;

final class PaidMembershipDocumentUrl
{
    public static function from(?string $documento): string
    {
        $documento = (string) $documento;

        return str_starts_with($documento, 'http')
            ? $documento
            : asset('storage/'.$documento);
    }

    public static function fromReconciliation(CreditReconciliation $record): ?string
    {
        $receipt = $record->paidMembership ?? $record->paidMembershipCorporate;

        if ($receipt === null) {
            return null;
        }

        foreach (['document_ves', 'document_usd'] as $attribute) {
            $value = $receipt->{$attribute} ?? null;

            if (filled($value) && $value !== 'N/A') {
                return self::from((string) $value);
            }
        }

        return null;
    }
}
