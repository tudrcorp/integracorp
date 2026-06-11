<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Pages;

use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Concerns\HasMedicamentosStepInfoModal;
use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\TelemedicineConsultationPatientResource;
use App\Jobs\GeneratePdfEspecialista;
use App\Jobs\GeneratePdfImagenologia;
use App\Jobs\GeneratePdfLaboratorio;
use App\Jobs\GeneratePdfMedicamentos;
use App\Jobs\SendTelemedicinaDocument;
use App\Models\OperationInventory;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineDoctor;
use App\Models\TelemedicineListLaboratory;
use App\Models\TelemedicineListSpecialist;
use App\Models\TelemedicineListStudy;
use App\Models\TelemedicinePatient;
use App\Models\TelemedicinePatientLab;
use App\Models\TelemedicinePatientMedications;
use App\Models\TelemedicinePatientSpecialty;
use App\Models\TelemedicinePatientStudy;
use App\Support\Telemedicine\TelemedicineMedicationCoverage;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditTelemedicineConsultationPatient extends EditRecord
{
    use HasMedicamentosStepInfoModal;

    protected static string $resource = TelemedicineConsultationPatientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function getListeners(): array
    {
        return [
            'open-medicamentos-step-info-modal' => 'openMedicamentosStepInfoModal',
        ];
    }

    /**
     * Creamos el registro de los medicamentos
     * asignados por el medico en la consulta
     *
     * @author TuDrEnCasa
     *
     * @since 1.0
     *
     * @version 1.0
     *
     * @param  array  $data,  array $medications
     * @return void
     */
    protected function afterCreate()
    {
        try {

            $record = $this->getRecord()->toArray();
            // dd($record, $this->data);

            $doctor = TelemedicineDoctor::where('id', $record['telemedicine_doctor_id'])->first()->toArray();

            $patient = TelemedicinePatient::where('id', $record['telemedicine_patient_id'])->first()->toArray();
            // dd($patient);

            $feedbackOne = session()->get('feedbackOne');

            $medicationsArr = session()->get('medications') ?? [];
            $labsArr = session()->get('labs') ?? [];
            $otherLabsArr = session()->get('other_labs') ?? [];
            $studiesArr = session()->get('studies') ?? [];
            $otherStudiesArr = session()->get('other_studies') ?? [];
            $consultSpecialistArr = session()->get('consult_specialist') ?? [];
            $otherSpecialistArr = session()->get('other_specialist') ?? [];

            if ($feedbackOne != true) {
                $finalArrLabs = array_merge($labsArr, $otherLabsArr);
                $finalArrStudies = array_merge($studiesArr, $otherStudiesArr);
                $finalArrSpecialist = array_merge($consultSpecialistArr, $otherSpecialistArr);
            }

            // dd($finalArrLabs, $finalArrStudies, $finalArrSpecialist);

            // Arreglo de medicamento
            if (! empty($medicationsArr)) {
                for ($i = 0; $i < count($medicationsArr); $i++) {
                    $row = $medicationsArr[$i];

                    if (! is_array($row)) {
                        continue;
                    }

                    $inventoryId = filled($row['operation_inventory_id'] ?? null)
                        ? (int) $row['operation_inventory_id']
                        : null;
                    $manualMedicine = filled($row['medicines'] ?? null) ? (string) $row['medicines'] : null;

                    if ($manualMedicine === null && $inventoryId === null) {
                        continue;
                    }

                    $medications = new TelemedicinePatientMedications;
                    $medications->telemedicine_consultation_patient_id = $record['id'];
                    $medications->telemedicine_patient_id = $record['telemedicine_patient_id'];
                    $medications->telemedicine_case_id = $record['telemedicine_case_id'];
                    $medications->telemedicine_doctor_id = $record['telemedicine_doctor_id'];
                    $medications->medicine = $manualMedicine ?? OperationInventory::query()->whereKey($inventoryId)->value('name');
                    $medications->indications = $row['indications'];
                    $medications->duration = $row['duration'];
                    $medications->telemedicine_priority_id = $record['telemedicine_priority_id'];
                    $medications->operation_inventory_id = $inventoryId;
                    $medications->is_covered = TelemedicineMedicationCoverage::coverageForPersist($inventoryId);
                    $medications->assigned_by = Auth::user()->id;
                    $medications->save();
                }

                /**
                 * Informacion para el pdf
                 * -------------------------------------------------------------------------------------------
                 *
                 * @typeDoc = Tipo de documento a generar
                 *
                 * @doctor = Informacion del doctor
                 *
                 * @recod = Informacion de la consulta
                 */
                $typeDoc = 'medicamentos';

                $data = [
                    'fecha' => now()->format('d/m/Y'),
                    'code_reference' => $record['code_reference'],
                    'name_patiente' => $record['full_name'],
                    'ci_patiente' => $record['nro_identificacion'],
                    'age_patiente' => $patient['age'],
                    'medicationsArr' => $medicationsArr,
                    'code_cm' => $doctor['code_cm'],
                    'code_mpps' => $doctor['code_mpps'],
                    'signature' => $doctor['signature'],
                    'telemedicine_case_id' => $record['telemedicine_case_id'],
                    'telemedicine_consultation_id' => $record['id'],
                    'telemedicine_patient_id' => $record['telemedicine_patient_id'],
                    'signature' => $doctor['signature'],
                ];

                GeneratePdfMedicamentos::dispatch($data, Auth::user(), $typeDoc)->onQueue('telemedicina');
            }

            // Arreglo de Laboratorios
            if (! empty($finalArrLabs)) {
                // Log::info('Lab: ' . json_encode($medicationsArr));
                for ($i = 0; $i < count($finalArrLabs); $i++) {
                    $labs = new TelemedicinePatientLab;
                    $labs->telemedicine_consultation_patient_id = $record['id'];
                    $labs->telemedicine_patient_id = $record['telemedicine_patient_id'];
                    $labs->telemedicine_case_id = $record['telemedicine_case_id'];
                    $labs->telemedicine_doctor_id = $record['telemedicine_doctor_id'];
                    $labs->laboratory = $finalArrLabs[$i];
                    $labs->type = TelemedicineListLaboratory::where('name', $finalArrLabs[$i])->first()->type;
                    $labs->assigned_by = Auth::user()->id;
                    $labs->save();
                }

                /**
                 * Informacion para el pdf
                 * -------------------------------------------------------------------------------------------
                 *
                 * @typeDoc = Tipo de documento a generar
                 *
                 * @doctor = Informacion del doctor
                 *
                 * @recod = Informacion de la consulta
                 */
                $typeDoc = 'laboratorios';

                $data = [
                    'fecha' => now()->format('d/m/Y'),
                    'code_reference' => $record['code_reference'],
                    'name_patiente' => $record['full_name'],
                    'ci_patiente' => $record['nro_identificacion'],
                    'age_patiente' => $patient['age'],
                    'labs' => $record['labs'],
                    'code_cm' => $doctor['code_cm'],
                    'code_mpps' => $doctor['code_mpps'],
                    'signature' => $doctor['signature'],
                    'telemedicine_case_id' => $record['telemedicine_case_id'],
                    'telemedicine_consultation_id' => $record['id'],
                    'telemedicine_patient_id' => $record['telemedicine_patient_id'],
                    'signature' => $doctor['signature'],
                ];

                GeneratePdfLaboratorio::dispatch($data, Auth::user(), $typeDoc)->onQueue('telemedicina');
            }

            // Arreglo de Estudios
            if (! empty($finalArrStudies)) {
                // Log::info('Estudios: ' . json_encode($medicationsArr));
                for ($i = 0; $i < count($finalArrStudies); $i++) {
                    $study = new TelemedicinePatientStudy;
                    $study->telemedicine_consultation_patient_id = $record['id'];
                    $study->telemedicine_patient_id = $record['telemedicine_patient_id'];
                    $study->telemedicine_case_id = $record['telemedicine_case_id'];
                    $study->telemedicine_doctor_id = $record['telemedicine_doctor_id'];
                    $study->study = $finalArrStudies[$i];
                    $study->assigned_by = Auth::user()->id;
                    $study->type = TelemedicineListStudy::where('name', $finalArrStudies[$i])->first()->type;
                    $study->save();
                }

                /**
                 * Informacion para el pdf
                 * -------------------------------------------------------------------------------------------
                 *
                 * @typeDoc = Tipo de documento a generar
                 *
                 * @doctor = Informacion del doctor
                 *
                 * @recod = Informacion de la consulta
                 */
                $typeDoc = 'imagenologia';

                $data = [
                    'fecha' => now()->format('d/m/Y'),
                    'code_reference' => $record['code_reference'],
                    'name_patiente' => $record['full_name'],
                    'ci_patiente' => $record['nro_identificacion'],
                    'age_patiente' => $patient['age'],
                    'studies' => $record['studies'],
                    'code_cm' => $doctor['code_cm'],
                    'code_mpps' => $doctor['code_mpps'],
                    'signature' => $doctor['signature'],
                    'telemedicine_case_id' => $record['telemedicine_case_id'],
                    'telemedicine_consultation_id' => $record['id'],
                    'telemedicine_patient_id' => $record['telemedicine_patient_id'],
                    'phone' => $patient['phone'],
                    'signature' => $doctor['signature'],
                ];

                // Bus::chain([

                //     new GeneratePdfImagenologia($data, Auth::user(), $typeDoc),

                //     new SendTelemedicinaDocument($data['telemedicine_patient_id'], $data['telemedicine_case_id'], Auth::user(), $patient['phone'], $typeDoc),

                // ])->onQueue('telemedicina')->dispatch();

                GeneratePdfImagenologia::dispatch($data, Auth::user(), $typeDoc)->onQueue('telemedicina');
            }

            // Arreglo Especialistas
            if (! empty($finalArrSpecialist)) {
                // Log::info('Especialista: ' . json_encode($medicationsArr));
                for ($i = 0; $i < count($finalArrSpecialist); $i++) {
                    $specialist = new TelemedicinePatientSpecialty;
                    $specialist->telemedicine_consultation_patient_id = $record['id'];
                    $specialist->telemedicine_patient_id = $record['telemedicine_patient_id'];
                    $specialist->telemedicine_case_id = $record['telemedicine_case_id'];
                    $specialist->telemedicine_doctor_id = $record['telemedicine_doctor_id'];
                    $specialist->specialty = $finalArrSpecialist[$i];
                    $specialist->assigned_by = Auth::user()->id;
                    $specialist->type = TelemedicineListSpecialist::where('name', $finalArrSpecialist[$i])->first()->type;
                    $specialist->save();
                }

                /**
                 * Informacion para el pdf
                 * -------------------------------------------------------------------------------------------
                 *
                 * @typeDoc = Tipo de documento a generar
                 *
                 * @doctor = Informacion del doctor
                 *
                 * @recod = Informacion de la consulta
                 */
                $typeDoc = 'especialista';

                $data = [
                    'fecha' => now()->format('d/m/Y'),
                    'code_reference' => $record['code_reference'],
                    'name_patiente' => $record['full_name'],
                    'ci_patiente' => $record['nro_identificacion'],
                    'age_patiente' => $patient['age'],
                    'consultSpecialistArr' => $consultSpecialistArr,
                    'code_cm' => $doctor['code_cm'],
                    'code_mpps' => $doctor['code_mpps'],
                    'signature' => $doctor['signature'],
                    'telemedicine_case_id' => $record['telemedicine_case_id'],
                    'telemedicine_consultation_id' => $record['id'],
                    'telemedicine_patient_id' => $record['telemedicine_patient_id'],
                    'signature' => $doctor['signature'],
                ];
                // dd($data);

                GeneratePdfEspecialista::dispatch($data, Auth::user(), $typeDoc)->onQueue('telemedicina');
            }

            // ...Limpio la variable de sesion
            session()->forget('medications');
            session()->forget('labs');
            session()->forget('other_labs');
            session()->forget('studies');
            session()->forget('other_studies');
            session()->forget('consult_specialist');
            session()->forget('other_specialist');

            // ...Activacion de la clave roja
            session()->forget('redCode');

            // ...Limpio la variable de sesion que se generar al momento acceder al caso para la primera consulta
            session()->forget('case');
            session()->forget('patient');
            session()->forget('redCode');

            // ...Limpio la variable de sesion que se crea cuando asociamos algun antecedente de la lista
            session()->forget('patologicalHistorySelected');

            // Actualizo el estatus del

            if (isset($feedbackOne) && $feedbackOne == true) {
                dd($record);
                // Actualizamos la informacion en la tabla de casos
                $case = TelemedicineCase::where('id', $record['telemedicine_case_id'])->first();
                $case->telemedicine_priority_id = isset($record['telemedicine_priority_id']) ? $record['telemedicine_priority_id'] : null;
                // $case->telemedicine_service_list_id = isset($record['telemedicine_service_list_id']) ? $record['telemedicine_service_list_id'] : null;
                $case->telemedicine_service_list_id = isset($record['telemedicine_service_list_drift_id']) ? $record['telemedicine_service_list_drift_id'] : null;
                $case->updated_at = now();
                $case->status = 'ALTA MEDICA';
                $case->save();

                // Actualizamos la informacion en la tabla de consultas
                $consult = TelemedicineConsultationPatient::where('id', $record['id'])->first();
                $consult->updated_at = now();
                $consult->status = 'ALTA MEDICA';
                $consult->save();

                session()->forget('feedbackOne');
            } else {
                $case = TelemedicineCase::where('id', $record['telemedicine_case_id'])->first();
                $case->telemedicine_priority_id = isset($record['telemedicine_priority_id']) ? $record['telemedicine_priority_id'] : null;
                $case->updated_at = now();
                $case->status = 'EN SEGUIMIENTO';
                $case->save();
            }

            // Si el servicio es una telemedicina estandar enviamos la notificacion y el documento
            if ($this->data['telemedicine_service_list_id'] == 1) {

                $this->sendNotifications($record);

                SendTelemedicinaDocument::dispatch($data['telemedicine_patient_id'], $data['telemedicine_case_id'], Auth::user(), $patient['phone'], $typeDoc)->onQueue('telemedicina');
            }

            // code...
        } catch (\Throwable $th) {
            dd($th);
        }
    }
}
