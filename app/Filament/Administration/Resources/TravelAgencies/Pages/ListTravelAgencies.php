<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\TravelAgencies\Pages;

use App\Filament\Administration\Resources\TravelAgencies\TravelAgencyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTravelAgencies extends ListRecords
{
    protected static string $resource = TravelAgencyResource::class;

    protected static ?string $title = 'Listado de Agencias de Viajes';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->label('Crear Agencia de Viajes'),
        ];
    }
}
