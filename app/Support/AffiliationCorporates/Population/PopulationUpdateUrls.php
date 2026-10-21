<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates\Population;

use App\Filament\Business\Resources\AffiliationCorporates\AffiliationCorporateResource;
use App\Models\CorporatePopulationUpdate;
use Throwable;

/**
 * Enlaces a las pantallas de población (también desde jobs, sin panel activo).
 */
final class PopulationUpdateUrls
{
    public static function index(int $affiliationId): ?string
    {
        try {
            return AffiliationCorporateResource::getUrl('population', ['record' => $affiliationId], panel: 'business');
        } catch (Throwable) {
            return null;
        }
    }

    public static function detail(CorporatePopulationUpdate $update): ?string
    {
        try {
            return AffiliationCorporateResource::getUrl('population-detail', [
                'record' => $update->affiliation_corporate_id,
                'update' => $update->public_id,
            ], panel: 'business');
        } catch (Throwable) {
            return null;
        }
    }
}
