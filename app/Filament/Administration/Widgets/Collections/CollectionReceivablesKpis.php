<?php

declare(strict_types=1);

namespace App\Filament\Administration\Widgets\Collections;

use App\Filament\Administration\Resources\AnnualCollections\AnnualCollectionResource;
use App\Support\Collections\CollectionReceivableReport;
use App\Support\Filament\SummaryCards;
use Carbon\CarbonImmutable;
use Filament\Widgets\Widget;
use Illuminate\Support\Str;

/**
 * KPIs de cuentas por cobrar, a hoy: total por cobrar, vencido en los últimos 30 días y lo
 * que vence en 7 días, cada uno separado en individuales y corporativas; los recibos (avisos
 * de cobro) emitidos del mes y del año; y la franja de plazos de 15, 30, 45 y 60 días. Las
 * cifras por cobrar llevan a la tabla de Cuentas por cobrar con su filtro de vencimiento.
 *
 * Mismo cálculo que tenía el resumen sobre la tabla (`CollectionReceivableReport::summary`):
 * cuotas pendientes de las afiliaciones que se pueden cobrar.
 */
class CollectionReceivablesKpis extends Widget
{
    protected static bool $isDiscovered = false;

    protected string $view = 'filament.administration.collections.receivables-kpis';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $summary = CollectionReceivableReport::summary(AnnualCollectionResource::getEloquentQuery());

        return [
            'tiles' => self::tiles($summary),
            'noticeTiles' => self::noticeTiles(CollectionReceivableReport::issuedNotices()),
            'windows' => self::windows($summary['due_windows']),
        ];
    }

    /**
     * «Recibos emitidos del mes» y «del año»: avisos de cobro por fecha de cobro, sin anulados.
     *
     * @param  array{month: array<string, array{affiliations: int, count: int, amount: float}>, year: array<string, array{affiliations: int, count: int, amount: float}>}  $notices
     * @return list<array<string, mixed>>
     */
    public static function noticeTiles(array $notices, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $tiles = [];

        foreach ([
            'month' => ['Recibos emitidos del mes', Str::ucfirst($today->locale('es')->translatedFormat('F Y')), 'heroicon-o-document-text', SummaryCards::BLUE],
            'year' => ['Recibos emitidos en el '.$today->year, 'Enero a diciembre de '.$today->year, 'heroicon-o-document-duplicate', '#7c3aed'],
        ] as $period => [$label, $periodLabel, $icon, $accent]) {
            $split = $notices[$period];
            $count = $split['individual']['count'] + $split['corporate']['count'];
            $amount = $split['individual']['amount'] + $split['corporate']['amount'];

            $tiles[] = [
                'key' => 'notices-'.$period,
                'label' => $label,
                'icon' => $icon,
                'accent' => $accent,
                'count' => $count,
                'detail' => $periodLabel.' · '.SummaryCards::money($amount),
                'split' => $split,
                'title' => 'Avisos de cobro con fecha de cobro en '.mb_strtolower($periodLabel).', pagados o por pagar (sin anulados)',
            ];
        }

        return $tiles;
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return list<array<string, mixed>>
     */
    public static function tiles(array $summary): array
    {
        $days = CollectionReceivableReport::OVERDUE_RECENT_DAYS;

        return [
            [
                'key' => 'pending',
                'label' => 'Total por cobrar',
                'icon' => 'heroicon-o-banknotes',
                'accent' => SummaryCards::BLUE,
                'amount' => $summary['pending_amount'],
                'detail' => SummaryCards::count($summary['pending_count'], 'cuota', 'cuotas').' · '.SummaryCards::count($summary['rows_count'], 'afiliación', 'afiliaciones'),
                'split' => $summary['pending_by_type'],
                'url' => self::tableUrl(null),
                'title' => 'Ver todas las cuentas por cobrar',
            ],
            [
                'key' => 'overdue',
                'label' => 'Vencido · últimos '.$days.' días',
                'icon' => 'heroicon-o-exclamation-triangle',
                'accent' => SummaryCards::RED,
                'amount' => $summary['overdue_recent_amount'],
                'detail' => SummaryCards::count($summary['overdue_recent_count'], 'cuota', 'cuotas'),
                'split' => $summary['overdue_recent_by_type'],
                'url' => self::tableUrl(CollectionReceivableReport::AGING_OVERDUE_1_30),
                'title' => 'Ver en la tabla lo vencido en los últimos '.$days.' días',
            ],
            [
                'key' => 'due-soon',
                'label' => 'Vence en 7 días',
                'icon' => 'heroicon-o-clock',
                'accent' => SummaryCards::AMBER,
                'amount' => $summary['due_soon_amount'],
                'detail' => SummaryCards::count($summary['due_soon_count'], 'cuota', 'cuotas'),
                'split' => $summary['due_soon_by_type'],
                'url' => self::tableUrl(CollectionReceivableReport::AGING_DUE_SOON),
                'title' => 'Ver en la tabla lo que vence en los próximos 7 días',
            ],
        ];
    }

    /**
     * Plazos de 15, 30, 45 y 60 días como tarjetas, del tono más claro al más intenso.
     *
     * @param  array<int, array{count: int, amount: float, by_type?: array<string, array{affiliations: int, count: int, amount: float}>}>  $dueWindows
     * @return list<array<string, mixed>>
     */
    public static function windows(array $dueWindows): array
    {
        $accents = [15 => '#0ea5e9', 30 => '#3b82f6', 45 => '#6366f1', 60 => '#8b5cf6'];
        $empty = ['affiliations' => 0, 'count' => 0, 'amount' => 0.0];
        $items = [];

        foreach (CollectionReceivableReport::DUE_WINDOWS as $days => $bucket) {
            $window = $dueWindows[$days] ?? ['count' => 0, 'amount' => 0.0];

            $items[] = [
                'key' => 'due-'.$days,
                'label' => 'Vence en '.$days.' días',
                'icon' => 'heroicon-o-calendar-days',
                'accent' => $accents[$days] ?? SummaryCards::BLUE,
                'amount' => (float) $window['amount'],
                'detail' => SummaryCards::count((int) $window['count'], 'cuota', 'cuotas'),
                'split' => [
                    'individual' => $window['by_type']['individual'] ?? $empty,
                    'corporate' => $window['by_type']['corporate'] ?? $empty,
                ],
                'url' => self::tableUrl($bucket),
                'title' => 'Ver en la tabla lo que vence en los próximos '.$days.' días',
            ];
        }

        return $items;
    }

    /**
     * Cuentas por cobrar con el filtro «Vencimiento» aplicado (o sin filtro).
     */
    public static function tableUrl(?string $aging): string
    {
        return $aging === null
            ? AnnualCollectionResource::getUrl('index')
            : AnnualCollectionResource::getUrl('index', ['filters' => ['aging' => ['value' => $aging]]]);
    }
}
