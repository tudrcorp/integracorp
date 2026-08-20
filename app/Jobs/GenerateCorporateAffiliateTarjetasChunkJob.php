<?php

namespace App\Jobs;

use App\Services\AffiliationCorporateBusinessDocumentsService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateCorporateAffiliateTarjetasChunkJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    /**
     * @param  array<int, array<string, mixed>>  $chunk
     */
    public function __construct(
        public array $chunk,
    ) {
        $this->onQueue((string) config('affiliate-card.documents_queue', 'documents'));
    }

    public function handle(): void
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(540);
        AffiliationCorporateBusinessDocumentsService::generateTarjetasChunk($this->chunk);
    }
}
