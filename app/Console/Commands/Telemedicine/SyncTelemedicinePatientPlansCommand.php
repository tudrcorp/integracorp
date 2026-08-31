<?php

declare(strict_types=1);

namespace App\Console\Commands\Telemedicine;

use App\Support\Telemedicine\TelemedicinePatientPlanBridge;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

class SyncTelemedicinePatientPlansCommand extends Command
{
    protected $signature = 'telemedicine:sync-patient-plans
                            {--apply : Escribe plan_id/coverage_id faltantes (por defecto solo dry-run)}
                            {--patient= : ID de telemedicine_patients}
                            {--document= : Cédula del paciente}
                            {--limit= : Máximo de pacientes a revisar}';

    protected $description = 'Copia el plan del afiliado (individual o corporativo) a pacientes de telemedicina que quedaron sin plan_id.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $patientId = filled($this->option('patient')) ? (int) $this->option('patient') : null;
        $limit = filled($this->option('limit')) ? (int) $this->option('limit') : null;
        $document = filled($this->option('document')) ? (string) $this->option('document') : null;

        $result = TelemedicinePatientPlanBridge::backfillMissing(
            apply: $apply,
            patientId: $patientId,
            document: $document,
            limit: $limit,
        );

        if ($result['rows'] === []) {
            $this->info('No hay pacientes sin plan_id con afiliación vinculada para actualizar.');

            return CommandAlias::SUCCESS;
        }

        $this->table(
            ['patient_id', 'nombre', 'cédula', 'afiliación', 'plan_id', 'coverage_id'],
            array_map(
                static fn (array $row): array => [
                    $row['patient_id'],
                    $row['full_name'],
                    $row['nro_identificacion'],
                    $row['code_affiliation'],
                    $row['plan_id'],
                    $row['coverage_id'],
                ],
                $result['rows'],
            ),
        );

        if (! $apply) {
            $this->warn('Dry-run: no se escribió nada. Revise la tabla y ejecute de nuevo con --apply.');
            $this->line('Revisados: '.$result['scanned'].' · pendientes: '.count($result['rows']).' · sin fuente de plan: '.$result['skipped']);

            return CommandAlias::SUCCESS;
        }

        $this->info('Actualizados: '.$result['updated'].' · omitidos: '.$result['skipped'].' · revisados: '.$result['scanned']);

        return CommandAlias::SUCCESS;
    }
}
