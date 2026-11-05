<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DataChangeAudit;
use Illuminate\Console\Command;

/**
 * Retención de la auditoría de cambios: borra por lotes los movimientos con más de
 * {@see DataChangeAudit::RETENTION_MONTHS} meses. Solo toca `data_change_audits`.
 */
class PurgeDataChangeAuditsCommand extends Command
{
    protected $signature = 'audit:purge-data-changes {--dry-run : Solo cuenta lo que se borraría}';

    protected $description = 'Borra los movimientos de la auditoría de cambios con más de 24 meses';

    public function handle(): int
    {
        $limit = now()->subMonths(DataChangeAudit::RETENTION_MONTHS);
        $query = DataChangeAudit::query()->where('created_at', '<', $limit);
        $total = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("Se borrarían {$total} movimientos anteriores al {$limit->format('d/m/Y')}.");

            return self::SUCCESS;
        }

        $deleted = 0;

        do {
            $ids = DataChangeAudit::query()->where('created_at', '<', $limit)->orderBy('id')->limit(1000)->pluck('id');
            $deleted += $ids->isEmpty() ? 0 : DataChangeAudit::query()->whereIn('id', $ids)->delete();
        } while ($ids->isNotEmpty());

        $this->info("Borrados {$deleted} movimientos anteriores al {$limit->format('d/m/Y')}.");

        return self::SUCCESS;
    }
}
