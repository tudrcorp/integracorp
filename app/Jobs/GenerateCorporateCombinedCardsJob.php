<?php

namespace App\Jobs;

use App\Models\AffiliationCorporate;
use App\Services\AffiliationCorporateBusinessDocumentsService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Genera el PDF único con todos los carnets corporativos (columna de 4).
 *
 * Va primero en el lote para que la vista previa esté disponible en segundos,
 * mientras los carnets individuales que exige ViVEplus se siguen generando.
 */
class GenerateCorporateCombinedCardsJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(
        public string $affiliationCode,
    ) {
        $this->onQueue((string) config('affiliate-card.documents_queue', 'documents'));
    }

    public function handle(): void
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(900);

        $record = AffiliationCorporate::query()
            ->where('code', $this->affiliationCode)
            ->with(['corporateAffiliates.plan', 'corporateAffiliates.coverage', 'plan', 'coverage'])
            ->firstOrFail();

        AffiliationCorporateBusinessDocumentsService::generateCombinedCards($record);
    }
}
