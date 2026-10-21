<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CorporatePopulationUpdate;
use App\Support\AffiliationCorporates\Population\PopulationUpdateAnalyzer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Analiza el archivo de población de una carga (no cambia afiliados).
 *
 * Un solo intento: si se cae, la transacción no confirmó y no hay nada a medias;
 * `failed()` devuelve la carga a un estatus desde el que se puede reintentar.
 */
class AnalyzeCorporatePopulationUpdateJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public int $updateId) {}

    public function handle(): void
    {
        $update = CorporatePopulationUpdate::query()->find($this->updateId);

        if ($update === null || $update->status !== CorporatePopulationUpdate::STATUS_ANALYZING) {
            return;
        }

        PopulationUpdateAnalyzer::analyze($update);
    }

    public function failed(?Throwable $exception): void
    {
        CorporatePopulationUpdate::query()
            ->whereKey($this->updateId)
            ->where('status', CorporatePopulationUpdate::STATUS_ANALYZING)
            ->update(['status' => CorporatePopulationUpdate::STATUS_FAILED, 'error' => 'El análisis se interrumpió. Suba el archivo de nuevo.', 'updated_at' => now()]);
    }
}
