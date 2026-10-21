<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Affiliate;
use App\Models\Affiliation;
use App\Support\Affiliations\AffiliateSpecificBusinessUnitChanger;
use InvalidArgumentException;

final class AffiliationAffiliateBusinessContextSynchronizer
{
    public function sync(Affiliation $affiliation, mixed $businessUnitId, mixed $businessLineId, mixed $specificBusinessUnit = null): int
    {
        if (blank($businessUnitId) || blank($businessLineId)) {
            throw new InvalidArgumentException('Debe definir la unidad de negocio y la línea de servicio antes de sincronizar.');
        }

        $updated = Affiliate::query()
            ->where('affiliation_id', $affiliation->id)
            ->update([
                'business_unit_id' => (int) $businessUnitId,
                'business_line_id' => (int) $businessLineId,
            ]);

        // La unidad específica fijada a mano por un analista no se pisa.
        $specificUpdated = Affiliate::query()
            ->where('affiliation_id', $affiliation->id)
            ->when(
                AffiliateSpecificBusinessUnitChanger::lockColumnExists('affiliates'),
                static fn ($query) => $query->whereNull('specific_business_unit_locked_at'),
            )
            ->update(['specific_business_unit' => self::normalizeSpecificBusinessUnit($specificBusinessUnit)]);

        return max($updated, $specificUpdated);
    }

    public static function normalizeSpecificBusinessUnit(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
