<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Pages;

use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\TelemedicineConsultationPatientResource;
use App\Models\TelemedicineCase;
use App\Models\TelemedicinePatient;
use App\Support\Telemedicine\ConsultationEditSession;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Redirect;

class ViewTelemedicineConsultationPatient extends ViewRecord
{
    protected static string $resource = TelemedicineConsultationPatientResource::class;

    protected static ?string $title = 'Detalle de Seguimiento';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')
                ->label('Editar')
                ->button()
                ->icon(
                    'heroicon-s-pencil'
                )
                ->color('primary')
                ->action(function () {
                    $record = $this->record;

                    $case = TelemedicineCase::query()->whereKey($record->telemedicine_case_id)->first();
                    $patient = TelemedicinePatient::query()->whereKey($record->telemedicine_patient_id)->first();

                    if ($case === null || $patient === null) {
                        return null;
                    }

                    ConsultationEditSession::storeForEdit($case, $patient, (string) $record->status);

                    return Redirect::route('filament.telemedicina.resources.telemedicine-consultation-patients.edit', ['record' => $record->id]);
                }),

            Action::make('back')
                ->label('Regresar')
                ->button()
                ->icon('heroicon-s-arrow-left')
                ->color('primary')
                ->url(function () {
                    return Redirect::back()->getTargetUrl();
                }),
        ];
    }
}
