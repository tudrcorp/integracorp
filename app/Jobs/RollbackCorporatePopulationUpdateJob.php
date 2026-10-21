<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CorporatePopulationUpdate;
use App\Support\AffiliationCorporates\Population\PopulationUpdateRollback;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Revierte una carga de población aplicada.
 *
 * Un solo intento: si se cae, la transacción no confirmó y no hay nada a medias;
 * `failed()` devuelve la carga a un estatus desde el que se puede reintentar.
 */
class RollbackCorporatePopulationUpdateJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public int $updateId) {}

    public function handle(): void
    {
        $update = CorporatePopulationUpdate::query()->find($this->updateId);

        if ($update === null || $update->status !== CorporatePopulationUpdate::STATUS_ROLLING_BACK) {
            return;
        }

        PopulationUpdateRollback::rollback($update);
    }

    public function failed(?Throwable $exception): void
    {
        CorporatePopulationUpdate::query()
            ->whereKey($this->updateId)
            ->where('status', CorporatePopulationUpdate::STATUS_ROLLING_BACK)
            ->update(['status' => CorporatePopulationUpdate::STATUS_APPLIED, 'error' => 'No se revirtió: el proceso se interrumpió. No se cambió ningún afiliado; puede intentar de nuevo.', 'rolled_back_by' => null, 'rolled_back_by_name' => null, 'rollback_note' => null, 'updated_at' => now()]);
    }
}
