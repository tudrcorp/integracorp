<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Commissions\Pages;

use App\Filament\Administration\Resources\Commissions\CommissionResource;
use App\Models\Commission;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

class ListCommissions extends ListRecords
{
    protected static string $resource = CommissionResource::class;

    protected static ?string $title = 'Detallado de Comisiones';

    public function getSubheading(): string|Htmlable|null
    {
        $stats = $this->overviewStats();

        return new HtmlString(
            '<div class="mt-2 flex flex-wrap items-stretch gap-2.5">'
            .self::statChip(
                label: 'Comisiones totales USD',
                value: 'US$ '.number_format($stats['usd_year'], 2, ',', '.'),
                suffix: 'Año '.$stats['year'].' · '.$stats['month_label'].': US$ '.number_format($stats['usd_month'], 2, ',', '.'),
                tone: 'info',
                icon: 'currency',
            )
            .self::statChip(
                label: 'Comisiones totales VES',
                value: 'Bs. '.number_format($stats['ves_year'], 2, ',', '.'),
                suffix: 'Año '.$stats['year'].' · '.$stats['month_label'].': Bs. '.number_format($stats['ves_month'], 2, ',', '.'),
                tone: 'success',
                icon: 'banknotes',
            )
            .'</div>'
        );
    }

    /**
     * @return array{
     *     year: int,
     *     month_label: string,
     *     usd_year: float,
     *     usd_month: float,
     *     ves_year: float,
     *     ves_month: float
     * }
     */
    private function overviewStats(): array
    {
        $now = Carbon::now();
        $startOfYear = $now->copy()->startOfYear();
        $endOfYear = $now->copy()->endOfYear();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();

        $usdExpression = 'COALESCE(commission_agency_master_usd, 0) + COALESCE(commission_agency_general_usd, 0) + COALESCE(commission_agent_usd, 0) + COALESCE(commission_sub_agent_usd, 0) + COALESCE(commission_referidor_usd, 0)';
        $vesExpression = 'COALESCE(commission_agency_master_ves, 0) + COALESCE(commission_agency_general_ves, 0) + COALESCE(commission_agent_ves, 0) + COALESCE(commission_sub_agent_ves, 0) + COALESCE(commission_referidor_ves, 0)';

        return [
            'year' => (int) $now->year,
            'month_label' => ucfirst($now->translatedFormat('F')),
            'usd_year' => $this->sumCommissions($usdExpression, $startOfYear, $endOfYear),
            'usd_month' => $this->sumCommissions($usdExpression, $startOfMonth, $endOfMonth),
            'ves_year' => $this->sumCommissions($vesExpression, $startOfYear, $endOfYear),
            'ves_month' => $this->sumCommissions($vesExpression, $startOfMonth, $endOfMonth),
        ];
    }

    private function sumCommissions(string $expression, Carbon $from, Carbon $to): float
    {
        /** @var Builder<Commission> $query */
        $query = Commission::query()
            ->whereBetween('created_at', [$from, $to]);

        return (float) ($query
            ->selectRaw("SUM({$expression}) as total")
            ->value('total') ?? 0);
    }

    private static function statChip(
        string $label,
        string $value,
        string $suffix,
        string $tone,
        string $icon,
    ): string {
        [$shell, $iconWrap, $labelClass, $valueClass, $suffixClass] = match ($tone) {
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

        return '<div class="inline-flex min-w-[14rem] max-w-full items-center gap-3 rounded-2xl border px-3.5 py-2.5 '.$shell.'">'
            .'<span class="inline-flex size-9 shrink-0 items-center justify-center rounded-xl '.$iconWrap.'">'
            .self::statIcon($icon)
            .'</span>'
            .'<span class="flex min-w-0 flex-col gap-0.5">'
            .'<span class="text-[10px] font-semibold uppercase tracking-[0.14em] '.$labelClass.'">'.e($label).'</span>'
            .'<strong class="text-lg font-bold leading-none tabular-nums tracking-tight '.$valueClass.'">'.e($value).'</strong>'
            .'<span class="text-[11px] font-medium leading-snug '.$suffixClass.'">'.e($suffix).'</span>'
            .'</span>'
            .'</div>';
    }

    private static function statIcon(string $icon): string
    {
        return match ($icon) {
            'banknotes' => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4" aria-hidden="true"><path fill-rule="evenodd" d="M1 4a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V4Zm12 4a3 3 0 1 1-6 0 3 3 0 0 1 6 0ZM4 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm13-1a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM1.75 14.5a.75.75 0 0 0 0 1.5c4.004 0 7.77.826 11.24 2.34.18.076.38.076.56 0A25.883 25.883 0 0 1 18.25 16a.75.75 0 0 0 0-1.5 27.333 27.333 0 0 0-4.66-.99v-.75a.75.75 0 0 0-1.5 0v.67a27.67 27.67 0 0 0-4.66-.67v-.75a.75.75 0 0 0-1.5 0v.84c-1.56.17-3.06.48-4.48.91Z" clip-rule="evenodd"/></svg>
SVG,
            default => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4" aria-hidden="true"><path d="M10.75 10.818v2.614A3.13 3.13 0 0 0 11.888 13c.482-.315.612-.648.612-.875 0-.227-.13-.56-.612-.875a3.13 3.13 0 0 0-1.138-.432ZM8.33 8.62c.053.055.115.108.186.163.208.156.46.288.736.393V7.542A2.849 2.849 0 0 0 8.33 8.62ZM13.125 8A3.626 3.626 0 0 0 10 4.875v.75c.192.104.38.226.56.365.48.372.84.87.84 1.51 0 .192-.04.37-.11.53A3.626 3.626 0 0 0 13.125 8ZM6.875 8A3.626 3.626 0 0 0 9.25 5.99V5.125A3.626 3.626 0 0 0 6.875 8ZM10 15.125A5.125 5.125 0 1 1 10 4.875a5.125 5.125 0 0 1 0 10.25Z"/><path d="M9.25 12.719A3.137 3.137 0 0 1 8.112 13c-.482.315-.612.648-.612.875 0 .227.13.56.612.875a3.13 3.13 0 0 0 1.138.432v-2.463ZM10.75 7.042A2.85 2.85 0 0 1 11.67 8.62c-.053.055-.115.108-.186.163A3.126 3.126 0 0 1 10.75 9.175V7.042Z"/></svg>
SVG,
        };
    }
}
