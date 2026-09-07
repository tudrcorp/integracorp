<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\Pages;

use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\TelemedicineHistoryPatientResource;
use App\Models\TelemedicineCase;
use App\Models\TelemedicinePatient;
use App\Support\Filament\FilamentIosButton;
use App\Support\Telemedicine\ConsultationCreateRoute;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewTelemedicineHistoryPatient extends ViewRecord
{
    protected static string $resource = TelemedicineHistoryPatientResource::class;

    protected static ?string $title = 'Información de Paciente';

    protected function getHeaderActions(): array
    {
        return [

            EditAction::make()
                ->label('Editar Historia Clínica')
                ->color('warning')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('warning'),
                ]),

            Action::make('back_to_consultations')
                ->label('Regresar a Consultas')
                ->icon(Heroicon::ArrowLeft)
                ->color('warning')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('warning'),
                ])
                ->action(function () {
                    $patient = session()->get('patient');
                    $case = session()->get('case');

                    if (! $patient instanceof TelemedicinePatient) {
                        $patientId = (int) ($this->getRecord()->telemedicine_patient_id ?? 0);
                        $patient = $patientId > 0
                            ? TelemedicinePatient::query()->find($patientId)
                            : null;
                    }

                    if (! $case instanceof TelemedicineCase) {
                        $case = null;
                    }

                    if (! $patient instanceof TelemedicinePatient) {
                        return redirect()->route('filament.telemedicina.pages.dashboard');
                    }

                    return redirect()->to(ConsultationCreateRoute::url($patient, $case));
                }),

            Action::make('back')
                ->label('Dashboard')
                ->icon(Heroicon::Home)
                ->color('success')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('success'),
                ])
                ->url(route('filament.telemedicina.pages.dashboard')),
        ];
    }
}
