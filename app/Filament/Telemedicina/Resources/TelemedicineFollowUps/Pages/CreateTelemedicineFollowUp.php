<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineFollowUps\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Telemedicina\Resources\TelemedicineFollowUps\TelemedicineFollowUpResource;
use App\Models\TelemedicineCase;

class CreateTelemedicineFollowUp extends CreateRecord
{
    protected static string $resource = TelemedicineFollowUpResource::class;

    protected static ?string $title = 'Formulario de Seguimiento de Casos';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regresar')
                ->label('Regresar')
                ->button()
                ->icon('heroicon-s-arrow-left')
                ->color('gray')
                ->url(TelemedicineFollowUpResource::getUrl('index')),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {

        $caso = TelemedicineCase::where('id', $data['telemedicine_case_id'])->with('consultations')->first()->toArray();

        $data['telemedicine_patient_id'] = $caso['telemedicine_patient_id'];
        $data['telemedicine_doctor_id'] = $caso['telemedicine_doctor_id'];
        $data['telemedicine_consultation_patient_id'] = $caso['consultations'][0]['id'];

        return $data;
    }
}