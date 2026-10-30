<?php

declare(strict_types=1);

namespace App\Support\Business\Dashboard;

use App\Support\Metrics\Corporate\CorporateFinanceMetrics;
use App\Support\Metrics\Corporate\CorporateMetrics;
use App\Support\Metrics\Corporate\CorporatePortfolioMetrics;
use App\Support\Operations\Dashboard\DashboardGrouping;
use App\Support\Operations\Dashboard\OperationsDashboardPeriod as Period;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Venta nueva frente a cartera que se renueva: lo que mide al equipo de Negocios.
 *
 * - Dinero nuevo: pagos hechos dentro del primer año desde la emisión de la
 *   afiliación, incluidas las cuotas de ese primer contrato.
 * - Dinero de cartera: pagos de afiliaciones que ya cumplieron un año.
 * - Sin clasificar: pagos sin afiliación asociada (por ejemplo, Nuevos Negocios).
 *   Se muestran aparte para que las tres partes sumen los ingresos del período.
 * - Renovaciones (conteo): aceptaciones del historial, una por afiliación. Ese
 *   historial existe desde junio de 2026; lo anterior no quedó registrado. El
 *   dinero no depende de él: sale de la fecha de emisión, que renovar no cambia.
 *
 * Una afiliación corporativa guarda una fila de historial por afiliado, por eso
 * siempre se cuenta por código.
 */
final class BusinessNewVsRenewalMetrics
{
    public const NEW = 'new';

    public const RENEWAL = 'renewal';

    public const UNCLASSIFIED = 'unclassified';

    private const HISTORIES = ['affiliation_renovation_histories', 'affiliation_corporate_renovation_histories'];

    /**
     * @return array{current: array<string, int|float|null>, previous: array<string, int|float|null>, sparklines: array<string, list<float>>}
     */
    public static function kpis(Period $period): array
    {
        return CorporateMetrics::remember('business_new_vs_renewal', $period, static function () use ($period): array {
            return [
                'current' => self::summary($period->from, $period->to),
                'previous' => self::summary($period->previousFrom, $period->previousTo),
                'sparklines' => [
                    'new' => self::incomeDaily(self::NEW, $period->from, $period->to),
                    'renewal' => self::incomeDaily(self::RENEWAL, $period->from, $period->to),
                ],
            ];
        });
    }

    /**
     * @return array{new_income: float, renewal_income: float, unclassified_income: float, new_share: float|null, new_affiliations: int, renewed: int}
     */
    public static function summary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = DashboardGrouping::by(self::classifiedSales($from, $to), ['grp_kind' => self::kindSql()], ['amount' => 'sales.total_amount'])
            ->selectRaw('COALESCE(SUM(g.amount), 0) as amount')
            ->pluck('amount', 'grp_kind');

        $new = round((float) ($rows[self::NEW] ?? 0), 2);
        $renewal = round((float) ($rows[self::RENEWAL] ?? 0), 2);
        $classified = $new + $renewal;

        return [
            'new_income' => $new,
            'renewal_income' => $renewal,
            'unclassified_income' => round((float) ($rows[self::UNCLASSIFIED] ?? 0), 2),
            'new_share' => $classified > 0 ? round($new * 100 / $classified, 1) : null,
            'new_affiliations' => (int) CorporatePortfolioMetrics::movement($from, $to)['new'],
            'renewed' => self::renewedCount($from, $to),
        ];
    }

    /**
     * @return array{labels: list<string>, first: list<float>, second: list<float>}
     */
    public static function incomeSeries(Period $period): array
    {
        return CorporateMetrics::remember('business_new_vs_renewal_income', $period, static function () use ($period): array {
            $granularity = $period->granularity();
            $new = CorporateMetrics::bucket(self::incomeDaily(self::NEW, $period->from, $period->to), $period->from, $granularity);
            $renewal = CorporateMetrics::bucket(self::incomeDaily(self::RENEWAL, $period->from, $period->to), $period->from, $granularity);

            return ['labels' => $new['labels'], 'first' => $new['values'], 'second' => $renewal['values']];
        });
    }

    /**
     * @return array{labels: list<string>, first: list<float>, second: list<float>}
     */
    public static function countSeries(Period $period): array
    {
        return CorporateMetrics::remember('business_new_vs_renewal_count', $period, static function () use ($period): array {
            $granularity = $period->granularity();
            $new = CorporateMetrics::bucket(CorporatePortfolioMetrics::newDaily($period->from, $period->to), $period->from, $granularity);
            $renewed = array_fill(0, (int) $period->from->diffInDays($period->to) + 1, 0.0);

            foreach (self::HISTORIES as $table) {
                $daily = CorporateMetrics::daily(
                    DB::query()->fromSub(self::renewedCodes($table, $period->from, $period->to), 'r'),
                    'r.accepted_at',
                    $period->from,
                    $period->to,
                );

                foreach ($daily as $index => $value) {
                    $renewed[$index] += $value;
                }
            }

            return ['labels' => $new['labels'], 'first' => $new['values'], 'second' => CorporateMetrics::bucket($renewed, $period->from, $granularity)['values']];
        });
    }

    /**
     * Dinero nuevo del período por autor de la afiliación.
     *
     * @return array<string, float> Nombre tal como se guardó => monto.
     */
    public static function newIncomeByCreator(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return DashboardGrouping::by(
            self::classifiedSales($from, $to)->whereRaw(self::kindSql().' = ?', [self::NEW]),
            ['grp_creator' => "COALESCE(affiliations.created_by, affiliation_corporates.created_by, '')"],
            ['amount' => 'sales.total_amount'],
        )
            ->selectRaw('COALESCE(SUM(g.amount), 0) as amount')
            ->pluck('amount', 'grp_creator')
            ->map(static fn (mixed $amount): float => round((float) $amount, 2))
            ->all();
    }

    private static function renewedCount(CarbonImmutable $from, CarbonImmutable $to): int
    {
        $total = 0;

        foreach (self::HISTORIES as $table) {
            $total += DB::query()->fromSub(self::renewedCodes($table, $from, $to), 'r')->count();
        }

        return $total;
    }

    private static function renewedCodes(string $table, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return DB::table($table)
            ->whereBetween('accepted_at', CorporateMetrics::range($from, $to))
            ->selectRaw('code_affiliation, MIN(accepted_at) as accepted_at')
            ->groupBy('code_affiliation');
    }

    /**
     * @return list<float>
     */
    private static function incomeDaily(string $kind, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return CorporateMetrics::daily(
            self::classifiedSales($from, $to)->whereRaw(self::kindSql().' = ?', [$kind]),
            'sales.created_at',
            $from,
            $to,
            'sales.total_amount',
        );
    }

    private static function classifiedSales(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return CorporateFinanceMetrics::salesBetween($from, $to)
            ->leftJoin('affiliations', 'affiliations.code', '=', 'sales.affiliation_code')
            ->leftJoin('affiliation_corporates', 'affiliation_corporates.code', '=', 'sales.affiliation_code');
    }

    private static function kindSql(): string
    {
        $emission = 'COALESCE('.CorporatePortfolioMetrics::emissionSql('affiliations').', '.CorporatePortfolioMetrics::emissionSql('affiliation_corporates').')';

        return "CASE WHEN {$emission} IS NULL THEN '".self::UNCLASSIFIED."'"
            ." WHEN sales.created_at < DATE_ADD({$emission}, INTERVAL 1 YEAR) THEN '".self::NEW."'"
            ." ELSE '".self::RENEWAL."' END";
    }
}
