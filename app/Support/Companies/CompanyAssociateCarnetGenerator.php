<?php

declare(strict_types=1);

namespace App\Support\Companies;

use App\Http\Controllers\TarjetaAfiliacionController;
use App\Models\CompanyAssociate;
use RuntimeException;

final class CompanyAssociateCarnetGenerator
{
    private const PLAN_LABEL = 'INCLUSIÓN';

    private const COVERAGE_LABEL = 'LOCAL';

    private const PAYMENT_FREQUENCY_LABEL = 'CONTADO';

    /**
     * @return array{filename: string, preview_url: string, absolute_path: string}
     */
    public static function generate(CompanyAssociate $associate): array
    {
        $associate->loadMissing(['company', 'responsible']);

        $filename = self::filenameFor($associate);
        $data = self::payloadFor($associate, $filename);

        $result = TarjetaAfiliacionController::generateTarjetaAfiliacion(
            $data,
            silent: true,
            ensureOutputDirectory: true,
            applyResourceLimits: true,
        );

        if ($result !== true) {
            throw new RuntimeException(is_string($result) ? $result : 'No se pudo generar el carnet del asociado.');
        }

        $absolutePath = public_path('storage/tarjeta-afiliacion/'.$filename);

        if (! is_file($absolutePath)) {
            throw new RuntimeException('El carnet se generó pero no se encontró el archivo en disco.');
        }

        return [
            'filename' => $filename,
            'preview_url' => asset('storage/tarjeta-afiliacion/'.$filename).'?t='.time(),
            'absolute_path' => $absolutePath,
        ];
    }

    public static function filenameFor(CompanyAssociate $associate): string
    {
        return 'TAR-NB-'.$associate->getKey().'.pdf';
    }

    public static function absolutePathFor(CompanyAssociate $associate): ?string
    {
        $path = public_path('storage/tarjeta-afiliacion/'.self::filenameFor($associate));

        return is_file($path) ? $path : null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function payloadFor(CompanyAssociate $associate, string $filename): array
    {
        return [
            'name' => $associate->full_name,
            'ci' => $associate->identity_card,
            'code' => self::associateCode($associate),
            'plan' => self::PLAN_LABEL,
            'template_key' => 'inclusion',
            'plan_qr_filename' => 'qr-plan-inclusion.png',
            'frecuencia' => self::PAYMENT_FREQUENCY_LABEL,
            'cobertura' => self::COVERAGE_LABEL,
            'desde' => filled($associate->date_init)
                ? (string) $associate->date_init
                : ($associate->registered_at?->format('d/m/Y') ?? ''),
            'hasta' => (string) ($associate->date_end ?? ''),
            'output_filename' => $filename,
        ];
    }

    private static function associateCode(CompanyAssociate $associate): string
    {
        return self::buildAssociateCode($associate);
    }

    public static function buildAssociateCode(CompanyAssociate $associate): string
    {
        return 'NB-'.$associate->company_id.'-'.$associate->getKey();
    }
}
