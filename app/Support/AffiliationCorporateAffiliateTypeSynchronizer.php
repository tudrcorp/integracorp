<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use InvalidArgumentException;

final class AffiliationCorporateAffiliateTypeSynchronizer
{
    public function sync(AffiliationCorporate $affiliationCorporate, mixed $affiliationType): int
    {
        if (blank($affiliationType)) {
            throw new InvalidArgumentException('Debe definir el tipo de afiliación antes de sincronizar.');
        }

        return AffiliateCorporate::query()
            ->where('affiliation_corporate_id', $affiliationCorporate->id)
            ->update([
                'affiliation_type' => (string) $affiliationType,
            ]);
    }
}
