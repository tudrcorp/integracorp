<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\MergeAffiliationCertificateChunksJob;
use App\Jobs\RenderAffiliationCertificateChunkJob;
use App\Models\Affiliation;
use App\Models\AffiliationCertificateIssue;
use App\Models\AffiliationCorporate;
use App\Models\User;
use App\Support\AffiliationCorporates\CorporateAffiliationContractedPlan;
use App\Support\Affiliations\Certificates\CertificateDocumentData;
use App\Support\Affiliations\Certificates\CertificatePdfNotifier;
use App\Support\Affiliations\Certificates\CertificateVerificationKey;
use App\Support\Affiliations\Certificates\CorporateCertificateDocument;
use App\Support\Affiliations\Certificates\IndividualCertificateDocument;
use App\Support\DomPdfBatchRenderOptions;
use App\Support\SecurityAudit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use setasign\Fpdi\Fpdi;
use Throwable;

/**
 * «Generador de Certificado» del panel de Negocios: emite el certificado de una
 * afiliación con sus carnets (diseño «Voucher Tu Dr en Casa») y lo dibuja en PDF.
 *
 * Emitir crea la fila en `affiliation_certificate_issues` con la clave que lleva el
 * QR; dibujar es puro y repetible: la vista previa y la descarga salen de la misma
 * emisión, con los mismos carnets.
 */
final class AffiliationCertificateGeneratorService
{
    /** Carpeta (disco `local`, privado) de los PDF dibujados en cola. */
    public const STORAGE_DIRECTORY = 'certificados-generador';

    /**
     * Hasta este número de páginas el PDF se dibuja al vuelo; por encima, en cola.
     * Medido: ~0,1 s por página con DomPDF; 30 páginas caben holgadas en un request.
     */
    public const SYNC_MAX_PAGES = 30;

    /**
     * Páginas por tramo en los documentos grandes. Cada tramo es un job propio:
     * corto (~10 s y ~200 MB para 60 páginas), reintentable e idempotente, así
     * que un reinicio del worker, un `retry_after` o un error pasajero solo
     * repite ese tramo. Con varios workers en la cola, los tramos corren en
     * paralelo. TDEC-COR-00054 (730 páginas) son 13 tramos.
     */
    public const CHUNK_PAGES = 60;

    /** Carpeta (disco `local`) de los tramos y la foto de datos de cada emisión en cola. */
    public const CHUNK_DIRECTORY = 'certificados-generador/tramos';

    /** Carpeta (disco `local`) de las vistas previas livianas. */
    public const PREVIEW_DIRECTORY = 'certificados-generador/vista-previa';

    /**
     * Hasta este número de páginas la vista previa es el documento completo:
     * dibujarlo entero cuesta lo mismo que dos páginas. Por encima, la vista
     * previa trae solo la portada y la primera hoja de carnets.
     */
    public const FULL_PREVIEW_MAX_PAGES = 6;

    /**
     * @param  list<int|string>  $carnetAffiliateIds
     */
    public function issueIndividual(Affiliation $affiliation, array $carnetAffiliateIds, User $user): AffiliationCertificateIssue
    {
        $affiliation->loadMissing(IndividualCertificateDocument::eagerLoads());

        $printable = IndividualCertificateDocument::printableAffiliates($affiliation);
        $allowedIds = $printable->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();

        // Solo afiliados vigentes de esta afiliación: un id manipulado se descarta.
        $carnetIds = array_values(array_intersect(
            $allowedIds,
            array_map('intval', $carnetAffiliateIds),
        ));

        if ($printable->isEmpty() && $carnetAffiliateIds !== []) {
            throw new InvalidArgumentException('La afiliación no tiene afiliados activos para generar carnets.');
        }

        $period = IndividualCertificateDocument::paymentPeriod($affiliation);

        $issue = DB::transaction(fn (): AffiliationCertificateIssue => AffiliationCertificateIssue::query()->create([
            'verification_key' => CertificateVerificationKey::generate(),
            'affiliation_type' => AffiliationCertificateIssue::TYPE_INDIVIDUAL,
            'affiliation_id' => $affiliation->id,
            'affiliation_code' => (string) $affiliation->code,
            'plan_name' => $affiliation->plan?->description,
            'valid_from' => $period?->start->toDateString(),
            'valid_until' => $period?->end->toDateString(),
            'paid_until' => $period?->paidUntil->toDateString(),
            'current_period_paid' => (bool) $period?->currentIsPaid,
            'affiliates_count' => max(1, $printable->count()),
            'carnets_count' => count($carnetIds),
            'carnet_affiliate_ids' => $carnetIds,
            'issued_by' => $user->id,
            'issued_by_name' => $user->name,
        ]));

        SecurityAudit::log('AUDIT_AFFILIATION_CERTIFICATE_ISSUED', 'business.affiliations.certificate-generator', [
            'affiliation_certificate_issue_id' => $issue->id,
            'affiliation_code' => $issue->affiliation_code,
            'type' => $issue->affiliation_type,
            'carnets' => $issue->carnets_count,
        ], $user);

        return $issue;
    }

