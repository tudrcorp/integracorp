<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\OperationMedicalAppointments\Pages;

use App\Filament\Operations\Resources\OperationMedicalAppointments\OperationMedicalAppointmentResource;
use Filament\Resources\Pages\ManageRecords;

class ManageOperationMedicalAppointments extends ManageRecords
{
    protected static string $resource = OperationMedicalAppointmentResource::class;

    protected static ?string $title = 'Citas Médicas';

    protected function getHeaderActions(): array
    {
        return [];
    }
}
