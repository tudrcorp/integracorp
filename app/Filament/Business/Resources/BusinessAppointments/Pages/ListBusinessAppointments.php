<?php

namespace App\Filament\Business\Resources\BusinessAppointments\Pages;

use App\Filament\Business\Resources\BusinessAppointments\BusinessAppointmentsResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListBusinessAppointments extends ListRecords
{
    protected static string $resource = BusinessAppointmentsResource::class;

    protected static ?string $title = 'Citas de Negocios';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nueva Cita')->icon('heroicon-o-calendar'),
        ];
    }

}