    /**
     * @param  list<int|string>  $carnetAffiliateIds
     */
    public function issueCorporate(AffiliationCorporate $affiliation, array $carnetAffiliateIds, User $user): AffiliationCertificateIssue
    {
        $printable = CorporateCertificateDocument::printableAffiliates($affiliation);
        $allowed = array_flip($printable->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all());

        // Solo afiliados vigentes de este colectivo: un id manipulado se descarta.
        $carnetIds = array_values(array_filter(
            array_values(array_unique(array_map('intval', $carnetAffiliateIds))),
            static fn (int $id): bool => isset($allowed[$id]),
        ));

        if ($printable->isEmpty()) {
            throw new InvalidArgumentException('El colectivo no tiene afiliados activos para certificar.');
        }

        $period = CorporateCertificateDocument::paymentPeriod($affiliation);
        $plan = CorporateAffiliationContractedPlan::plan($affiliation);
        $queued = self::estimatedPages($printable->count(), count($carnetIds)) > self::SYNC_MAX_PAGES;

        $issue = DB::transaction(fn (): AffiliationCertificateIssue => AffiliationCertificateIssue::query()->create([
            'verification_key' => CertificateVerificationKey::generate(),
            'affiliation_type' => AffiliationCertificateIssue::TYPE_CORPORATE,
            'affiliation_id' => $affiliation->id,
            'affiliation_code' => (string) $affiliation->code,
            'plan_name' => $plan?->description,
            'valid_from' => $period?->start->toDateString(),
            'valid_until' => $period?->end->toDateString(),
            'paid_until' => $period?->paidUntil->toDateString(),
            'current_period_paid' => (bool) $period?->currentIsPaid,
            'affiliates_count' => $printable->count(),
            'carnets_count' => count($carnetIds),
            'carnet_affiliate_ids' => $carnetIds,
            'pdf_status' => $queued ? AffiliationCertificateIssue::PDF_PROCESSING : null,
            'issued_by' => $user->id,
            'issued_by_name' => $user->name,
        ]));

        SecurityAudit::log('AUDIT_AFFILIATION_CERTIFICATE_ISSUED', 'business.affiliation-corporates.certificate-generator', [
            'affiliation_certificate_issue_id' => $issue->id,
            'affiliation_code' => $issue->affiliation_code,
            'type' => $issue->affiliation_type,
            'carnets' => $issue->carnets_count,
            'queued' => $queued,
        ], $user);

        if ($queued) {
            $this->queueRender($issue);
        }

        return $issue;
    }

    /**
     * Páginas aproximadas del documento: certificado, relación de a 40 y carnets de a 4.
     */
    public static function estimatedPages(int $affiliates, int $carnets): int
    {
        $relation = $affiliates > 1 ? (int) ceil($affiliates / CertificateDocumentData::RELATION_ROWS_PER_PAGE) : 0;
        $carnetPages = $carnets > 0 ? (int) ceil($carnets / CertificateDocumentData::CARNETS_PER_PAGE) : 0;

        return 1 + $relation + $carnetPages;
    }

    /**
     * Datos de la plantilla para una emisión.
     *
     * @return array<string, mixed>
     */
    public function documentData(AffiliationCertificateIssue $issue): array
    {
        if (! $issue->isIndividual()) {
            $corporate = AffiliationCorporate::query()->findOrFail($issue->affiliation_id);

            return CorporateCertificateDocument::build($corporate, $issue, $issue->carnet_affiliate_ids ?? []);
        }

        $affiliation = Affiliation::query()
            ->with(IndividualCertificateDocument::eagerLoads())
            ->findOrFail($issue->affiliation_id);

        return IndividualCertificateDocument::build($affiliation, $issue, $issue->carnet_affiliate_ids ?? []);
    }

