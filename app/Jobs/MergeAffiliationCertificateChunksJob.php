<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AffiliationCertificateIssue;
use App\Services\AffiliationCertificateGeneratorService;
use App\Support\Affiliations\Certificates\CertificatePdfNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Une los tramos de un certificado grande en el PDF final y avisa a quien lo
 * pidió. Idempotente: si el PDF ya está listo, no lo vuelve a unir ni a avisar.
 */
class MergeAffiliationCertificateChunksJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [15, 60];
    }

    public function __construct(public int $issueId)
    {
        $this->onQueue(AffiliationCertificateGeneratorService::queue());
    }

    public function handle(AffiliationCertificateGeneratorService $generator): void
    {
        $issue = AffiliationCertificateIssue::query()->find($this->issueId);

        if ($issue === null) {
            return;
        }

        ini_set('memory_limit', '1024M');

        if ($generator->mergeChunks($issue)) {
            CertificatePdfNotifier::ready($issue->fresh() ?? $issue);
        }
    }

    public function failed(?Throwable $exception): void
    {
        AffiliationCertificateGeneratorService::markFailed($this->issueId, $exception);
    }
}
