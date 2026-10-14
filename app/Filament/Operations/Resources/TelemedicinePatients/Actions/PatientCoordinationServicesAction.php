<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\TelemedicinePatients\Actions;

use App\Filament\Operations\Resources\TelemedicinePatients\Pages\PatientCoordinationServices;
use App\Filament\Operations\Resources\TelemedicinePatients\TelemedicinePatientResource;
use App\Models\TelemedicinePatient;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * Entrada desde la ficha del paciente a la lista de todos sus servicios
 * ({@see PatientCoordinationServices}).
 */
final class PatientCoordinationServicesAction
{
    public static function make(): Action
    {
        return Action::make('patient_coordination_services')
            ->label('Servicios del paciente')
            ->icon(Heroicon::OutlinedSquare3Stack3d)
            ->color('info')
            ->visible(fn (): bool => PatientCoordinationServices::canAccess())
            ->url(fn (TelemedicinePatient $record): string => TelemedicinePatientResource::getUrl('services', ['record' => $record]));
    }
}
