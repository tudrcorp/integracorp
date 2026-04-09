<?php

namespace App\Filament\Business\Resources\TravelAgencies\Pages;

use App\Filament\Business\Resources\TravelAgencies\TravelAgencyResource;
use App\Filament\Business\Resources\TravelAgencies\Widgets\TotalTravelAgencyStatOverview;
use App\Filament\Business\Resources\TravelAgencies\Widgets\TravelAgencyForStateChart;
use Filament\Actions\CreateAction;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;

class ListTravelAgencies extends ListRecords
{
    use ExposesTableToWidgets;

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

    protected function getHeaderWidgets(): array
    {
        return [
            TotalTravelAgencyStatOverview::class,
            TravelAgencyForStateChart::class,
        ];
    }
}
