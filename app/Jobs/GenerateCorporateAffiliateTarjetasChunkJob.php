<?php

namespace App\Jobs;

use App\Services\AffiliationCorporateBusinessDocumentsService;
use App\Support\Affiliations\Concerns\LogsAffiliationJobFailures;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateCorporateAffiliateTarjetasChunkJob implements ShouldQueue
{
    use Batchable, LogsAffiliationJobFailures, Queueable;

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
        $this->runWithAffiliationFailureLogging(function (): void {
            ini_set('memory_limit', '1024M');
            set_time_limit(540);
            AffiliationCorporateBusinessDocumentsService::generateTarjetasChunk($this->chunk);
        }, $this->affiliationJobFailureContext());
    }

    public function failed(?Throwable $exception): void
    {
        $this->logAffiliationJobFailure($exception, $this->affiliationJobFailureContext());
    }

    /**
     * @return array<string, mixed>
     */
    private function affiliationJobFailureContext(): array
    {
        $payloads = AffiliationCorporateBusinessDocumentsService::normalizeTarjetaPayloads($this->chunk);
        $first = $payloads[0] ?? [];

        return [
            'action' => 'regenerate-documents',
            'document_kind' => 'tarjetas-chunk',
            'affiliation_code' => $first['code'] ?? null,
            'chunk_size' => count($payloads),
            'output_filenames' => array_values(array_filter(array_map(
                static fn (array $payload): ?string => isset($payload['output_filename']) ? (string) $payload['output_filename'] : null,
                $payloads,
            ))),
        ];
    }
}
