<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineFollowUps\Pages;

use Filament\Actions\Action;
use App\Models\TelemedicineCase;
use Filament\Resources\Pages\CreateRecord;
use App\Models\TelemedicinePatientMedications;
use App\Filament\Telemedicina\Resources\TelemedicineFollowUps\TelemedicineFollowUpResource;

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
        isset($data['medications']) ? session()->put('medications', $data['medications']) : null;

        $caso = TelemedicineCase::where('id', $data['telemedicine_case_id'])->with('consultations')->first()->toArray();
        $data['code']                       = $caso['code'];
        $data['telemedicine_patient_id']    = $caso['telemedicine_patient_id'];
        $data['telemedicine_doctor_id']     = $caso['telemedicine_doctor_id'];
        $data['telemedicine_consultation_patient_id'] = $caso['consultations'][0]['id'];

        return $data;
    }

    /**
     * Creamos el registro de los medicamentos
     * asignados por el medico en la consulta
     * 
     * @return void
     * @author TuDrEnCasa
     * @since 1.0
     * @version 1.0
     * 
     * @param array $data, array $medications
     * 
     */
    protected function afterCreate()
    {
        try {

            $array = session()->get('medications');
            
            if (empty($array)) {
                return;
            }

            $record = $this->getRecord()->toArray();

            for ($i = 0; $i < count($array); $i++) {
                // dd($medications[1]['indications']);
                $medications = new TelemedicinePatientMedications();
                $medications->telemedicine_patient_id               = $record['telemedicine_patient_id'];
                $medications->telemedicine_case_id                  = $record['telemedicine_case_id'];
                $medications->telemedicine_doctor_id                = $record['telemedicine_doctor_id'];
                $medications->telemedicine_follow_up_id             = $record['id'];
                $medications->medicine                              = $array[$i]['medicines'];
                $medications->indications                           = $array[$i]['indications'];
                $medications->save();
            }

            //...Limpio la variable de sesion
            session()->forget('medications');

            //code...
        } catch (\Throwable $th) {
            dd($th);
        }
    }
}