<?php

namespace App\Http\Controllers;

use App\Models\OperationCoordinationService;
use App\Models\OperationOnCallUser;
use App\Models\TelemedicineServiceList;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OperationCoordinationServiceController extends Controller
{
    //
    public static function create(array $record, array $doctor, array $patient)
    {

        DB::transaction(function () use ($record, $doctor, $patient) {

            try {

                $colaborador = OperationOnCallUser::where('date_OnCall', now()->format('d/m/Y'))->first();

                $service = TelemedicineServiceList::find($record['telemedicine_service_list_id']);

                $operationCoordinationService = OperationCoordinationService::create([

                    'telemedicine_consultation_patient_id' => $record['id'],
                    'telemedicine_patient_id' => $record['telemedicine_patient_id'],
                    'telemedicine_case_id' => $record['telemedicine_case_id'],
                    'telemedicine_doctor_id' => $doctor['id'],
                    'date_solicitud' => now(),
                    'date_service' => now(),
                    'business_line_id' => $patient['business_line_id'],
                    'business_unit_id' => $patient['business_unit_id'],
                    'reference_number' => $record['code_reference'],
                    'status' => 'EN GESTION',
                    'holder' => $colaborador->name,
                    'ci_holder' => $colaborador->rrhh_colaborador->cedula,
                    'patient' => $record['full_name'],
                    'ci_patient' => $patient['nro_identificacion'],
                    'birth_date_patient' => $patient['birth_date'],
                    'relationship_patient' => 'TITULAR',
                    'age_patient' => $patient['age'],
                    'contractor' => $patient['afilliation_id'] == null ? 'CORPORATIVO' : 'INDIVIDUAL',
                    'state_id' => $patient['state_id'],
                    'city_id' => $patient['city_id'],
                    'address' => $patient['address'],
                    'phone_holder' => $patient['phone'],
                    'symptoms_diagnosis' => $record['diagnostic_impression'] ?? '...',
                    'servicie' => $service->name,
                    // 'specific_service'                          => $record['specific_service'] ?? '...',
                    'supplier_service' => $record['supplier_service'] ?? '...',
                    'farmadoc' => $record['farmadoc'] ?? '...',
                    'type_negotiation' => '...',
                    'status_negotiation' => '...',
                    'neto' => 0.00,
                    'porcen_tdec' => 0,
                    'quote_price' => 0.00,
                    'negotiation' => '...',
                    'porcen_discount' => 0,
                    'price_discount' => 0.00,
                    'quote_number' => '...',
                    'approved_number' => '...',
                    'service_order_number' => 0,
                    'bill_number' => '...',
                    'bill_price' => 0.00,
                    'bill_date' => now()->format('d/m/Y'),
                    'incidence' => 0,
                    'negotiation_description' => '...',
                    'qc_description' => '...',
                    'observations' => $record['observations'] ?? '...',
                    'created_by' => Auth::user()->name,
                    'updated_by' => Auth::user()->name,

                ]);

                // dd($operationCoordinationService['id']);
                return $operationCoordinationService['id'];

            } catch (\Throwable $th) {
                Log::error('Error al crear el servicio de coordinacion: '.$th->getMessage());
                Notification::make()
                    ->title('Error al crear el servicio de coordinacion')
                    ->body($th->getMessage())
                    ->danger()
                    ->send();
                throw $th;
            }
        });
    }
}
