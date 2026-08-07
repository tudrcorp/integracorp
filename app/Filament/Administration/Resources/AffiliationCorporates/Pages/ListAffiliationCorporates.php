<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\AffiliationCorporates\Pages;

use App\Filament\Administration\Resources\AffiliationCorporates\AffiliationCorporateResource;
use App\Filament\Administration\Resources\AffiliationCorporates\Tables\AffiliationCorporatesTable;
use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ListAffiliationCorporates extends ListRecords
{
    protected static string $resource = AffiliationCorporateResource::class;

    protected static ?string $title = 'Afiliaciones corporativas';

    public function getSubheading(): string|Htmlable|null
    {
        $stats = $this->overviewStats();

        return new HtmlString(
            '<div class="mt-2 flex flex-wrap items-stretch gap-2.5">'
            .self::statChip(
                label: 'Total corporativos',
                value: number_format($stats['companies']),
                suffix: 'empresas',
                tone: 'info',
                icon: 'building',
            )
            .self::statChip(
                label: 'Afiliados corporativos',
                value: number_format($stats['affiliates']),
                suffix: 'afiliados',
                tone: 'success',
                icon: 'users',
            )
            .self::statChip(
                label: 'Total neto',
                value: 'US$ '.number_format($stats['total_neto'], 2, ',', '.'),
                suffix: 'afiliaciones',
                tone: 'warning',
                icon: 'currency',
            )
            .'</div>'
        );
    }

    public function getTabs(): array
    {
        return AffiliationCorporatesTable::getTabs();
    }

    /**
     * @return array{companies: int, affiliates: int, total_neto: float}
     */
    private function overviewStats(): array
    {
        return [
            'companies' => (int) AffiliationCorporate::query()->where('status', 'ACTIVA')->count(),
            'affiliates' => (int) AffiliateCorporate::query()->where('status', 'ACTIVO')->count(),
            'total_neto' => (float) AffiliationCorporate::query()->sum('total_amount'),
        ];
    }

    private static function statChip(
        string $label,
        string $value,
        string $suffix,
        string $tone,
        string $icon,
    ): string {
        [$shell, $iconWrap, $labelClass, $valueClass, $suffixClass] = match ($tone) {
            'warning' => [
                'border-amber-400/35 bg-gradient-to-br from-amber-50 via-white to-amber-100/70 shadow-sm shadow-amber-500/10 dark:border-amber-400/30 dark:from-amber-500/20 dark:via-gray-900/80 dark:to-amber-900/20 dark:shadow-amber-900/20',
                'bg-amber-500/15 text-amber-700 ring-1 ring-amber-500/25 dark:bg-amber-400/15 dark:text-amber-200 dark:ring-amber-300/20',
                'text-amber-800/70 dark:text-amber-200/70',
                'text-amber-950 dark:text-amber-50',
                'text-amber-800/55 dark:text-amber-200/55',
            ],
            'info' => [
                'border-sky-400/35 bg-gradient-to-br from-sky-50 via-white to-sky-100/70 shadow-sm shadow-sky-500/10 dark:border-sky-400/30 dark:from-sky-500/20 dark:via-gray-900/80 dark:to-sky-900/20 dark:shadow-sky-900/20',
                'bg-sky-500/15 text-sky-700 ring-1 ring-sky-500/25 dark:bg-sky-400/15 dark:text-sky-200 dark:ring-sky-300/20',
                'text-sky-800/70 dark:text-sky-200/70',
                'text-sky-950 dark:text-sky-50',
                'text-sky-800/55 dark:text-sky-200/55',
            ],
            default => [
                'border-emerald-400/35 bg-gradient-to-br from-emerald-50 via-white to-emerald-100/70 shadow-sm shadow-emerald-500/10 dark:border-emerald-400/30 dark:from-emerald-500/20 dark:via-gray-900/80 dark:to-emerald-900/20 dark:shadow-emerald-900/20',
                'bg-emerald-500/15 text-emerald-700 ring-1 ring-emerald-500/25 dark:bg-emerald-400/15 dark:text-emerald-200 dark:ring-emerald-300/20',
                'text-emerald-800/70 dark:text-emerald-200/70',
                'text-emerald-950 dark:text-emerald-50',
                'text-emerald-800/55 dark:text-emerald-200/55',
            ],
        };

        return '<div class="inline-flex min-w-[11.5rem] items-center gap-3 rounded-2xl border px-3.5 py-2.5 '.$shell.'">'
            .'<span class="inline-flex size-9 shrink-0 items-center justify-center rounded-xl '.$iconWrap.'">'
            .self::statIcon($icon)
            .'</span>'
            .'<span class="flex min-w-0 flex-col gap-0.5">'
            .'<span class="text-[10px] font-semibold uppercase tracking-[0.14em] '.$labelClass.'">'.e($label).'</span>'
            .'<span class="flex flex-wrap items-baseline gap-x-1.5 gap-y-0">'
            .'<strong class="text-lg font-bold leading-none tabular-nums tracking-tight '.$valueClass.'">'.e($value).'</strong>'
            .'<span class="text-[11px] font-medium '.$suffixClass.'">'.e($suffix).'</span>'
            .'</span>'
            .'</span>'
            .'</div>';
    }

    private static function statIcon(string $icon): string
    {
        return match ($icon) {
            'currency' => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4" aria-hidden="true"><path d="M10.75 10.818v2.614A3.13 3.13 0 0 0 11.888 13c.482-.315.612-.648.612-.875 0-.227-.13-.56-.612-.875a3.13 3.13 0 0 0-1.138-.432ZM8.33 8.62c.053.055.115.108.186.163.208.156.46.288.736.393V7.542A2.849 2.849 0 0 0 8.33 8.62ZM13.125 8A3.626 3.626 0 0 0 10 4.875v.75c.192.104.38.226.56.365.48.372.84.87.84 1.51 0 .192-.04.37-.11.53A3.626 3.626 0 0 0 13.125 8ZM6.875 8A3.626 3.626 0 0 0 9.25 5.99V5.125A3.626 3.626 0 0 0 6.875 8ZM10 15.125A5.125 5.125 0 1 1 10 4.875a5.125 5.125 0 0 1 0 10.25Z"/><path d="M9.25 12.719A3.137 3.137 0 0 1 8.112 13c-.482.315-.612.648-.612.875 0 .227.13.56.612.875a3.13 3.13 0 0 0 1.138.432v-2.463ZM10.75 7.042A2.85 2.85 0 0 1 11.67 8.62c-.053.055-.115.108-.186.163A3.126 3.126 0 0 1 10.75 9.175V7.042Z"/></svg>
SVG,
            'building' => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4" aria-hidden="true"><path fill-rule="evenodd" d="M4.25 2A2.25 2.25 0 0 0 2 4.25v11.5A2.25 2.25 0 0 0 4.25 18h11.5A2.25 2.25 0 0 0 18 15.75V4.25A2.25 2.25 0 0 0 15.75 2H4.25ZM6 6.75A.75.75 0 0 1 6.75 6h.5a.75.75 0 0 1 0 1.5h-.5A.75.75 0 0 1 6 6.75ZM6.75 9a.75.75 0 0 0 0 1.5h.5a.75.75 0 0 0 0-1.5h-.5ZM6 12.75a.75.75 0 0 1 .75-.75h.5a.75.75 0 0 1 0 1.5h-.5a.75.75 0 0 1-.75-.75ZM9.75 6a.75.75 0 0 0 0 1.5h.5a.75.75 0 0 0 0-1.5h-.5ZM9 9.75A.75.75 0 0 1 9.75 9h.5a.75.75 0 0 1 0 1.5h-.5A.75.75 0 0 1 9 9.75ZM9.75 12a.75.75 0 0 0 0 1.5h.5a.75.75 0 0 0 0-1.5h-.5ZM13 6.75a.75.75 0 0 1 .75-.75h.5a.75.75 0 0 1 0 1.5h-.5a.75.75 0 0 1-.75-.75ZM13.75 9a.75.75 0 0 0 0 1.5h.5a.75.75 0 0 0 0-1.5h-.5ZM13 12.75a.75.75 0 0 1 .75-.75h.5a.75.75 0 0 1 0 1.5h-.5a.75.75 0 0 1-.75-.75Z" clip-rule="evenodd"/></svg>
SVG,
            default => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4" aria-hidden="true"><path d="M7 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM14.5 9a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5ZM1.615 16.428a1.224 1.224 0 0 1-.183-1.661A9.977 9.977 0 0 1 7 12.5c2.042 0 3.928.612 5.55 1.661a1.224 1.224 0 0 1-.183 1.661A9.977 9.977 0 0 1 7 18.5a9.977 9.977 0 0 1-5.385-2.072ZM12.707 15.293a1 1 0 0 1 1.414 0A7.969 7.969 0 0 1 17.5 18a1 1 0 1 1-2 0 5.969 5.969 0 0 0-2.379-4.707 1 1 0 0 1 0-1.414Z"/></svg>
SVG,
        };
    }
}
