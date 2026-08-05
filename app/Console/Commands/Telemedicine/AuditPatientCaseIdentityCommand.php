<?php

declare(strict_types=1);

namespace App\Console\Commands\Telemedicine;

use App\Models\TelemedicineCase;
use App\Support\Telemedicine\TelemedicineCaseIdentity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Command\Command as CommandAlias;

class AuditPatientCaseIdentityCommand extends Command
{
    protected $signature = 'telemedicine:audit-patient-case-identity
                            {--csv= : Ruta relativa en storage/app para exportar CSV}';

    protected $description = 'Lista casos de telemedicina cuyo patient_name no coincide con el full_name del paciente vinculado (sin auto-reparar).';

    public function handle(): int
    {
        $rows = TelemedicineCase::query()
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

        if ($rows->isEmpty()) {
            $this->info('No se encontraron inconsistencias de identidad paciente ↔ caso.');

            return CommandAlias::SUCCESS;
        }

        $this->warn("Se encontraron {$rows->count()} inconsistencia(s):");
        $this->table(
            ['case_id', 'case_code', 'patient_id', 'case_patient_name', 'patient_full_name', 'patient_ci', 'status'],
            $rows->map(fn (array $row): array => [
                $row['case_id'],
                $row['case_code'],
                $row['telemedicine_patient_id'],
                $row['case_patient_name'],
                $row['patient_full_name'],
                $row['patient_ci'],
                $row['case_status'],
            ])->all(),
        );

        $csvPath = $this->option('csv');

        if (filled($csvPath)) {
            $relative = ltrim((string) $csvPath, '/');
            $handle = fopen('php://temp', 'r+');

            fputcsv($handle, array_keys($rows->first()));

            foreach ($rows as $row) {
                fputcsv($handle, array_values($row));
            }

            rewind($handle);
            Storage::disk('local')->put($relative, stream_get_contents($handle) ?: '');
            fclose($handle);

            $this->info('CSV exportado a storage/app/'.$relative);
        }

        $this->line('Este comando no corrige datos. Revise y saneé manualmente con criterio clínico.');

        return CommandAlias::SUCCESS;
    }
}
