<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Collections\CollectionDueDateRepair;
use App\Support\SecurityAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SyncCollectionDueDatesCommand extends Command
{
    protected $signature = 'collections:sync-due-dates
                            {--execute : Sin este flag solo muestra la vista previa}';

    protected $description = 'Sincroniza la fecha de filtro de las cuotas con su fecha oficial de próximo pago. Sin --execute no escribe nada.';

    public function handle(): int
    {
        $plan = CollectionDueDateRepair::plan();
        $execute = (bool) $this->option('execute');

        if ($plan === []) {
            $this->info('Todas las cuotas tienen la fecha de próximo pago y la de filtro sincronizadas. No hay nada que reparar.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Afiliación', 'Estado', 'Próximo pago', 'Filtro actual', 'Expiración', 'Acción', 'Motivo'],
            array_map(fn (array $row): array => [
                $row['id'],
                $row['affiliation_code'],
                $row['status'],
                $row['raw_official'],
                $row['raw_filter'],
                $row['expiration'],
                $this->actionLabel($row['action']),
                $row['reason'],
            ], $plan),
        );

        $counts = array_count_values(array_column($plan, 'action'));
        $this->line(sprintf(
            'Sincronizar: %d · Corregir formato: %d · Revisión manual (no se tocan): %d',
            $counts[CollectionDueDateRepair::ACTION_SYNC] ?? 0,
            $counts[CollectionDueDateRepair::ACTION_FORMAT] ?? 0,
            $counts[CollectionDueDateRepair::ACTION_REVIEW] ?? 0,
        ));

        if (! $execute) {
            $this->comment('Vista previa: no se escribió nada. Para aplicar: php artisan collections:sync-due-dates --execute');

            return self::SUCCESS;
        }

        $backupPath = 'collections-due-date-sync/'.now()->format('Y-m-d_His').'.csv';
        Storage::disk('local')->put($backupPath, $this->toCsv($plan));
        $this->line('Respaldo de los valores anteriores: '.Storage::disk('local')->path($backupPath));

        try {
            $result = CollectionDueDateRepair::apply($plan);
        } catch (Throwable $exception) {
            $this->error('No se aplicó ningún cambio: '.$exception->getMessage());

            return self::FAILURE;
        }

        SecurityAudit::log('AUDIT_COLLECTIONS_DUE_DATES_SYNCED', 'console.collections.sync-due-dates', [
            'synced' => $result['sync'],
            'formatted' => $result['format'],
            'skipped' => $result['skipped'],
            'backup' => $backupPath,
        ]);

        $this->info(sprintf(
            'Listo. Sincronizadas: %d · Formato corregido: %d · Sin tocar: %d',
            $result['sync'],
            $result['format'],
            $result['skipped'],
        ));

        return self::SUCCESS;
    }

    private function actionLabel(string $action): string
    {
        return match ($action) {
            CollectionDueDateRepair::ACTION_SYNC => 'Sincronizar',
            CollectionDueDateRepair::ACTION_FORMAT => 'Formato',
            default => 'Revisar',
        };
    }

    /**
     * @param  list<array<string, mixed>>  $plan
     */
    private function toCsv(array $plan): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['id', 'affiliation_code', 'status', 'next_payment_date', 'filter_next_payment_date', 'expiration', 'action', 'reason']);

        foreach ($plan as $row) {
            fputcsv($handle, [
                $row['id'],
                $row['affiliation_code'],
                $row['status'],
                $row['raw_official'],
                $row['raw_filter'],
                $row['expiration'],
                $row['action'],
                $row['reason'],
            ]);
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
