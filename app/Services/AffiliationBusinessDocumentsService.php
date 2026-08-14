<?php

namespace App\Services;

use App\Http\Controllers\AffiliationController;
use App\Http\Controllers\TarjetaAfiliacionController;
use App\Models\Affiliate;
use App\Models\Affiliation;
use App\Support\AffiliateCard\AffiliateCardPageLayout;
use App\Support\TarjetaAfiliacionQrPlanCatalog;
use App\Support\Viveplus\ViveplusDocumentWebhookDispatcher;
use App\Support\WhiteCompanies\WhiteCompanyDocumentBrand;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class AffiliationBusinessDocumentsService
{
    public static function condicionadoBasenameForPlanId(?int $planId): ?string
    {
        return match ((int) $planId) {
            1 => 'CondicionesINICIAL.pdf',
            2 => 'CondicionesIDEAL.pdf',
            3 => 'CondicionesESPECIAL.pdf',
            default => null,
        };
    }

    public static function condicionadoPublicUrlForPlanId(?int $planId): ?string
    {
        $basename = self::condicionadoBasenameForPlanId($planId);

        if ($basename === null) {
            return null;
        }

        return asset('storage/condicionados/'.$basename);
    }

    /**
     * Genera la tarjeta legacy `TAR-{code}.pdf` solo si no hay tarjeta equivalente entre afiliados
     * (p. ej. el titular ya tiene fila en `affiliates` con su CI).
     */
    public static function shouldGenerateLegacyTitularTarjeta(Affiliation $record): bool
    {
        if (! $record->relationLoaded('affiliates')) {
            $record->loadMissing('affiliates');
        }

        if ($record->affiliates->isEmpty()) {
            return true;
        }

        $titularCi = trim((string) $record->nro_identificacion_ti);
        if ($titularCi === '') {
            return true;
        }

        $titularEnAfiliados = $record->affiliates->contains(
            fn (Affiliate $a): bool => strcasecmp(trim((string) $a->nro_identificacion), $titularCi) === 0
        );

        return ! $titularEnAfiliados;
    }

    public static function condicionadoAbsolutePathForPlanId(?int $planId): ?string
    {
        $basename = self::condicionadoBasenameForPlanId($planId);
        if ($basename === null) {
            return null;
        }

        $path = storage_path('app/public/condicionados/'.$basename);

        return is_file($path) ? $path : null;
    }

    public static function condicionadoAbsolutePathForAffiliation(Affiliation $record): ?string
    {
        return WhiteCompanyDocumentBrand::forAffiliation($record)
            ->condicionadoAbsolutePath($record->plan_id !== null ? (int) $record->plan_id : null);
    }

    /**
     * Regenera el certificado (uno) y una tarjeta PDF por cada familiar en `affiliates`.
     *
     * @return array{documents: array<int, array{label: string, kind: string, filename: string, preview_url: string}>}
     */
    public static function resolveCertificateAbsolutePath(Affiliation $record): ?string
    {
        $path = public_path('storage/certificados-doc/CER-'.$record->code.'.pdf');

        return is_file($path) ? $path : null;
    }

    /**
     * @return array<int, string>
     */
    public static function titularTarjetaCandidateFilenames(Affiliation $record): array
    {
        $record->loadMissing('affiliates');

        $candidates = [];

        if (self::shouldGenerateLegacyTitularTarjeta($record)) {
            $candidates[] = 'TAR-'.$record->code.'.pdf';
        } else {
            $titularCi = trim((string) $record->nro_identificacion_ti);
            $titularAffiliate = $record->affiliates->first(
                fn (Affiliate $affiliate): bool => strcasecmp(
                    trim((string) $affiliate->nro_identificacion),
                    $titularCi
                ) === 0
            );
            $affiliate = $titularAffiliate ?? $record->affiliates->first();

            if ($affiliate !== null) {
                $candidates[] = 'TAR-'.$record->code.'-'.$affiliate->id.'.pdf';
            }
        }

        $legacyFilename = 'TAR-'.$record->code.'.pdf';
        if (! in_array($legacyFilename, $candidates, true)) {
            $candidates[] = $legacyFilename;
        }

        return $candidates;
    }

    /**
     * @return array<int, string>
     */
    public static function titularTarjetaCandidateAbsolutePaths(Affiliation $record): array
    {
        $directory = public_path('storage/tarjeta-afiliacion/');

        return array_map(
            fn (string $filename): string => $directory.$filename,
            self::titularTarjetaCandidateFilenames($record),
        );
    }

    public static function resolveTitularTarjetaAbsolutePath(Affiliation $record): ?string
    {
        foreach (self::titularTarjetaCandidateAbsolutePaths($record) as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Carnets individuales existentes, uno por persona, con la cédula exacta de `affiliates`.
     *
     * @return array<int, array{path: string, identification: string}>
     */
    public static function resolveAffiliateCarnetDocuments(Affiliation $record): array
    {
        $record->loadMissing('affiliates');

        $directory = public_path('storage/tarjeta-afiliacion/');
        $documents = [];
        $seen = [];

        foreach ($record->affiliates as $affiliate) {
            $identification = (string) $affiliate->nro_identificacion;
            if (trim($identification) === '') {
                continue;
            }

            $path = $directory.'TAR-'.$record->code.'-'.$affiliate->id.'.pdf';
            if (! is_file($path)) {
                continue;
            }

            if (isset($seen[$identification])) {
                continue;
            }

            $seen[$identification] = true;
            $documents[] = [
                'path' => $path,
                'identification' => $identification,
            ];
        }

        if (self::shouldGenerateLegacyTitularTarjeta($record)) {
            $identification = (string) $record->nro_identificacion_ti;
            $path = $directory.'TAR-'.$record->code.'.pdf';

            if (trim($identification) !== '' && is_file($path) && ! isset($seen[$identification])) {
                $documents[] = [
                    'path' => $path,
                    'identification' => $identification,
                ];
            }
        }

        return $documents;
    }

    public static function regenerateCertificateAndTarjetas(
        Affiliation $record,
        ?int $userId,
        bool $notifyCertificate = false,
        bool $useIndividualAffiliateCardLayout = false,
    ): array {
        $record->loadMissing(['affiliates', 'plan.benefitPlans', 'coverage', 'agent', 'agency', 'whiteCompanyUser']);

        $brand = WhiteCompanyDocumentBrand::forAffiliation($record);

        self::purgeExistingGeneratedDocuments($record);

        $affiliateCount = $record->affiliates->count();
        $legacyTarjetaCount = self::shouldGenerateLegacyTitularTarjeta($record) ? 1 : 0;
        $totalPdfs = 1 + $affiliateCount + $legacyTarjetaCount;
        $memoryMb = min(1024, 384 + (48 * max(1, $totalPdfs)));
        ini_set('memory_limit', $memoryMb.'M');
        set_time_limit(min(900, 120 + (45 * max(1, $totalPdfs))));

        $certDir = public_path('storage/certificados-doc/');
        if (! is_dir($certDir)) {
            mkdir($certDir, 0755, true);
        }

        $tarjetaDir = public_path('storage/tarjeta-afiliacion/');
        if (! is_dir($tarjetaDir)) {
            mkdir($tarjetaDir, 0755, true);
        }

        AffiliationController::generateCertificateIndividual(
            $record,
            $record->affiliates,
            $userId,
            $notifyCertificate,
            rethrowOnFailure: true,
        );

        $version = (string) time();
        $certName = 'CER-'.$record->code.'.pdf';

        $documents = [
            [
                'label' => 'Certificado de afiliación',
                'kind' => 'certificate',
                'filename' => $certName,
                'preview_url' => asset('storage/certificados-doc/'.$certName).'?t='.$version,
            ],
        ];

        $desde = $record->effective_date ?? '';
        $hasta = self::vigenciaHasta($record->effective_date);
        $planId = $record->plan_id !== null ? (int) $record->plan_id : null;
        $planDesc = $brand->planDisplayName($planId, $record->plan?->description ?? '');
        $cobertura = $record->coverage?->price ?? '';
        $frecuencia = $record->payment_frequency;
        $totalTarjetasForLayout = $affiliateCount + $legacyTarjetaCount;

        if ($useIndividualAffiliateCardLayout && $totalTarjetasForLayout > 1) {
            $batchCards = [];

            foreach ($record->affiliates as $affiliate) {
                $batchCards[] = self::tarjetaPayload(
                    record: $record,
                    name: (string) $affiliate->full_name,
                    ci: (string) $affiliate->nro_identificacion,
                    planDesc: $planDesc,
                    frecuencia: $frecuencia,
                    cobertura: $cobertura,
                    desde: $desde,
                    hasta: $hasta,
                    useIndividualAffiliateCardLayout: true,
                    brand: $brand,
                );
            }

            if ($legacyTarjetaCount === 1) {
                $batchCards[] = self::tarjetaPayload(
                    record: $record,
                    name: (string) $record->full_name_ti,
                    ci: (string) $record->nro_identificacion_ti,
                    planDesc: $planDesc,
                    frecuencia: $frecuencia,
                    cobertura: $cobertura,
                    desde: $desde,
                    hasta: $hasta,
                    useIndividualAffiliateCardLayout: true,
                    brand: $brand,
                );
            }

            $combinedFilename = 'TAR-'.$record->code.'-carnets.pdf';
            $batchResult = TarjetaAfiliacionController::generateTarjetaAfiliacionBatch(
                $batchCards,
                $combinedFilename,
                silent: true,
                ensureOutputDirectory: false,
            );

            if ($batchResult !== true) {
                throw new RuntimeException(is_string($batchResult) ? $batchResult : 'Error al generar tarjetas de afiliación.');
            }

            $documents[] = [
                'label' => 'Tarjetas de afiliación ('.$totalTarjetasForLayout.' afiliados)',
                'kind' => 'tarjeta',
                'filename' => $combinedFilename,
                'preview_url' => asset('storage/tarjeta-afiliacion/'.$combinedFilename).'?t='.$version,
            ];

            self::writeIndividualTarjetas(
                $record,
                $planDesc,
                $frecuencia,
                $cobertura,
                $desde,
                $hasta,
                $useIndividualAffiliateCardLayout,
                $brand,
                $legacyTarjetaCount,
            );
        } else {
            foreach ($record->affiliates as $affiliate) {
                $data = self::tarjetaPayload(
                    record: $record,
                    name: (string) $affiliate->full_name,
                    ci: (string) $affiliate->nro_identificacion,
                    planDesc: $planDesc,
                    frecuencia: $frecuencia,
                    cobertura: $cobertura,
                    desde: $desde,
                    hasta: $hasta,
                    outputFilename: 'TAR-'.$record->code.'-'.$affiliate->id.'.pdf',
                    useIndividualAffiliateCardLayout: $useIndividualAffiliateCardLayout,
                    brand: $brand,
                );

                $ok = TarjetaAfiliacionController::generateTarjetaAfiliacion(
                    $data,
                    silent: true,
                    ensureOutputDirectory: false,
                    applyResourceLimits: false,
                );
                if ($ok !== true) {
                    throw new RuntimeException(is_string($ok) ? $ok : 'Error al generar tarjeta de afiliación.');
                }

                $filename = $data['output_filename'];
                $documents[] = [
                    'label' => 'Tarjeta — '.$affiliate->full_name,
                    'kind' => 'tarjeta',
                    'filename' => $filename,
                    'preview_url' => asset('storage/tarjeta-afiliacion/'.$filename).'?t='.$version,
                ];
            }

            if ($legacyTarjetaCount === 1) {
                $dataLegacy = self::tarjetaPayload(
                    record: $record,
                    name: (string) $record->full_name_ti,
                    ci: (string) $record->nro_identificacion_ti,
                    planDesc: $planDesc,
                    frecuencia: $frecuencia,
                    cobertura: $cobertura,
                    desde: $desde,
                    hasta: $hasta,
                    outputFilename: 'TAR-'.$record->code.'.pdf',
                    useIndividualAffiliateCardLayout: $useIndividualAffiliateCardLayout,
                    brand: $brand,
                );
                $legacy = TarjetaAfiliacionController::generateTarjetaAfiliacion(
                    $dataLegacy,
                    silent: true,
                    ensureOutputDirectory: false,
                    applyResourceLimits: false,
                );
                if ($legacy !== true) {
                    throw new RuntimeException(is_string($legacy) ? $legacy : 'Error al generar tarjeta estándar.');
                }

                $documents[] = [
                    'label' => 'Tarjeta — titular',
                    'kind' => 'tarjeta',
                    'filename' => $dataLegacy['output_filename'],
                    'preview_url' => asset('storage/tarjeta-afiliacion/'.$dataLegacy['output_filename']).'?t='.$version,
                ];
            }
        }

        $condicionadoPath = self::condicionadoAbsolutePathForAffiliation($record);
        if ($condicionadoPath !== null) {
            $condBasename = basename($condicionadoPath);
            $relative = self::publicStorageRelativePath($condicionadoPath) ?? $condBasename;
            $documents[] = [
                'label' => 'Condiciones del plan',
                'kind' => 'condicionado',
                'filename' => $condBasename,
                'preview_url' => asset('storage/'.$relative).'?t='.$version,
            ];
        }

        try {
            ViveplusDocumentWebhookDispatcher::dispatchForIndividual($record, $userId);
        } catch (Throwable $exception) {
            Log::error('AffiliationBusinessDocumentsService: no se pudo encolar el webhook de ViVEplus', [
                'affiliation_code' => $record->code,
                'error' => $exception->getMessage(),
            ]);
        }

        return ['documents' => $documents];
    }

    /**
     * Escribe un PDF de carnet por persona para el webhook (además del combinado de vista previa).
     */
    private static function writeIndividualTarjetas(
        Affiliation $record,
        string $planDesc,
        mixed $frecuencia,
        mixed $cobertura,
        string $desde,
        string $hasta,
        bool $useIndividualAffiliateCardLayout,
        ?WhiteCompanyDocumentBrand $brand,
        int $legacyTarjetaCount,
    ): void {
        foreach ($record->affiliates as $affiliate) {
            $data = self::tarjetaPayload(
                record: $record,
                name: (string) $affiliate->full_name,
                ci: (string) $affiliate->nro_identificacion,
                planDesc: $planDesc,
                frecuencia: $frecuencia,
                cobertura: $cobertura,
                desde: $desde,
                hasta: $hasta,
                outputFilename: 'TAR-'.$record->code.'-'.$affiliate->id.'.pdf',
                useIndividualAffiliateCardLayout: $useIndividualAffiliateCardLayout,
                brand: $brand,
            );

            $ok = TarjetaAfiliacionController::generateTarjetaAfiliacion(
                $data,
                silent: true,
                ensureOutputDirectory: false,
                applyResourceLimits: false,
            );
            if ($ok !== true) {
                throw new RuntimeException(is_string($ok) ? $ok : 'Error al generar tarjeta de afiliación.');
            }
        }

        if ($legacyTarjetaCount !== 1) {
            return;
        }

        $dataLegacy = self::tarjetaPayload(
            record: $record,
            name: (string) $record->full_name_ti,
            ci: (string) $record->nro_identificacion_ti,
            planDesc: $planDesc,
            frecuencia: $frecuencia,
            cobertura: $cobertura,
            desde: $desde,
            hasta: $hasta,
            outputFilename: 'TAR-'.$record->code.'.pdf',
            useIndividualAffiliateCardLayout: $useIndividualAffiliateCardLayout,
            brand: $brand,
        );
        $legacy = TarjetaAfiliacionController::generateTarjetaAfiliacion(
            $dataLegacy,
            silent: true,
            ensureOutputDirectory: false,
            applyResourceLimits: false,
        );
        if ($legacy !== true) {
            throw new RuntimeException(is_string($legacy) ? $legacy : 'Error al generar tarjeta estándar.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function tarjetaPayload(
        Affiliation $record,
        string $name,
        string $ci,
        string $planDesc,
        mixed $frecuencia,
        mixed $cobertura,
        string $desde,
        string $hasta,
        string $outputFilename = '',
        bool $useIndividualAffiliateCardLayout = false,
        ?WhiteCompanyDocumentBrand $brand = null,
    ): array {
        $planId = $record->plan_id !== null ? (int) $record->plan_id : null;
        $tdecTag = TarjetaAfiliacionQrPlanCatalog::displayTagForPlan($planId, $planDesc);

        $payload = [
            'name' => $name,
            'ci' => $ci,
            'code' => $record->code,
            'plan' => $planDesc,
            'plan_id' => $planId,
            'plan_tarjeta_etiqueta' => $brand?->planShortLabel($planId, $tdecTag) ?? $tdecTag,
            'frecuencia' => $frecuencia,
            'cobertura' => $cobertura,
            'desde' => $desde,
            'hasta' => $hasta,
        ];

        if ($outputFilename !== '') {
            $payload['output_filename'] = $outputFilename;
        }

        if ($useIndividualAffiliateCardLayout) {
            $payload['card_layout'] = 'individual-affiliation';
            $payload['template_key'] = 'individual-affiliation';
        }

        $templatePath = $brand?->carnetCompiledPdfAbsolutePath;
        if (is_string($templatePath) && $templatePath !== '') {
            $payload['template_path'] = $templatePath;
            $payload['template_key'] = AffiliateCardPageLayout::TEMPLATE_INDIVIDUAL_AFFILIATION_ALLIED;
            $payload['card_layout'] = AffiliateCardPageLayout::TEMPLATE_INDIVIDUAL_AFFILIATION;
        }

        return $payload;
    }

    private static function publicStorageRelativePath(string $absolutePath): ?string
    {
        $candidates = [
            realpath(storage_path('app/public')) ?: storage_path('app/public'),
            realpath(public_path('storage')) ?: public_path('storage'),
        ];
        $normalized = realpath($absolutePath) ?: $absolutePath;

        foreach ($candidates as $root) {
            if (str_starts_with($normalized, $root)) {
                return str_replace('\\', '/', ltrim(substr($normalized, strlen($root)), DIRECTORY_SEPARATOR));
            }
        }

        return basename($absolutePath);
    }

    private static function purgeExistingGeneratedDocuments(Affiliation $record): void
    {
        $certificatePath = public_path('storage/certificados-doc/CER-'.$record->code.'.pdf');

        if (is_file($certificatePath)) {
            unlink($certificatePath);
        }

        $tarjetaDirectory = public_path('storage/tarjeta-afiliacion/');
        $pattern = $tarjetaDirectory.'TAR-'.$record->code.'*.pdf';

        foreach (glob($pattern) ?: [] as $tarjetaPath) {
            if (is_file($tarjetaPath)) {
                unlink($tarjetaPath);
            }
        }
    }

    private static function vigenciaHasta(?string $effectiveDate): string
    {
        if (empty($effectiveDate)) {
            return '';
        }

        try {
            return Carbon::createFromFormat('d/m/Y', $effectiveDate)->addYear()->format('d/m/Y');
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Rutas absolutas de los PDF generados para adjuntar al correo.
     *
     * @return array<int, string>
     */
    public static function absolutePdfPathsForAffiliation(Affiliation $record): array
    {
        $record->loadMissing('affiliates');

        $paths = [
            public_path('storage/certificados-doc/CER-'.$record->code.'.pdf'),
        ];

        $combinedCarnetsPath = public_path('storage/tarjeta-afiliacion/TAR-'.$record->code.'-carnets.pdf');

        if (is_file($combinedCarnetsPath)) {
            $paths[] = $combinedCarnetsPath;
        } else {
            foreach ($record->affiliates as $affiliate) {
                $paths[] = public_path('storage/tarjeta-afiliacion/TAR-'.$record->code.'-'.$affiliate->id.'.pdf');
            }

            if (self::shouldGenerateLegacyTitularTarjeta($record)) {
                $paths[] = public_path('storage/tarjeta-afiliacion/TAR-'.$record->code.'.pdf');
            }
        }

        $condicionado = self::condicionadoAbsolutePathForAffiliation($record);
        if ($condicionado !== null) {
            $paths[] = $condicionado;
        }

        return array_values(array_filter($paths, fn (string $p): bool => is_file($p)));
    }
}
