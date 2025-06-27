<?php

namespace App\Filament\Resources\Plans\Pages;

use App\Filament\Resources\Plans\PlanResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePlan extends CreateRecord
{
    protected static string $resource = PlanResource::class;

    protected static ?string $title = 'Crear Plan';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}