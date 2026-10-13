<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AffiliationCertificateIssue;
use App\Services\AffiliationCertificateGeneratorService;
use App\Support\Affiliations\Certificates\CertificatePdfNotifier;
use Illuminate\Console\Command;
use Throwable;

/**
 * Salida de soporte: dibuja en este proceso los tramos que falten de un
 * certificado en cola y lo une, sin depender del worker. Reutiliza los tramos
 * ya listos y avisa a quien lo pidió, igual que la cola.
 */
class RenderAffiliationCertificateCommand extends Command
{
    protected $signature = 'certificates:render {issue : ID de la emisión o clave CER-…}';

    protected $description = 'Genera desde la consola un certificado grande del Generador de Certificado (si la cola no lo procesó)';

    public function handle(AffiliationCertificateGeneratorService $generator): int
    {
        $argument = trim((string) $this->argument('issue'));
        $issue = ctype_digit($argument)
            ? AffiliationCertificateIssue::query()->find((int) $argument)
            : AffiliationCertificateIssue::query()->where('verification_key', strtoupper($argument))->first();

        if ($issue === null) {
            $this->error('No existe la emisión '.$argument.'.');

            return self::FAILURE;
        }

        if (! $issue->isQueued()) {
            $this->info('Es un certificado pequeño: se dibuja al abrirlo desde el panel. No hace falta este comando.');

            return self::SUCCESS;
        }

        ini_set('memory_limit', '1024M');

        try {
            // Deja la foto de datos y el conteo de tramos al día sin encolar nada.
            $generator->prepareRender($issue->fresh());
            $issue->refresh();

            $this->info($issue->affiliation_code.': '.$issue->pdf_pages.' páginas en '.$issue->pdf_chunks_total.' tramos.');
            $bar = $this->output->createProgressBar((int) $issue->pdf_chunks_total);

            for ($index = 0; $index < (int) $issue->pdf_chunks_total; $index++) {
                $generator->renderChunk($issue, $index);
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();

            if ($generator->mergeChunks($issue->fresh())) {
                CertificatePdfNotifier::ready($issue->fresh());
            }
        } catch (Throwable $exception) {
            AffiliationCertificateGeneratorService::markFailed($issue->id, $exception);
            $this->error('No se pudo generar: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Listo: '.$issue->fresh()->pdf_path);

        return self::SUCCESS;
    }
}
