<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\GenerateAffiliationCertificatePdfJob;
use App\Models\Affiliation;
use App\Models\AffiliationCertificateIssue;
use App\Models\AffiliationCorporate;
use App\Models\User;
use App\Support\AffiliationCorporates\CorporateAffiliationContractedPlan;
use App\Support\Affiliations\Certificates\CertificateDocumentData;
use App\Support\Affiliations\Certificates\CertificateVerificationKey;
use App\Support\Affiliations\Certificates\CorporateCertificateDocument;
use App\Support\Affiliations\Certificates\IndividualCertificateDocument;
use App\Support\DomPdfBatchRenderOptions;
use App\Support\SecurityAudit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use setasign\Fpdi\Fpdi;

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
     * Páginas por tramo al dibujar documentos largos. DomPDF retiene ~4,5 MB por
     * página: 730 páginas (2.646 carnets) pedían ~3,3 GB y morían. Por tramos, cada
     * DomPDF se libera antes del siguiente y FPDI une el resultado.
     */
    public const CHUNK_PAGES = 80;

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
            GenerateAffiliationCertificatePdfJob::dispatch($issue->id)->afterCommit();
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
     * El PDF de la emisión. Si se dibujó en cola, se sirve el archivo guardado.
     */
    public function renderPdf(AffiliationCertificateIssue $issue, bool $fromStorage = true): string
    {
        if ($fromStorage && $issue->isQueued()) {
            if ($issue->pdf_status !== AffiliationCertificateIssue::PDF_READY || ! $issue->pdf_path || ! Storage::disk('local')->exists($issue->pdf_path)) {
                throw new InvalidArgumentException('El certificado todavía se está generando.');
            }

            return (string) Storage::disk('local')->get($issue->pdf_path);
        }

        $data = $this->documentData($issue);

        if (count($data['pages']) <= self::CHUNK_PAGES) {
            return $this->renderDocument($data);
        }

        return $this->renderInChunks($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderDocument(array $data): string
    {
        $pdf = Pdf::loadView('documents.affiliation-certificate.document', ['data' => $data])
            ->setPaper('letter', 'portrait');

        DomPdfBatchRenderOptions::apply($pdf);
        $pdf->setOptions(['dpi' => 96, 'defaultFont' => 'Helvetica'], mergeWithDefaults: true);

        return (string) $pdf->output();
    }

    /**
     * Dibuja el documento por tramos de páginas y los une con FPDI. Cada página
     * conserva su número y el total, así que el resultado es idéntico al de una
     * sola pasada.
     *
     * @param  array<string, mixed>  $data
     */
    private function renderInChunks(array $data): string
    {
        $directory = storage_path('app/tmp/certificados-generador/'.bin2hex(random_bytes(6)));
        @mkdir($directory, 0775, true);
        $files = [];

        try {
            foreach (array_chunk($data['pages'], self::CHUNK_PAGES) as $index => $pages) {
                $chunk = $data;
                $chunk['pages'] = $pages;

                $file = $directory.'/tramo-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT).'.pdf';
                file_put_contents($file, $this->renderDocument($chunk));
                $files[] = $file;

                unset($chunk);
                gc_collect_cycles();
            }

            $merged = new Fpdi;

            foreach ($files as $file) {
                $count = $merged->setSourceFile($file);

                for ($page = 1; $page <= $count; $page++) {
                    $template = $merged->importPage($page);
                    $size = $merged->getTemplateSize($template);
                    $merged->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $merged->useTemplate($template);
                }
            }

            return (string) $merged->Output('S');
        } finally {
            foreach ($files as $file) {
                @unlink($file);
            }

            @rmdir($directory);
        }
    }

    public static function fileName(AffiliationCertificateIssue $issue): string
    {
        return 'Certificado-'.preg_replace('/[^A-Za-z0-9\-]/', '', $issue->affiliation_code).'.pdf';
    }
}
