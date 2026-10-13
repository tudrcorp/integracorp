<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AffiliationCertificateIssue;
use App\Services\AffiliationCertificateGeneratorService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Dibuja un tramo (hasta 60 páginas) de un certificado grande del «Generador de
 * Certificado». Corto, reintentable e idempotente: si el archivo del tramo ya
 * existe no lo vuelve a dibujar, así que un reinicio del worker o un
 * `retry_after` solo cuesta ese tramo.
 */
class RenderAffiliationCertificateChunkJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 3;

    /** Un tramo tarda ~10 s; cinco minutos dejan holgura sin acercarse al `retry_after` de Redis (900 s). */
    public int $timeout = 300;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [15, 60];
    }

    public function __construct(public int $issueId, public int $chunkIndex)
    {
        $this->onQueue(AffiliationCertificateGeneratorService::queue());
    }

    public function handle(AffiliationCertificateGeneratorService $generator): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $issue = AffiliationCertificateIssue::query()->find($this->issueId);

        // La emisión puede no ser visible todavía si el job ganó la carrera al commit.
        if ($issue === null) {
            $this->release(10);

            return;
        }

        ini_set('memory_limit', '1024M');

        $generator->renderChunk($issue, $this->chunkIndex);
    }
}
