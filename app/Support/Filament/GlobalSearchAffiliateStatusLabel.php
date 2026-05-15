<?php

declare(strict_types=1);

namespace App\Support\Filament;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

final class GlobalSearchAffiliateStatusLabel
{
    /**
     * Etiqueta de estatus para resultados de búsqueda global (afiliados individuales y corporativos).
     */
    public static function html(?string $status): Htmlable|string
    {
        if (! filled($status)) {
            return '—';
        }

        $normalized = strtoupper(trim((string) $status));
        $label = e((string) $status);

        $base = 'inline-flex items-center rounded-md px-1.5 py-0.5 text-[11px] font-semibold ring-1 ';

        return match ($normalized) {
            'ACTIVO' => new HtmlString(
                '<span class="'.$base.'text-emerald-900 bg-emerald-100 ring-emerald-300/80 dark:bg-emerald-500/15 dark:text-emerald-100 dark:ring-emerald-400/35">'.$label.'</span>'
            ),
            'INACTIVO' => new HtmlString(
                '<span class="'.$base.'text-rose-900 bg-rose-100 ring-rose-300/80 dark:bg-rose-500/15 dark:text-rose-100 dark:ring-rose-400/35">'.$label.'</span>'
            ),
            default => new HtmlString(
                '<span class="'.$base.'text-gray-800 bg-gray-100 ring-gray-300/70 dark:bg-gray-500/15 dark:text-gray-200 dark:ring-gray-400/30">'.$label.'</span>'
            ),
        };
    }
}
