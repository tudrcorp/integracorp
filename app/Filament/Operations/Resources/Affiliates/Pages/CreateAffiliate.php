<?php

namespace App\Filament\Operations\Resources\Affiliates\Pages;

use App\Filament\Operations\Resources\Affiliates\AffiliateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAffiliate extends CreateRecord
{
    protected static string $resource = AffiliateResource::class;
}
