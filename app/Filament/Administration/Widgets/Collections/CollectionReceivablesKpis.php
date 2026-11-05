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
 * KPIs de cuentas por cobrar, a hoy: todo lo vencido y lo que vence en 7 días, cada uno separado en individuales y corporativas; los recibos (avisos
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

        $windows = self::windows($summary['due_windows']);

        /** Primera fila: vencido, 7 días y 15 días; segunda fila: 30, 45 y 60 días. */
        return [
            'tiles' => [...self::tiles($summary), ...array_slice($windows, 0, 1)],
            'noticeTiles' => self::noticeTiles(CollectionReceivableReport::issuedNotices()),
            'windows' => array_slice($windows, 1),
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
        return [
            [
                'key' => 'overdue',
                'label' => 'Vencido',
                'icon' => 'heroicon-o-exclamation-triangle',
                'accent' => SummaryCards::RED,
                'amount' => $summary['overdue_amount'],
                'detail' => SummaryCards::count($summary['overdue_count'], 'cuota', 'cuotas'),
                'split' => null,
                'buckets' => [
                    ['label' => '1 a 15 días', ...$summary['overdue_aging']['1_15']],
                    ['label' => '16 a 30 días', ...$summary['overdue_aging']['16_30']],
                    ['label' => 'Más de 30 días', ...$summary['overdue_aging']['31_plus']],
                ],
                'url' => self::tableUrl(null, CollectionReceivableReport::STATUS_OVERDUE),
                'title' => 'Ver en la tabla todo lo vencido',
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
    public static function tableUrl(?string $aging, ?string $collectionStatus = null): string
    {
        $filters = array_filter([
            'aging' => $aging !== null ? ['value' => $aging] : null,
            'collection_status' => $collectionStatus !== null ? ['value' => $collectionStatus] : null,
        ]);

        return $filters === []
            ? AnnualCollectionResource::getUrl('index')
            : AnnualCollectionResource::getUrl('index', ['filters' => $filters]);
    }
}
