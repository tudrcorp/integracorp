<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CorporatePopulationUpdate;
use App\Support\AffiliationCorporates\Population\PopulationUpdateApplier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Aplica una carga de población aprobada (todo o nada).
 *
 * Un solo intento: si se cae, la transacción no confirmó y no hay nada a medias;
 * `failed()` devuelve la carga a un estatus desde el que se puede reintentar.
 */
class ApplyCorporatePopulationUpdateJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public int $updateId) {}

    public function handle(): void
    {
        $update = CorporatePopulationUpdate::query()->find($this->updateId);

        if ($update === null || $update->status !== CorporatePopulationUpdate::STATUS_APPLYING) {
            return;
        }

        PopulationUpdateApplier::apply($update);
    }

    public function failed(?Throwable $exception): void
    {
        CorporatePopulationUpdate::query()
            ->whereKey($this->updateId)
            ->where('status', CorporatePopulationUpdate::STATUS_APPLYING)
            ->update(['status' => CorporatePopulationUpdate::STATUS_ANALYZED, 'error' => 'No se aplicó: el proceso se interrumpió. No se cambió ningún afiliado; puede aprobar de nuevo.', 'approved_by' => null, 'approved_by_name' => null, 'approved_at' => null, 'updated_at' => now()]);
    }
}
