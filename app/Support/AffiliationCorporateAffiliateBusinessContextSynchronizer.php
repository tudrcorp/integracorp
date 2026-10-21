<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Support\Affiliations\AffiliateSpecificBusinessUnitChanger;
use InvalidArgumentException;

final class AffiliationCorporateAffiliateBusinessContextSynchronizer
{
    public function sync(AffiliationCorporate $affiliationCorporate, mixed $businessUnitId, mixed $businessLineId, mixed $specificBusinessUnit = null): int
    {
        if (blank($businessUnitId) || blank($businessLineId)) {
            throw new InvalidArgumentException('Debe definir la unidad de negocio y la línea de servicio antes de sincronizar.');
        }

        $updated = AffiliateCorporate::query()
            ->where('affiliation_corporate_id', $affiliationCorporate->id)
            ->update([
                'business_unit_id' => (int) $businessUnitId,
                'business_line_id' => (int) $businessLineId,
            ]);

        // La unidad específica fijada a mano por un analista no se pisa.
        $specificUpdated = AffiliateCorporate::query()
            ->where('affiliation_corporate_id', $affiliationCorporate->id)
            ->when(
                AffiliateSpecificBusinessUnitChanger::lockColumnExists('affiliate_corporates'),
                static fn ($query) => $query->whereNull('specific_business_unit_locked_at'),
            )
            ->update(['specific_business_unit' => AffiliationAffiliateBusinessContextSynchronizer::normalizeSpecificBusinessUnit($specificBusinessUnit)]);

        return max($updated, $specificUpdated);
    }
}
