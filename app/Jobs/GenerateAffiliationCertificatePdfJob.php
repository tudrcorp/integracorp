<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AffiliationCertificateIssue;
use App\Services\AffiliationCertificateGeneratorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Compatibilidad: así se encolaban antes los certificados grandes, en un único
 * job de varios minutos. Si queda alguno en la cola de producción, ahora solo
 * delega en el dibujo por tramos ({@see AffiliationCertificateGeneratorService::queueRender()}).
 */
class GenerateAffiliationCertificatePdfJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

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

        if ($issue->pdf_status === AffiliationCertificateIssue::PDF_READY && $issue->pdf_path && Storage::disk('local')->exists($issue->pdf_path)) {
            return;
        }

        $generator->queueRender($issue);
    }

    public function failed(?Throwable $exception): void
    {
        AffiliationCertificateGeneratorService::markFailed($this->issueId, $exception);
    }
}
