<?php

declare(strict_types=1);

namespace App\Support\Affiliations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Comprobante (voucher) que se cargó con un pago de afiliación, individual
 * (`paid_memberships`) o corporativa (`paid_membership_corporates`).
 *
 * Cada pago puede traer uno en dólares (`document_usd`) y otro en bolívares
 * (`document_ves`), guardados en el disco `public`. Los registros sin archivo
 * guardan «N/A» o vacío.
 */
final class PaymentVoucherFile
{
    public const DISK = 'public';

    public const CURRENCY_USD = 'usd';

    public const CURRENCY_VES = 'ves';

    /** @var list<string> */
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'jfif', 'svg', 'bmp'];

    public static function path(Model $payment, string $currency): ?string
    {
        $raw = match ($currency) {
            self::CURRENCY_USD => $payment->getAttribute('document_usd'),
            self::CURRENCY_VES => $payment->getAttribute('document_ves'),
            default => null,
        };

        $path = trim((string) $raw);

        if ($path === '' || strtoupper($path) === 'N/A') {
            return null;
        }

        /** Algunos registros viejos guardaron la ruta pública completa. */
        return ltrim((string) preg_replace('#^/?storage/#', '', $path), '/');
    }

    public static function exists(?string $path): bool
    {
        return $path !== null && ! str_contains($path, '..') && Storage::disk(self::DISK)->exists($path);
    }

    /**
     * @return 'image'|'pdf'|'other'
     */
    public static function kind(?string $path): string
    {
        $extension = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));

        return match (true) {
            in_array($extension, self::IMAGE_EXTENSIONS, true) => 'image',
            $extension === 'pdf' => 'pdf',
            default => 'other',
        };
    }

    public static function url(string $path): string
    {
        return Storage::disk(self::DISK)->url($path);
    }

    /**
     * «voucher-TDEC-IND-000436-pago-12-usd.jpg»: identifica el pago al descargarlo.
     */
    public static function downloadName(Model $payment, string $currency, string $path, ?string $affiliationCode = null): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $code = Str::slug(filled($affiliationCode) ? (string) $affiliationCode : 'afiliacion');

        return 'voucher-'.strtoupper($code).'-pago-'.$payment->getKey().'-'.$currency.($extension !== '' ? '.'.$extension : '');
    }

    public static function currencyLabel(string $currency): string
    {
        return $currency === self::CURRENCY_VES ? 'Bs.' : 'US$';
    }
}
