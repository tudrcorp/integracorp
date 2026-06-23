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

    public int $timeout = 240;

    /**
     * @param  array<int, array<string, mixed>>  $chunk
     */
    public function __construct(
        public array $chunk,
    ) {
        $this->onQueue('documents');
    }

    public function handle(): void
    {
        ini_set('memory_limit', '512M');
        set_time_limit(180);
        AffiliationCorporateBusinessDocumentsService::generateTarjetasChunk($this->chunk);
    }
}
