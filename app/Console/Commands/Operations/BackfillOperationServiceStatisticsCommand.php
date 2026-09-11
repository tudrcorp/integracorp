<?php

declare(strict_types=1);

namespace App\Console\Commands\Operations;

use App\Jobs\BackfillOperationServiceStatisticsChunkJob;
use App\Models\TelemedicineCase;
use App\Support\Operations\OperationServiceStatisticSync;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

class BackfillOperationServiceStatisticsCommand extends Command
{
    protected $signature = 'operations:backfill-service-statistics
                            {--case= : ID de un caso de telemedicina}
                            {--chunk=200 : Casos por lote}
                            {--sync : Ejecuta en este proceso en vez de encolar}';

    protected $description = 'Rellena la tabla de hechos de servicios de telemedicina y operaciones para el dashboard.';

    public function handle(): int
    {
        if (! OperationServiceStatisticSync::tableReady()) {
            $this->error('La tabla operation_service_statistics no existe. Aplique la migración primero.');

            return CommandAlias::FAILURE;
        }

        $caseId = filled($this->option('case')) ? (int) $this->option('case') : null;
        $chunkSize = max(1, (int) $this->option('chunk'));
        $sync = (bool) $this->option('sync');

        if ($caseId !== null && $caseId > 0) {
            try {
                OperationServiceStatisticSync::syncFromCaseId($caseId);
                $this->info("Caso {$caseId} sincronizado.");
            } catch (\Throwable $exception) {
                $this->error("Caso {$caseId}: {$exception->getMessage()}");

                return CommandAlias::FAILURE;
            }

            return CommandAlias::SUCCESS;
        }

        $dispatched = 0;
        $processed = 0;

        TelemedicineCase::query()
            ->orderBy('id')
            ->select(['id'])
            ->chunkById($chunkSize, function ($cases) use ($sync, &$dispatched, &$processed): void {
                $ids = $cases->pluck('id')->map(fn (mixed $id): int => (int) $id)->filter(fn (int $id): bool => $id > 0)->values()->all();
                if ($ids === []) {
                    return;
                }

                if ($sync) {
                    foreach ($ids as $id) {
                        try {
                            OperationServiceStatisticSync::syncFromCaseId($id);
                            $processed++;
                        } catch (\Throwable $exception) {
                            $this->error("Caso {$id}: {$exception->getMessage()}");
                        }
                    }

                    return;
                }

                BackfillOperationServiceStatisticsChunkJob::dispatch($ids);
                $dispatched++;
                $processed += count($ids);
            });

        if ($sync) {
            $this->info("Casos sincronizados: {$processed}.");

            return CommandAlias::SUCCESS;
        }

        $this->info("Lotes encolados en system: {$dispatched} · casos: {$processed}.");
        $this->line('El worker debe escuchar la cola system para completar el backfill.');

        return CommandAlias::SUCCESS;
    }
}
