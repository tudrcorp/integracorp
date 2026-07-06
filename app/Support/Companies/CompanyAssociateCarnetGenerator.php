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
            'preview_url' => self::publicUrlFor($associate) ?? asset('storage/tarjeta-afiliacion/'.$filename),
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

    public static function publicUrlFor(CompanyAssociate $associate): ?string
    {
        $path = self::absolutePathFor($associate);

        if ($path === null) {
            return null;
        }

        return asset('storage/tarjeta-afiliacion/'.self::filenameFor($associate)).'?v='.filemtime($path);
    }

    /**
     * @return array{desde: string, hasta: string}
     */
    public static function cardValidityDates(CompanyAssociate $associate): array
    {
        if (filled($associate->date_init) || filled($associate->date_end)) {
            return [
                'desde' => (string) ($associate->date_init ?? ''),
                'hasta' => (string) ($associate->date_end ?? ''),
            ];
        }

        $flightDate = self::formatFlightDate($associate->flight_date);

        if ($flightDate !== '') {
            return [
                'desde' => $flightDate,
                'hasta' => $flightDate,
            ];
        }

        return [
            'desde' => $associate->registered_at?->format('d/m/Y') ?? '',
            'hasta' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function payloadFor(CompanyAssociate $associate, string $filename): array
    {
        $validity = self::cardValidityDates($associate);

        return [
            'name' => $associate->full_name,
            'ci' => $associate->identity_card,
            'code' => self::associateCode($associate),
            'plan' => self::PLAN_LABEL,
            'template_key' => 'inclusion',
            'plan_qr_filename' => 'qr-plan-inclusion.png',
            'frecuencia' => self::PAYMENT_FREQUENCY_LABEL,
            'cobertura' => self::COVERAGE_LABEL,
            'desde' => $validity['desde'],
            'hasta' => $validity['hasta'],
            'output_filename' => $filename,
        ];
    }

    public static function formatFlightDate(mixed $flightDate): string
    {
        if (blank($flightDate)) {
            return '';
        }

        if ($flightDate instanceof \DateTimeInterface) {
            return $flightDate->format('d/m/Y');
        }

        $stringValue = trim((string) $flightDate);

        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y'] as $format) {
            try {
                return \Carbon\Carbon::createFromFormat($format, $stringValue)->format('d/m/Y');
            } catch (\Throwable) {
            }
        }

        try {
            return \Carbon\Carbon::parse($stringValue)->format('d/m/Y');
        } catch (\Throwable) {
            return '';
        }
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
