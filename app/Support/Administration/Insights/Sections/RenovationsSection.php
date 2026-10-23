<?php

declare(strict_types=1);

namespace App\Support\Administration\Insights\Sections;

use App\Support\Administration\Insights\AdministrativeReportBuilder as Report;
use App\Support\Administration\Insights\AdministrativeReportFilters;
use App\Support\Administration\Insights\SvgChart;
use App\Support\Collections\CollectionReceivableReport;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Renovaciones individuales y corporativas.
 *
 * Cada afiliación tiene su registro de renovación (`renovations` / `renovation_corporates`,
 * una fila por afiliado): «PERIODO DE RENOVACION» a 30 días o menos de la fecha de
 * renovación y «VIGENTE» antes. Al aceptarse pasa al historial con su fecha de aceptación.
 * - Renovadas: aceptaciones del historial en el periodo.
 * - Vencidas sin renovar: registros cuya fecha de renovación ya pasó sin aceptarse.
 * - Tasa: renovadas / (renovadas + vencidas sin renovar con fecha en el periodo).
 */
final class RenovationsSection
{
    /**
     * [tabla de renovaciones, tabla de historial, tipo].
     *
     * @var list<array{0: string, 1: string, 2: string}>
     */
    private const SOURCES = [
        ['renovations', 'affiliation_renovation_histories', 'individual'],
        ['renovation_corporates', 'affiliation_corporate_renovation_histories', 'corporate'],
    ];

    public function __construct(private readonly Report $report) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $today = $this->report->today()->toDateString();
        $filters = $this->report->filters();
        $months = $this->report->months();
        $renewedByMonth = $this->report->bucketize($this->renewedByMonth());
        $individual = array_map(static fn (string $key): int => (int) ($renewedByMonth[$key]['individual'] ?? 0), array_keys($months));
        $corporate = array_map(static fn (string $key): int => (int) ($renewedByMonth[$key]['corporate'] ?? 0), array_keys($months));
        $perMonth = array_map(static fn (int $a, int $b): int => $a + $b, $individual, $corporate);
        $renewed = array_sum($perMonth);

        $inPeriod = $this->pendingTotals(fn (QueryBuilder $query, string $table) => $query->where("{$table}.status", 'PERIODO DE RENOVACION')->whereDate("{$table}.date_renewal", '>=', $today));
        $overdue = $this->pendingTotals(fn (QueryBuilder $query, string $table) => $query->whereDate("{$table}.date_renewal", '<', $today));
        $expiredInPeriod = $this->pendingTotals(fn (QueryBuilder $query, string $table) => $query
            ->whereDate("{$table}.date_renewal", '<', $today)
            ->whereBetween("{$table}.date_renewal", [$filters->from->toDateString(), $filters->to->toDateString()]));
        $rate = $renewed + $expiredInPeriod['affiliations'] > 0 ? $renewed * 100 / ($renewed + $expiredInPeriod['affiliations']) : null;

        $windows = array_keys(CollectionReceivableReport::DUE_WINDOWS);
        $upcoming = array_map(fn (int $days): array => $this->pendingTotals(fn (QueryBuilder $query, string $table) => $query
            ->whereBetween("{$table}.date_renewal", [$today, $this->report->today()->addDays($days)->toDateString()])), $windows);
        $byAgency = $this->pendingByAgency();
        $firstRecord = $this->firstRecordDate();

        $rateNote = $rate === null
            ? 'Sin renovaciones vencidas ni aceptadas en el periodo para calcular la tasa.'
            : 'Tasa de renovación del periodo: '.Report::percent($rate).' ('.Report::count($renewed, 'renovada', 'renovadas').' de '
                .Report::number($renewed + $expiredInPeriod['affiliations']).' que vencían). ';
        $dataNote = $firstRecord !== null && $firstRecord->greaterThan($filters->from)
            ? 'Los registros de renovación existen desde el '.$firstRecord->format('d/m/Y').': antes de esa fecha la sección no tiene datos.'
            : '';

