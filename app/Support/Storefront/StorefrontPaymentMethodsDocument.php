<?php

declare(strict_types=1);

namespace App\Support\Storefront;

use App\Support\QrCode\GdPngQrCodeGenerator;
use Illuminate\Support\Facades\Storage;

/**
 * PDF de métodos de pago nacionales e internacionales que el cliente
 * descarga al escanear el QR del pie de la cotización PWA.
 */
final class StorefrontPaymentMethodsDocument
{
    public const STORAGE_DIRECTORY = 'pwa-documents';

    public const DOWNLOAD_FILENAME = 'metodos-de-pago-tdg.pdf';

    public static function absolutePath(): ?string
    {
        $directory = Storage::disk('public')->path(self::STORAGE_DIRECTORY);

        if (! is_dir($directory)) {
            return null;
        }

        $matches = glob($directory.'/Metodos de pago*.pdf') ?: [];

        if ($matches === []) {
            $matches = glob($directory.'/*.pdf') ?: [];
        }

        $path = $matches[0] ?? null;

        return is_string($path) && is_file($path) ? $path : null;
    }

    public static function exists(): bool
    {
        return self::absolutePath() !== null;
    }

    public static function publicUrl(): string
    {
        return route('storefront.documents.payment-methods', absolute: true);
    }

    public static function qrDataUri(): string
    {
        $png = GdPngQrCodeGenerator::generate(
            self::publicUrl(),
            220,
            'M',
            1,
        );

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
