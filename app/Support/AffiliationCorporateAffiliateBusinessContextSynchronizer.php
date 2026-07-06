<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use InvalidArgumentException;

final class AffiliationCorporateAffiliateBusinessContextSynchronizer
{
    public function sync(AffiliationCorporate $affiliationCorporate, mixed $businessUnitId, mixed $businessLineId): int
    {
        if (blank($businessUnitId) || blank($businessLineId)) {
            throw new InvalidArgumentException('Debe definir la unidad de negocio y la línea de servicio antes de sincronizar.');
        }

        return AffiliateCorporate::query()
            ->where('affiliation_corporate_id', $affiliationCorporate->id)
            ->update([
                'business_unit_id' => (int) $businessUnitId,
                'business_line_id' => (int) $businessLineId,
            ]);
    }
}
