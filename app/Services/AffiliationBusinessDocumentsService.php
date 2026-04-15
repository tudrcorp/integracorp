<?php

namespace App\Services;

use App\Http\Controllers\AffiliationController;
use App\Http\Controllers\TarjetaAfiliacionController;
use App\Models\Affiliate;
use App\Models\Affiliation;
use Carbon\Carbon;
use RuntimeException;

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

    public static function condicionadoAbsolutePathForAffiliation(Affiliation $record): ?string
    {
        $basename = self::condicionadoBasenameForPlanId($record->plan_id);
        if ($basename === null) {
            return null;
        }

        $path = storage_path('app/public/condicionados/'.$basename);

        return is_file($path) ? $path : null;
    }

    /**
     * Regenera el certificado (uno) y una tarjeta PDF por cada familiar en `affiliates`.
     *
     * @return array{documents: array<int, array{label: string, kind: string, filename: string, preview_url: string}>}
     */
    public static function regenerateCertificateAndTarjetas(Affiliation $record, ?int $userId, bool $notifyCertificate = false): array
    {
        $record->loadMissing(['affiliates', 'plan.benefitPlans', 'coverage', 'agent', 'agency']);

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
        $planDesc = $record->plan?->description ?? '';
        $cobertura = $record->coverage?->price ?? '';
        $frecuencia = $record->payment_frequency;

        foreach ($record->affiliates as $affiliate) {
            $data = [
                'name' => $affiliate->full_name,
                'ci' => $affiliate->nro_identificacion,
                'code' => $record->code,
                'plan' => $planDesc,
                'frecuencia' => $frecuencia,
                'cobertura' => $cobertura,
                'desde' => $desde,
                'hasta' => $hasta,
                'output_filename' => 'TAR-'.$record->code.'-'.$affiliate->id.'.pdf',
            ];

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
            $dataLegacy = [
                'name' => $record->full_name_ti,
                'ci' => $record->nro_identificacion_ti,
                'code' => $record->code,
                'plan' => $planDesc,
                'frecuencia' => $frecuencia,
                'cobertura' => $cobertura,
                'desde' => $desde,
                'hasta' => $hasta,
                'output_filename' => 'TAR-'.$record->code.'.pdf',
            ];
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

        $condicionadoPath = self::condicionadoAbsolutePathForAffiliation($record);
        if ($condicionadoPath !== null) {
            $condBasename = basename($condicionadoPath);
            $documents[] = [
                'label' => 'Condiciones del plan',
                'kind' => 'condicionado',
                'filename' => $condBasename,
                'preview_url' => asset('storage/condicionados/'.$condBasename).'?t='.$version,
            ];
        }

        return ['documents' => $documents];
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

        foreach ($record->affiliates as $affiliate) {
            $paths[] = public_path('storage/tarjeta-afiliacion/TAR-'.$record->code.'-'.$affiliate->id.'.pdf');
        }

        if (self::shouldGenerateLegacyTitularTarjeta($record)) {
            $paths[] = public_path('storage/tarjeta-afiliacion/TAR-'.$record->code.'.pdf');
        }

        $condicionado = self::condicionadoAbsolutePathForAffiliation($record);
        if ($condicionado !== null) {
            $paths[] = $condicionado;
        }

        return array_values(array_filter($paths, fn (string $p): bool => is_file($p)));
    }
}