        return [
            'key' => 'renovations',
            'title' => 'Renovaciones',
            'intro' => 'Renovaciones aceptadas en el periodo y la cartera por renovar al día de hoy ('.$this->report->today()->format('d/m/Y').'), individuales y corporativas.',
            'kpis' => [
                ['label' => 'Renovadas en el periodo', 'value' => Report::number($renewed), 'detail' => Report::number(array_sum($individual)).' individuales · '.Report::number(array_sum($corporate)).' corporativas'],
                ['label' => 'Tasa de renovación', 'value' => $rate === null ? '—' : Report::percent($rate), 'detail' => 'renovadas / vencían'],
                ['label' => 'En periodo de renovación', 'value' => Report::number($inPeriod['affiliations']), 'detail' => Report::money($inPeriod['amount']).' anual'],
                ['label' => 'Vencidas sin renovar', 'value' => Report::number($overdue['affiliations']), 'detail' => Report::money($overdue['amount']).' anual'],
            ],
            'blocks' => [
                [
                    'title' => 'Renovadas por '.$this->report->bucketWord(),
                    'chart' => SvgChart::bars(array_values($months), $perMonth),
                    'layout' => Report::LAYOUT_WIDE,
                    'table' => Report::monthTable(array_values($months), [['Individuales', $individual, false], ['Corporativas', $corporate, false], ['Total', $perMonth, false]]),
                    'note' => trim($rateNote.$dataNote),
                ],
                [
                    'title' => 'Lo que se renueva en los próximos días',
                    'chart' => SvgChart::bars(array_map(static fn (int $days): string => $days.' días', $windows), array_column($upcoming, 'amount'), static fn (float $value): string => Report::compactMoney($value)),
                    'layout' => Report::LAYOUT_WIDE,
                    'table' => [
                        'headings' => ['Plazo', 'Afiliaciones', 'Afiliados', 'Monto anual'],
                        'rows' => array_map(static fn (int $days, array $row): array => ['Próximos '.$days.' días', Report::number($row['affiliations']), Report::number($row['affiliates']), Report::money($row['amount'])], $windows, $upcoming),
                        'total' => null,
                        'numeric' => [1, 2, 3],
                    ],
                    'note' => 'Los plazos se acumulan. El monto anual es la tarifa de renovación calculada para cada afiliado.',
                ],
                $this->report->rankingBlock('Las 10 agencias con más renovaciones pendientes', $byAgency, 'Agencia', array_sum(array_column($byAgency, 'amount')), 'del monto pendiente de renovar', 'Afiliaciones'),
            ],
        ];
    }

    /**
     * Afiliaciones renovadas por mes (su última aceptación del periodo), separadas por tipo.
     *
     * @return array<string, array{individual: int, corporate: int}>
     */
    private function renewedByMonth(): array
    {
        $result = [];

        foreach (self::SOURCES as [, $history, $kind]) {
            $query = DB::table($history)->whereBetween("{$history}.accepted_at", [$this->report->filters()->from, $this->report->filters()->to]);
            AdministrativeReportFilters::applyNetwork($query, $history, $this->report->network());

            // Cada afiliación una vez, en el mes de su última aceptación: así las barras suman el total.
            $latest = $query->selectRaw("{$history}.code_affiliation, MAX({$history}.accepted_at) as accepted_at")->groupBy("{$history}.code_affiliation");

            foreach (DB::query()->fromSub($latest, 'latest')->selectRaw("DATE_FORMAT(latest.accepted_at, '%Y-%m') as month, COUNT(*) as total")->groupBy('month')->get() as $row) {
                $result[(string) $row->month] ??= ['individual' => 0, 'corporate' => 0];
                $result[(string) $row->month][$kind] += (int) $row->total;
            }
        }

        return $result;
    }

    /**
     * Totales de la cartera por renovar que cumple la condición: afiliaciones, afiliados y monto anual.
     *
     * @param  callable(QueryBuilder, string): mixed  $condition
     * @return array{affiliations: int, affiliates: int, amount: float}
     */
    private function pendingTotals(callable $condition): array
    {
        $totals = ['affiliations' => 0, 'affiliates' => 0, 'amount' => 0.0];

        foreach (self::SOURCES as [$table]) {
            $query = DB::table($table);
            $condition($query, $table);
            AdministrativeReportFilters::applyNetwork($query, $table, $this->report->network());
            $row = $query->selectRaw("COUNT(DISTINCT {$table}.code_affiliation) as affiliations, COUNT(*) as affiliates, COALESCE(SUM({$table}.subtotal_anual), 0) as amount")->first();

            $totals['affiliations'] += (int) ($row->affiliations ?? 0);
            $totals['affiliates'] += (int) ($row->affiliates ?? 0);
            $totals['amount'] += (float) ($row->amount ?? 0);
        }

        $totals['amount'] = round($totals['amount'], 2);

        return $totals;
    }

    /**
     * Pendientes de renovar (en periodo o vencidas) por agencia.
     *
     * @return list<array{label: string, amount: float, count: int}>
     */
    private function pendingByAgency(): array
    {
        $rows = [];

        foreach (self::SOURCES as [$table]) {
            $query = DB::table($table)
                ->leftJoin('agencies', 'agencies.code', '=', "{$table}.code_agency")
                ->where(fn (QueryBuilder $query) => $query
                    ->where("{$table}.status", 'PERIODO DE RENOVACION')
                    ->orWhereDate("{$table}.date_renewal", '<', $this->report->today()->toDateString()));
            AdministrativeReportFilters::applyNetwork($query, $table, $this->report->network());

            $grouped = $query
                ->selectRaw("CONCAT(COALESCE(NULLIF(TRIM({$table}.code_agency), ''), 'SIN AGENCIA'), COALESCE(CONCAT(' · ', NULLIF(TRIM(agencies.name_corporative), '')), '')) as label")
                ->selectRaw("COUNT(DISTINCT {$table}.code_affiliation) as total, COALESCE(SUM({$table}.subtotal_anual), 0) as amount")
                ->groupBy('label')
                ->get();

            foreach ($grouped as $row) {
                $label = Str::upper(trim((string) $row->label));
                $rows[$label] ??= ['label' => $label, 'amount' => 0.0, 'count' => 0];
                $rows[$label]['amount'] += (float) $row->amount;
                $rows[$label]['count'] += (int) $row->total;
            }
        }

        $rows = array_values(array_map(static fn (array $row): array => [...$row, 'amount' => round($row['amount'], 2)], $rows));
        usort($rows, static fn (array $a, array $b): int => $b['amount'] <=> $a['amount']);

        return $rows;
    }

    private function firstRecordDate(): ?\Carbon\CarbonImmutable
    {
        $dates = array_filter(array_map(
            static fn (array $source): mixed => DB::table($source[0])->min('created_at'),
            self::SOURCES,
        ));

        return $dates === [] ? null : \Carbon\CarbonImmutable::parse((string) min($dates));
    }
}
