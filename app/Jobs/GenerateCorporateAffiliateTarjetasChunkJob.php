<?php

namespace App\Jobs;

use App\Models\AffiliationCorporate;
use App\Services\AffiliationCorporateBusinessDocumentsService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateCorporateAffiliateTarjetasChunkJob implements ShouldQueue
{
    use Batchable, Queueable;

    /**
     * @param  array<int, array<string, mixed>>  $chunk
     */
    public function __construct(
        public string $affiliationCode,
        public array $chunk,
    ) {}

    public function handle(): void
    {
        ini_set('memory_limit', '512M');
        set_time_limit(180);

        $record = AffiliationCorporate::query()
            ->where('code', $this->affiliationCode)
            ->with(['corporateAffiliates', 'plan', 'coverage'])
            ->firstOrFail();

        AffiliationCorporateBusinessDocumentsService::generateTarjetasChunk($record, $this->chunk);
    }
}
