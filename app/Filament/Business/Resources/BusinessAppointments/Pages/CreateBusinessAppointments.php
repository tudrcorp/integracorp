<?php

namespace App\Filament\Business\Resources\BusinessAppointments\Pages;

use App\Filament\Business\Resources\BusinessAppointments\BusinessAppointmentsResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBusinessAppointments extends CreateRecord
{
    protected static string $resource = BusinessAppointmentsResource::class;

    protected static ?string $title = 'Crear Cita';
}
