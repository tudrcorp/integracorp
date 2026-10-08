<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\AffiliationCorporates\Pages;

use App\Filament\Administration\Resources\AffiliationCorporates\AffiliationCorporateResource;
use App\Filament\Shared\Affiliations\ManageAffiliationCorporatePayments;

class AffiliationCorporatePayments extends ManageAffiliationCorporatePayments
{
    protected static string $resource = AffiliationCorporateResource::class;

    /** En Administración los pagos aprobados se pueden facturar. */
    public static function allowsInvoicing(): bool
    {
        return true;
    }
}
