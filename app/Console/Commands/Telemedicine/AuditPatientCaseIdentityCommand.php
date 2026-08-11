<?php

declare(strict_types=1);

namespace App\Console\Commands\Telemedicine;

use App\Models\OperationCoordinationService;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Support\Telemedicine\TelemedicineCaseIdentity;
use App\Support\Telemedicine\TelemedicinePatientIdentity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Command\Command as CommandAlias;

class AuditPatientCaseIdentityCommand extends Command
{
    protected $signature = 'telemedicine:audit-patient-case-identity
                            {--csv= : Ruta relativa en storage/app para exportar CSV}';

    protected $description = 'Lista inconsistencias de identidad paciente ↔ caso/consulta/servicio (sin auto-reparar).';

    public function handle(): int
    {
        $caseRows = $this->caseMismatchRows();
        $consultationRows = $this->consultationMismatchRows();
        $serviceRows = $this->serviceMismatchRows();

        $total = $caseRows->count() + $consultationRows->count() + $serviceRows->count();

        if ($total === 0) {
            $this->info('No se encontraron inconsistencias de identidad paciente ↔ caso/consulta/servicio.');

            return CommandAlias::SUCCESS;
        }

        $this->warn("Se encontraron {$total} inconsistencia(s):");

        if ($caseRows->isNotEmpty()) {
            $this->line('Casos (patient_name ≠ paciente FK):');
            $this->table(
                ['case_id', 'case_code', 'patient_id', 'case_patient_name', 'patient_full_name', 'patient_ci', 'status'],
                $caseRows->map(fn (array $row): array => [
                    $row['case_id'],
                    $row['case_code'],
                    $row['telemedicine_patient_id'],
                    $row['case_patient_name'],
                    $row['patient_full_name'],
                    $row['patient_ci'],
                    $row['case_status'],
                ])->all(),
            );
        }

        if ($consultationRows->isNotEmpty()) {
            $this->line('Consultas (cédula/nombre ≠ paciente FK):');
            $this->table(
                ['consultation_id', 'case_id', 'patient_id', 'consultation_name', 'consultation_ci', 'patient_name', 'patient_ci'],
                $consultationRows->map(fn (array $row): array => [
                    $row['consultation_id'],
                    $row['telemedicine_case_id'],
                    $row['telemedicine_patient_id'],
                    $row['consultation_name'],
                    $row['consultation_ci'],
                    $row['patient_name'],
                    $row['patient_ci'],
                ])->all(),
            );
        }

        if ($serviceRows->isNotEmpty()) {
            $this->line('Servicios de coordinación (ci_patient ≠ paciente FK):');
            $this->table(
                ['service_id', 'case_id', 'patient_id', 'service_patient', 'service_ci', 'patient_name', 'patient_ci'],
                $serviceRows->map(fn (array $row): array => [
                    $row['service_id'],
                    $row['telemedicine_case_id'],
                    $row['telemedicine_patient_id'],
                    $row['service_patient'],
                    $row['service_ci'],
                    $row['patient_name'],
                    $row['patient_ci'],
                ])->all(),
            );
        }

        $csvPath = $this->option('csv');

        if (filled($csvPath)) {
            $relative = ltrim((string) $csvPath, '/');
            $exportRows = $caseRows
                ->map(fn (array $row): array => ['type' => 'case'] + $row)
                ->concat($consultationRows->map(fn (array $row): array => ['type' => 'consultation'] + $row))
                ->concat($serviceRows->map(fn (array $row): array => ['type' => 'coordination_service'] + $row))
                ->values();

            $handle = fopen('php://temp', 'r+');
            $headers = array_keys($exportRows->first());
            fputcsv($handle, $headers);

            foreach ($exportRows as $row) {
                fputcsv($handle, array_map(
                    static fn (string $header) => $row[$header] ?? '',
                    $headers,
                ));
            }

            rewind($handle);
            Storage::disk('local')->put($relative, stream_get_contents($handle) ?: '');
            fclose($handle);

            $this->info('CSV exportado a storage/app/'.$relative);
        }

        $this->line('Este comando no corrige datos. Use telemedicine:remediate-shared-email-patient-identity --apply con criterio clínico.');

        return CommandAlias::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function caseMismatchRows(): \Illuminate\Support\Collection
    {
        return TelemedicineCase::query()
            ->with(['telemedicinePatient:id,full_name,nro_identificacion'])
            ->whereNotNull('telemedicine_patient_id')
            ->orderBy('id')
            ->get()
            ->filter(function (TelemedicineCase $case): bool {
                $patient = $case->telemedicinePatient;

                if ($patient === null) {
                    return true;
                }

                return ! TelemedicineCaseIdentity::namesMatch($case->patient_name, $patient->full_name);
            })
            ->map(function (TelemedicineCase $case): array {
                $patient = $case->telemedicinePatient;

                return [
                    'case_id' => $case->id,
                    'case_code' => $case->code,
                    'telemedicine_patient_id' => $case->telemedicine_patient_id,
                    'case_patient_name' => $case->patient_name,
                    'patient_full_name' => $patient?->full_name,
                    'patient_ci' => $patient?->nro_identificacion,
                    'case_status' => $case->status,
                ];
            })
            ->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function consultationMismatchRows(): \Illuminate\Support\Collection
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('telemedicine_consultation_patients')) {
            return collect();
        }

        return TelemedicineConsultationPatient::query()
            ->with(['telemedicinePatient:id,full_name,nro_identificacion'])
            ->whereNotNull('telemedicine_patient_id')
            ->orderBy('id')
            ->get()
            ->filter(function (TelemedicineConsultationPatient $consultation): bool {
                $patient = $consultation->telemedicinePatient;

                if ($patient === null) {
                    return true;
                }

                $nameMismatch = filled($consultation->full_name)
                    && ! TelemedicineCaseIdentity::namesMatch($consultation->full_name, $patient->full_name);
                $documentMismatch = filled($consultation->nro_identificacion)
                    && ! TelemedicinePatientIdentity::documentsMatch(
                        $consultation->nro_identificacion,
                        $patient->nro_identificacion,
                    );

                return $nameMismatch || $documentMismatch;
            })
            ->map(function (TelemedicineConsultationPatient $consultation): array {
                $patient = $consultation->telemedicinePatient;

                return [
                    'consultation_id' => $consultation->id,
                    'telemedicine_case_id' => $consultation->telemedicine_case_id,
                    'telemedicine_patient_id' => $consultation->telemedicine_patient_id,
                    'consultation_name' => $consultation->full_name,
                    'consultation_ci' => $consultation->nro_identificacion,
                    'patient_name' => $patient?->full_name,
                    'patient_ci' => $patient?->nro_identificacion,
                ];
            })
            ->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function serviceMismatchRows(): \Illuminate\Support\Collection
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('operation_coordination_services')) {
            return collect();
        }

        return OperationCoordinationService::query()
            ->with(['telemedicinePatient:id,full_name,nro_identificacion'])
            ->whereNotNull('telemedicine_patient_id')
            ->orderBy('id')
            ->get()
            ->filter(function (OperationCoordinationService $service): bool {
                $patient = $service->telemedicinePatient;

                if ($patient === null) {
                    return true;
                }

                return filled($service->ci_patient)
                    && $service->ci_patient !== 'NO ESPECIFICADO'
                    && ! TelemedicinePatientIdentity::documentsMatch(
                        $service->ci_patient,
                        $patient->nro_identificacion,
                    );
            })
            ->map(function (OperationCoordinationService $service): array {
                $patient = $service->telemedicinePatient;

                return [
                    'service_id' => $service->id,
                    'telemedicine_case_id' => $service->telemedicine_case_id,
                    'telemedicine_patient_id' => $service->telemedicine_patient_id,
                    'service_patient' => $service->patient,
                    'service_ci' => $service->ci_patient,
                    'patient_name' => $patient?->full_name,
                    'patient_ci' => $patient?->nro_identificacion,
                ];
            })
            ->values();
    }
}