    /**
     * Ruta absoluta del PDF listo para servir.
     *
     * Uno en cola sirve su archivo cuando está listo; mientras se dibuja, lanza
     * InvalidArgumentException. Uno pequeño se dibuja la primera vez y se guarda:
     * la vista previa y la descarga ya no lo dibujan dos veces.
     */
    public function pdfFile(AffiliationCertificateIssue $issue): string
    {
        $stored = $this->storedPdfPath($issue);

        if ($stored !== null) {
            return $stored;
        }

        if ($issue->isQueued()) {
            throw new InvalidArgumentException('El certificado todavía se está generando.');
        }

        $relative = self::STORAGE_DIRECTORY.'/'.$issue->verification_key.'.pdf';
        $this->putOrFail($relative, $this->renderDocument($this->documentData($issue)));

        $issue->forceFill(['pdf_path' => $relative, 'pdf_generated_at' => now()])->save();

        return Storage::disk('local')->path($relative);
    }

    /**
     * Ruta absoluta de la vista previa liviana: portada y primera hoja de
     * carnets (o el documento completo si tiene pocas páginas). Se dibuja una
     * sola vez; en los grandes ya viene hecha desde la emisión.
     */
    public function previewFile(AffiliationCertificateIssue $issue): string
    {
        $disk = Storage::disk('local');
        $relative = self::previewFileName($issue);

        if ($disk->exists($relative)) {
            return $disk->path($relative);
        }

        if (! $issue->isQueued()) {
            $data = $this->documentData($issue);

            if (count($data['pages']) <= self::FULL_PREVIEW_MAX_PAGES) {
                return $this->pdfFile($issue);
            }

            $this->putOrFail($relative, $this->renderDocument(self::previewData($data)));

            return $disk->path($relative);
        }

        // Emisión en cola anterior a la vista previa (o borrada): se arma con la
        // foto de datos si sigue disponible, o con los datos actuales.
        $data = $disk->exists(self::chunkDirectory($issue).'/datos.ser') ? $this->snapshot($issue) : $this->documentData($issue);
        $this->putOrFail($relative, $this->renderDocument(self::previewData($data)));

        return $disk->path($relative);
    }

    /**
     * Las páginas de la vista previa: la portada y la primera hoja de carnets;
     * sin carnets, la primera de la relación de afiliados. Cada página conserva
     * su número y el total («Página 1 de 730»).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function previewData(array $data): array
    {
        $pages = array_values($data['pages']);
        $second = collect($pages)->firstWhere('kind', 'carnet')
            ?? collect($pages)->firstWhere('kind', 'rel')
            ?? ($pages[1] ?? null);

        $data['pages'] = array_values(array_filter([$pages[0] ?? null, $second], static fn (mixed $page): bool => is_array($page)));

        return $data;
    }

    public static function previewFileName(AffiliationCertificateIssue $issue): string
    {
        return self::PREVIEW_DIRECTORY.'/'.$issue->verification_key.'.pdf';
    }

    /**
     * Contenido del PDF. Con `fromStorage` sirve el guardado (o lo dibuja y guarda
     * si es pequeño); sin él, lo dibuja de nuevo en este proceso, por tramos si es
     * largo (validaciones y el comando de soporte).
     */
    public function renderPdf(AffiliationCertificateIssue $issue, bool $fromStorage = true): string
    {
        if ($fromStorage) {
            return (string) file_get_contents($this->pdfFile($issue));
        }

        $data = $this->documentData($issue);

        if (count($data['pages']) <= self::CHUNK_PAGES) {
            return $this->renderDocument($data);
        }

        $merged = new Fpdi;

        foreach (array_chunk($data['pages'], self::CHUNK_PAGES) as $pages) {
            $chunk = $data;
            $chunk['pages'] = $pages;
            $file = tempnam(sys_get_temp_dir(), 'cer');

            try {
                file_put_contents($file, $this->renderDocument($chunk));
                $this->appendPdf($merged, $file);
            } finally {
                @unlink($file);
            }

            unset($chunk);
            gc_collect_cycles();
        }

        return (string) $merged->Output('S');
    }

    /**
     * Pone en cola el dibujo por tramos. Repetible: si ya hay tramos listos (un
     * reintento), solo encola los que faltan, sobre la misma foto de datos.
     */
    public function queueRender(AffiliationCertificateIssue $issue): void
    {
        $missing = $this->prepareRender($issue);

        if ($missing === []) {
            MergeAffiliationCertificateChunksJob::dispatch($issue->id)->afterCommit();

            return;
        }

        $issueId = $issue->id;
        $batch = Bus::batch(array_map(static fn (int $index): RenderAffiliationCertificateChunkJob => new RenderAffiliationCertificateChunkJob($issueId, $index), $missing))
            ->name('Certificado '.$issue->affiliation_code)
            ->onQueue(self::queue())
            ->then(static function (Batch $batch) use ($issueId): void {
                MergeAffiliationCertificateChunksJob::dispatch($issueId);
            })
            ->catch(static function (Batch $batch, Throwable $exception) use ($issueId): void {
                self::markFailed($issueId, $exception);
            })
            ->dispatch();

        $issue->forceFill(['pdf_batch_id' => $batch->id])->save();
    }

