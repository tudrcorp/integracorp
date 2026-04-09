<?php

namespace App\Http\Controllers;

use App\Models\TelemedicineConsultationPatient;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class TelemedicineConsultationPatientController extends Controller
{
    public static function createNextConsultation(array $record, array $doctor, array $patient)
    {
        try {

            // code...
            $nextConsultation = new TelemedicineConsultationPatient;
            $nextConsultation->telemedicine_case_id = $record['telemedicine_case_id'];
            $nextConsultation->telemedicine_case_code = $record['telemedicine_case_code'];
            $nextConsultation->telemedicine_patient_id = $record['telemedicine_patient_id'];
            $nextConsultation->telemedicine_doctor_id = $record['telemedicine_doctor_id'];
            $nextConsultation->telemedicine_priority_id = $record['telemedicine_priority_id'];
            $nextConsultation->assigned_by = $record['assigned_by'];
            /**
             * Seleccionamos el servicio de la consulta actual
             * y se lo asignamos a la siguiente consulta
             */
            $nextConsultation->telemedicine_service_list_id = $record['telemedicine_service_list_drift_id'];

            /**
             * Seleccionamos el nombre y la cedula del paciente
             * y se lo asignamos a la siguiente consulta
             */
            $nextConsultation->full_name = $patient['full_name'];
            $nextConsultation->nro_identificacion = $patient['nro_identificacion'];
            $nextConsultation->code_reference = 'REF-'.rand(100000, 999999);

            /**
             * Si el siguiente servicio que se deriva de la consulta es un AMD
             * entoces el estatus del nuevo servicio de AMD debe ser 'CONSULTA INICIAL'
             */
            if ($record['telemedicine_service_list_drift_id'] == 2) {
                $nextConsultation->status = 'CONSULTA INICIAL';
            } else {
                $nextConsultation->status = 'EN SEGUIMIENTO';
            }

            $nextConsultation->save();

        } catch (\Throwable $th) {
            // throw $th;
            Log::error('Error al crear la siguiente consulta: '.$th->getMessage());

            Notification::make()
                ->title('Error al crear la siguiente consulta')
                ->body($th->getMessage())
                ->icon('heroicon-m-tag')
                ->iconColor('danger')
                ->send();

        }
    }
}
