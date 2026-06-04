<?php

declare(strict_types=1);

namespace App\Support\Filament;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

final class GlobalSearchSupplierStatusLabel
{
    public static function sistemaHtml(?string $status): Htmlable|string
    {
        return self::badge($status, self::sistemaVariant($status));
    }

    public static function convenioHtml(?string $status): Htmlable|string
    {
        return self::badge($status, self::convenioVariant($status));
    }

    private static function badge(?string $status, string $variant): Htmlable|string
    {
        if (! filled($status)) {
            return '—';
        }

        $label = e(trim((string) $status));

        return new HtmlString(
            '<span class="fi-global-search-supplier-badge fi-global-search-supplier-badge--'.$variant.'">'.$label.'</span>'
        );
    }

    private static function sistemaVariant(?string $status): string
    {
        $normalized = mb_strtoupper(trim((string) $status));

        return match (true) {
            str_contains($normalized, 'AFILIADO') && ! str_contains($normalized, 'PROCESO') => 'sistema-afiliado',
            str_contains($normalized, 'ACTIVO') => 'sistema-activo',
            str_contains($normalized, 'PROCESO') => 'sistema-proceso',
            str_contains($normalized, 'INACTIVO'), str_contains($normalized, 'SUSPEND') => 'sistema-inactivo',
            default => 'sistema-default',
        };
    }

    private static function convenioVariant(?string $status): string
    {
        $normalized = mb_strtoupper(trim((string) $status));

        return match (true) {
            str_contains($normalized, 'PREFERENCIAL') => 'convenio-preferencial',
            str_contains($normalized, 'GENERAL') => 'convenio-general',
            str_contains($normalized, 'VIP') => 'convenio-vip',
            default => 'convenio-default',
        };
    }
}
