<?php

namespace App\Filament\Business\Resources\Benefits\Pages;

use App\Filament\Business\Resources\Benefits\BenefitResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBenefit extends CreateRecord
{
    protected static string $resource = BenefitResource::class;
}
