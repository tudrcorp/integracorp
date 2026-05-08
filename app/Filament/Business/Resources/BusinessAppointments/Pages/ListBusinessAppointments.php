<?php

namespace App\Filament\Business\Resources\BusinessAppointments\Pages;

use App\Filament\Business\Resources\BusinessAppointments\BusinessAppointmentsResource;
use App\Filament\Business\Resources\BusinessAppointments\Widgets\BusinessAppointmentNotesByUserChart;
use App\Filament\Business\Resources\BusinessAppointments\Widgets\BusinessAppointmentsByStateChart;
use App\Filament\Business\Resources\BusinessAppointments\Widgets\BusinessAppointmentsByStatusChart;
use App\Filament\Business\Resources\BusinessAppointments\Widgets\BusinessAppointmentsKpiOverview;
use Filament\Actions\CreateAction;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;

class ListBusinessAppointments extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = BusinessAppointmentsResource::class;

    protected static ?string $title = 'Citas de Negocios';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nueva Cita')->icon('heroicon-o-calendar'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            BusinessAppointmentsKpiOverview::class,
            BusinessAppointmentsByStatusChart::class,
            BusinessAppointmentsByStateChart::class,
            BusinessAppointmentNotesByUserChart::class,
        ];
    }
}
