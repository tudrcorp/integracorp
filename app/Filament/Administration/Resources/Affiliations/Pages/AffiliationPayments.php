<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Affiliations\Pages;

use App\Filament\Administration\Resources\Affiliations\AffiliationResource;
use App\Filament\Shared\Affiliations\ManageAffiliationPayments;

class AffiliationPayments extends ManageAffiliationPayments
{
    protected static string $resource = AffiliationResource::class;
}
