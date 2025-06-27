<?php

namespace App\Filament\Resources\AgencyTypes\Pages;

use App\Filament\Resources\AgencyTypes\AgencyTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAgencyTypes extends ListRecords
{
    protected static string $resource = AgencyTypeResource::class;

    protected static ?string $title = 'TIPOS DE AGENCIAS';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear')
                ->icon('heroicon-s-building-office-2')
        ];
    }
}