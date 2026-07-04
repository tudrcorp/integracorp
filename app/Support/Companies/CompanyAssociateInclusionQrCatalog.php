<?php

declare(strict_types=1);

namespace App\Support\Companies;

use Illuminate\Support\Facades\Storage;

final class CompanyAssociateInclusionQrCatalog
{
    public const PLAN_LABEL = 'INCLUSIÓN';

    public const QR_FILENAME = 'qr-plan-inclusion.png';

    public const LOGO_FILENAME = 'logo-tarjeta-inclusion-qr.png';

    public const LOGO_SOURCE_RELATIVE_PATH = 'image/logo-qr-inclusion.png';

    public const LOGO_CENTER_SCALE = 0.42;

    public const PDF_FILENAME = 'canales-de-comunicacion.pdf';

    public const PDF_STORAGE_DIRECTORY = 'tarjeta-afiliacion/documentos';

    public const QR_STORAGE_DIRECTORY = 'tarjeta-afiliacion/planes';

    public static function pdfStoragePath(): string
    {
        return self::PDF_STORAGE_DIRECTORY.'/'.self::PDF_FILENAME;
    }

    public static function qrStoragePath(): string
    {
        return self::QR_STORAGE_DIRECTORY.'/'.self::QR_FILENAME;
    }

    public static function logoStoragePath(): string
    {
        return self::QR_STORAGE_DIRECTORY.'/'.self::LOGO_FILENAME;
    }

    public static function publicBaseUrl(): string
    {
        $configured = config('services.company_associate_inclusion.public_url');

        if (filled($configured)) {
            return rtrim((string) $configured, '/');
        }

        if (app()->environment('production')) {
            return 'https://integracorp.tudrgroup.com';
        }

        if (! app()->runningInConsole() && app()->bound('request')) {
            $request = request();

            if (filled($request->getHttpHost())) {
                return $request->getSchemeAndHttpHost();
            }
        }

        return rtrim((string) config('app.url'), '/');
    }

    public static function pdfPublicUrl(): string
    {
        return self::publicBaseUrl().'/storage/'.self::pdfStoragePath();
    }

    public static function qrPublicUrl(): string
    {
        return self::publicBaseUrl().'/storage/'.self::qrStoragePath();
    }

    public static function qrExists(): bool
    {
        return is_file(Storage::disk('public')->path(self::qrStoragePath()));
    }

    public static function logoSourceAbsolutePath(): string
    {
        return public_path(self::LOGO_SOURCE_RELATIVE_PATH);
    }

    public static function logoPublicUrl(): string
    {
        return asset(self::LOGO_SOURCE_RELATIVE_PATH);
    }

    public static function qrPreviewUrl(): ?string
    {
        $absolutePath = Storage::disk('public')->path(self::qrStoragePath());

        if (! is_file($absolutePath)) {
            return null;
        }

        return self::qrPublicUrl().'?t='.filemtime($absolutePath);
    }
}
