<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Affiliate;
use App\Models\Affiliation;
use InvalidArgumentException;

final class AffiliationAffiliateBusinessContextSynchronizer
{
    public function sync(Affiliation $affiliation, mixed $businessUnitId, mixed $businessLineId): int
    {
        if (blank($businessUnitId) || blank($businessLineId)) {
            throw new InvalidArgumentException('Debe definir la unidad de negocio y la línea de servicio antes de sincronizar.');
        }

        return Affiliate::query()
            ->where('affiliation_id', $affiliation->id)
            ->update([
                'business_unit_id' => (int) $businessUnitId,
                'business_line_id' => (int) $businessLineId,
            ]);
    }
}
