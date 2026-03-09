<?php

namespace App\Http\Controllers;

use App\Models\TelemedicineMedicalReport;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TelemedicineMedicalReportController extends Controller
{
    //
    public static function create(array $data)
    {

        try {

            if ($data['status'] == 'CONSULTA INICIAL') {
                $report = TelemedicineMedicalReport::create([
                    'telemedicine_case_id' => $data['telemedicine_case_id'],
                    'telemedicine_doctor_id' => $data['telemedicine_doctor_id'],
                    'telemedicine_patient_id' => $data['telemedicine_patient_id'],
                    'telemedicine_consultation_patient_id' => $data['id'],
                    'pa' => $data['pa'],
                    'fc' => $data['fc'],
                    'fr' => $data['fr'],
                    'temp' => $data['temp'],
                    'saturacion' => $data['saturacion'],
                    'peso' => $data['peso'],
                    'estatura' => $data['estatura'],
                    'imc' => $data['imc'],
                    'reason_consultation' => $data['reason_consultation'],
                    'actual_phatology' => $data['actual_phatology'],
                    'background' => $data['background'],
                    'diagnostic_impression' => $data['diagnostic_impression'],
                    'type_service' => $data['telemedicine_service_list_id'],
                    'priority_service' => $data['telemedicine_priority_id'],
                    'observations' => $data['observations'],
                    'created_by' => Auth::user()->name,
                    'updated_by' => Auth::user()->name,
                    'status' => $data['status'],
                ]);
            } else {
                $report = TelemedicineMedicalReport::create([
                    'telemedicine_case_id' => $data['telemedicine_case_id'],
                    'telemedicine_doctor_id' => $data['telemedicine_doctor_id'],
                    'telemedicine_patient_id' => $data['telemedicine_patient_id'],
                    'telemedicine_consultation_patient_id' => $data['id'],
                    'type_service' => $data['telemedicine_service_list_id'],
                    'priority_service' => $data['telemedicine_priority_id'],
                    'observations' => $data['observations'],
                    'created_by' => Auth::user()->name,
                    'updated_by' => Auth::user()->name,
                    'status' => $data['status'],
                    'cuestion_1' => $data['cuestion_1'],
                    'cuestion_2' => $data['cuestion_2'],
                    'cuestion_3' => $data['cuestion_3'],
                    'cuestion_4' => $data['cuestion_4'],
                    'cuestion_5' => $data['cuestion_5'],
                ]);
            }

            if ($report) {
                Notification::make()
                    ->title('Reporte médico creado correctamente')
                    ->body('El reporte médico se ha creado correctamente')
                    ->icon('heroicon-c-check-circle')
                    ->iconColor('success')
                    ->success()
                    ->send();
            }

        } catch (\Throwable $th) {
            Log::error('Error al crear el reporte médico: '.$th->getMessage());
            Notification::make()
                ->title('ERROR')
                ->body('Error al crear el reporte médico')
                ->icon('heroicon-m-tag')
                ->iconColor('danger')
                ->danger()
                ->send();
        }

    }
}
