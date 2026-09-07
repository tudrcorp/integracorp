<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\Pages;

use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\TelemedicineHistoryPatientResource;
use App\Models\TelemedicineCase;
use App\Models\TelemedicinePatient;
use App\Support\Filament\FilamentIosButton;
use App\Support\Telemedicine\ConsultationCreateRoute;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditTelemedicineHistoryPatient extends EditRecord
{
    protected static string $resource = TelemedicineHistoryPatientResource::class;

    protected static ?string $title = 'Edición de historia clínica';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back_to_view')
                ->label('Resumen Historia Clínica')
                ->icon(Heroicon::ArrowLeft)
                ->color('primary')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('primary'),
                ])
                ->url(fn (): string => TelemedicineHistoryPatientResource::getUrl('view', ['record' => $this->getRecord()])),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        session()->put('redCode', false);

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
            return route('filament.telemedicina.pages.dashboard');
        }

        return ConsultationCreateRoute::url($patient, $case);
    }
}