    /**
     * Deja la emisión lista para dibujarse por tramos (foto de datos, conteo y
     * estado) sin encolar nada. Devuelve los tramos que faltan.
     *
     * @return list<int>
     */
    public function prepareRender(AffiliationCertificateIssue $issue): array
    {
        $directory = self::chunkDirectory($issue);
        $snapshot = $directory.'/datos.ser';
        $disk = Storage::disk('local');

        // Una sola foto de datos por emisión: todos los tramos parten las mismas
        // páginas aunque la población cambie mientras se dibujan.
        if (! $disk->exists($snapshot)) {
            $this->putOrFail($snapshot, serialize($this->documentData($issue)));
        }

        $snapshot = $this->snapshot($issue);
        $pages = count($snapshot['pages']);
        $total = max(1, (int) ceil($pages / self::CHUNK_PAGES));

        // La vista previa sale de la misma foto que los tramos: idéntica al PDF final
        // y visible mientras el documento completo se dibuja.
        if (! $disk->exists(self::previewFileName($issue))) {
            $this->putOrFail(self::previewFileName($issue), $this->renderDocument(self::previewData($snapshot)));
        }
        $missing = array_values(array_filter(range(0, $total - 1), fn (int $index): bool => ! $disk->exists(self::chunkFile($issue, $index))));

        $issue->forceFill([
            'pdf_status' => AffiliationCertificateIssue::PDF_PROCESSING,
            'pdf_error' => null,
            'pdf_pages' => $pages,
            'pdf_chunks_total' => $total,
            'pdf_chunks_done' => $total - count($missing),
            'pdf_progress_at' => now(),
        ])->save();

        return $missing;
    }

    /**
     * Dibuja un tramo. Idempotente: si su archivo ya existe, no hace nada.
     */
    public function renderChunk(AffiliationCertificateIssue $issue, int $index): void
    {
        $relative = self::chunkFile($issue, $index);

        if (Storage::disk('local')->exists($relative)) {
            return;
        }

        $data = $this->snapshot($issue);
        $data['pages'] = array_slice($data['pages'], $index * self::CHUNK_PAGES, self::CHUNK_PAGES);

        if ($data['pages'] === []) {
            throw new RuntimeException('El tramo '.$index.' no tiene páginas: la foto de datos no corresponde a esta emisión.');
        }

        $this->putOrFail($relative, $this->renderDocument($data));

        AffiliationCertificateIssue::query()->whereKey($issue->id)->increment('pdf_chunks_done', 1, ['pdf_progress_at' => now()]);
    }

    /**
     * Une los tramos en el PDF final, lo deja listo y borra los tramos.
     * Idempotente: si el PDF ya está listo, no lo vuelve a unir.
     */
    public function mergeChunks(AffiliationCertificateIssue $issue): bool
    {
        if ($issue->pdf_status === AffiliationCertificateIssue::PDF_READY && $this->storedPdfPath($issue) !== null) {
            return false;
        }

        $disk = Storage::disk('local');
        $total = max(1, (int) $issue->pdf_chunks_total);
        $merged = new Fpdi;

        for ($index = 0; $index < $total; $index++) {
            $relative = self::chunkFile($issue, $index);

            if (! $disk->exists($relative)) {
                throw new RuntimeException('Falta el tramo '.($index + 1).' de '.$total.' para unir el certificado.');
            }

            $this->appendPdf($merged, $disk->path($relative));
        }

        $final = self::STORAGE_DIRECTORY.'/'.$issue->verification_key.'.pdf';
        $this->ensureDirectory(dirname($final));
        $temporary = $disk->path($final).'.tmp';
        $merged->Output('F', $temporary);

        if (! is_file($temporary) || ! @rename($temporary, $disk->path($final))) {
            throw new RuntimeException('No se pudo guardar el certificado final en '.$final.'. Revise los permisos de storage/app/private.');
        }

        $issue->forceFill([
            'pdf_status' => AffiliationCertificateIssue::PDF_READY,
            'pdf_path' => $final,
            'pdf_error' => null,
            'pdf_chunks_done' => $total,
            'pdf_generated_at' => now(),
            'pdf_progress_at' => now(),
        ])->save();

        $disk->deleteDirectory(self::chunkDirectory($issue));

        return true;
    }

