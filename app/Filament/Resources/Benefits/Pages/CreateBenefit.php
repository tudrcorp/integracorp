<?php

namespace App\Filament\Resources\Benefits\Pages;

use App\Filament\Resources\Benefits\BenefitResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBenefit extends CreateRecord
{
    protected static string $resource = BenefitResource::class;

    protected static ?string $title = 'CREAR BENEFICIO';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}