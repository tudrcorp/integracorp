<?php

declare(strict_types=1);

namespace App\Filament\Administration\Widgets\Collections;

use App\Filament\Administration\Resources\Collections\CollectionResource;
use App\Filament\Administration\Resources\Collections\Tables\CollectionsTable;
use App\Support\Collections\CollectionManagementMetrics;
use App\Support\Collections\CollectionReceivableReport;
use App\Support\Filament\SummaryCards;
use Carbon\CarbonImmutable;
use Filament\Widgets\Widget;

/**
 * KPIs de Gestión de Cobranza, a hoy:
 *  1. lo cobrado en el mes, el resumen de lo cobrado en el año (mes a mes) y la morosidad;
 *  2. todo lo vencido por antigüedad, lo que vence en 7 días y en 15 días;
 *  3. lo que vence en 30, 45 y 60 días.
 *
 * Cobrado sale de los pagos registrados; la cartera, de las cuotas por pagar de Gestión de
 * Cobranza ({@see CollectionManagementMetrics}). Las tarjetas de cartera abren la tabla con su
 * filtro de estado o de próximo pago.
 */
class CollectionManagementKpis extends Widget
{
    protected static bool $isDiscovered = false;

    protected string $view = 'filament.administration.collections.management-kpis';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $today = CarbonImmutable::today();
        $portfolio = CollectionManagementMetrics::portfolio($today);
        $collected = CollectionManagementMetrics::collected($today);
        $receivables = self::withTableLinks([
            ...CollectionReceivablesKpis::tiles($portfolio),
            ...CollectionReceivablesKpis::windows($portfolio['due_windows']),
        ], $today);

        return [
            'collectedTile' => self::collectedTile($collected, $today),
            'yearTile' => self::yearTile($collected, $today),
            'rateTile' => self::rateTile($portfolio),
            'tiles' => array_slice($receivables, 0, 3),
            'windows' => array_slice($receivables, 3),
        ];
    }

    /**
     * @param  array<string, mixed>  $collected
     * @return array<string, mixed>
     */
    public static function collectedTile(array $collected, CarbonImmutable $today): array
    {
        $month = $collected['month'];
        $count = $month['individual']['count'] + $month['corporate']['count'];

        return [
            'key' => 'collected-month',
            'label' => 'Cobrado en el mes',
            'icon' => 'heroicon-o-banknotes',
            'accent' => '#16a34a',
            'amount' => round($month['individual']['amount'] + $month['corporate']['amount'], 2),
            'detail' => ucfirst($today->locale('es')->translatedFormat('F Y')).' · '.SummaryCards::count($count, 'pago', 'pagos'),
            'split' => $month,
            'count_noun' => ['pago', 'pagos'],
            'url' => null,
            'title' => 'Pagos registrados en el mes',
        ];
    }

    /**
     * @param  array<string, mixed>  $collected
     * @return array<string, mixed>
     */
    public static function yearTile(array $collected, CarbonImmutable $today): array
    {
        $year = $collected['year'];

        return [
            'label' => 'Resumen de cobrado '.$today->year,
            'amount' => round($year['individual']['amount'] + $year['corporate']['amount'], 2),
            'count' => $year['individual']['count'] + $year['corporate']['count'],
            'split' => $year,
            'months' => $collected['months'],
            'current_month' => $today->month,
        ];
    }

    /**
     * @param  array<string, mixed>  $portfolio
     * @return array<string, mixed>
     */
    public static function rateTile(array $portfolio): array
    {
        $byType = [];

        foreach (['individual' => 'Individuales', 'corporate' => 'Corporativas'] as $type => $label) {
            $overdue = (float) $portfolio['overdue_by_type'][$type]['amount'];
            $pending = (float) $portfolio['pending_by_type'][$type]['amount'];

            $byType[] = [
                'label' => $label,
                'rate' => CollectionManagementMetrics::delinquencyRate($overdue, $pending),
                'overdue' => $overdue,
                'pending' => $pending,
            ];
        }

        return [
            'rate' => CollectionManagementMetrics::delinquencyRate((float) $portfolio['overdue_amount'], (float) $portfolio['pending_amount']),
            'overdue' => (float) $portfolio['overdue_amount'],
            'pending' => (float) $portfolio['pending_amount'],
            'by_type' => $byType,
        ];
    }

    /**
     * Las tarjetas de cartera abren Gestión de Cobranza con su filtro.
     *
     * @param  list<array<string, mixed>>  $tiles
     * @return list<array<string, mixed>>
     */
    public static function withTableLinks(array $tiles, CarbonImmutable $today): array
    {
        $days = ['due-soon' => 7];

        foreach (array_keys(CollectionReceivableReport::DUE_WINDOWS) as $window) {
            $days['due-'.$window] = $window;
        }

        return array_map(function (array $tile) use ($days, $today): array {
            $key = (string) $tile['key'];

            if ($key === 'overdue') {
                $tile['url'] = CollectionResource::getUrl('index', ['filters' => ['status' => ['value' => CollectionsTable::STATUS_OVERDUE]]]);
                $tile['title'] = 'Ver en Gestión de Cobranza todas las cuotas vencidas';

                return $tile;
            }

            if (isset($days[$key])) {
                $tile['url'] = CollectionResource::getUrl('index', ['filters' => [
                    'status' => ['value' => CollectionReceivableReport::PENDING_STATUS],
                    'filter_next_payment_date' => ['desde' => $today->toDateString(), 'hasta' => $today->addDays($days[$key])->toDateString()],
                ]]);
                $tile['title'] = 'Ver en Gestión de Cobranza las cuotas que vencen en los próximos '.$days[$key].' días';
            }

            return $tile;
        }, $tiles);
    }
}