    /**
     * Falla definitiva (tras los reintentos de un tramo o de la unión). Los
     * tramos listos se conservan para que «Reintentar» no los repita.
     */
    public static function markFailed(int $issueId, ?Throwable $exception): void
    {
        $issue = AffiliationCertificateIssue::query()->find($issueId);

        Log::error('Certificado del generador: no se pudo dibujar', [
            'affiliation_certificate_issue_id' => $issueId,
            'message' => $exception?->getMessage(),
        ]);

        if ($issue === null || $issue->pdf_status === AffiliationCertificateIssue::PDF_READY) {
            return;
        }

        $issue->forceFill([
            'pdf_status' => AffiliationCertificateIssue::PDF_FAILED,
            'pdf_error' => mb_substr((string) $exception?->getMessage(), 0, 1000),
        ])->save();

        CertificatePdfNotifier::failed($issue);
    }

    public static function queue(): string
    {
        return (string) config('affiliate-card.documents_queue', 'documents');
    }

    public static function chunkDirectory(AffiliationCertificateIssue $issue): string
    {
        return self::CHUNK_DIRECTORY.'/'.$issue->verification_key;
    }

    public static function chunkFile(AffiliationCertificateIssue $issue, int $index): string
    {
        return self::chunkDirectory($issue).'/tramo-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT).'.pdf';
    }

    private function storedPdfPath(AffiliationCertificateIssue $issue): ?string
    {
        if (! filled($issue->pdf_path)) {
            return null;
        }

        if ($issue->isQueued() && $issue->pdf_status !== AffiliationCertificateIssue::PDF_READY) {
            return null;
        }

        $disk = Storage::disk('local');

        return $disk->exists($issue->pdf_path) ? $disk->path($issue->pdf_path) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(AffiliationCertificateIssue $issue): array
    {
        $raw = Storage::disk('local')->get(self::chunkDirectory($issue).'/datos.ser');
        $data = is_string($raw) ? @unserialize($raw, ['allowed_classes' => false]) : false;

        if (! is_array($data) || ! isset($data['pages']) || ! is_array($data['pages'])) {
            throw new RuntimeException('No se encontró la foto de datos del certificado '.$issue->verification_key.'.');
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderDocument(array $data): string
    {
        $pdf = Pdf::loadView('documents.affiliation-certificate.document', ['data' => $data])
            ->setPaper('letter', 'portrait');

        DomPdfBatchRenderOptions::apply($pdf);
        $pdf->setOptions([
            'dpi' => 96,
            'defaultFont' => 'Helvetica',
            // Las imágenes van como archivos (CertificateAssets): si storage/ o public/
            // son enlaces a otra ubicación en el servidor, sus rutas reales también
            // tienen que estar dentro del chroot o DomPDF las deja en blanco.
            'chroot' => array_values(array_unique(array_filter([
                realpath(base_path()),
                realpath(public_path()),
                realpath(storage_path()),
            ]))),
        ], mergeWithDefaults: true);

        return (string) $pdf->output();
    }

    private function appendPdf(Fpdi $merged, string $file): void
    {
        $count = $merged->setSourceFile($file);

        for ($page = 1; $page <= $count; $page++) {
            $template = $merged->importPage($page);
            $size = $merged->getTemplateSize($template);
            $merged->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $merged->useTemplate($template);
        }
    }

    /**
     * El disco `local` no lanza errores (`throw => false`): una escritura fallida
     * devolvería `false` en silencio y el certificado quedaría «listo» sin archivo.
     */
    private function putOrFail(string $relative, string $contents): void
    {
        $this->ensureDirectory(dirname($relative));

        if (Storage::disk('local')->put($relative, $contents) !== true) {
            throw new RuntimeException('No se pudo escribir '.$relative.'. Revise los permisos de storage/app/private para el usuario del worker.');
        }
    }

    private function ensureDirectory(string $relative): void
    {
        $disk = Storage::disk('local');

        if (! $disk->exists($relative)) {
            $disk->makeDirectory($relative);
        }
    }

    public static function fileName(AffiliationCertificateIssue $issue): string
    {
        return 'Certificado-'.preg_replace('/[^A-Za-z0-9\-]/', '', $issue->affiliation_code).'.pdf';
    }
}
