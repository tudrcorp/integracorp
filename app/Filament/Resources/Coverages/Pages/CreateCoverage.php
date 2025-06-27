<?php

namespace App\Filament\Resources\Coverages\Pages;

use App\Filament\Resources\Coverages\CoverageResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCoverage extends CreateRecord
{
    protected static string $resource = CoverageResource::class;

    protected static ?string $title = 'CREAR COBERTURAS';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}