<?php

declare(strict_types=1);

namespace App\Support\Administration\Insights;

use App\Models\Collection;
use App\Models\CollectionDropoutReview;
use App\Support\Administration\Reports\CollectionReportNetwork;
use App\Support\Collections\CollectionAdjuster;
use App\Support\Collections\CollectionReceivableReport;
use App\Support\Collections\DroppedAffiliationCollections;
use App\Support\Filament\SummaryCards;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Arma el Informe administrativo: resumen general, ventas, cobranza y bajas.
 *
 * Cada sección trae cifras clave, gráficos (SVG), tablas con total y % y una lectura
 * que sale de reglas fijas sobre los mismos números, así el texto siempre cuadra con
 * las cifras. Las mismas fuentes que el resto del panel:
 * - Cobrado y ventas: pagos registrados en `sales` (fecha de registro).
 * - Afiliaciones nuevas: fecha de emisión de la afiliación (o su creación si no la tiene).
 * - Cobranza: foto al día de hoy, con la misma regla que Cuentas por cobrar.
 * - Bajas: bitácora de estatus de la afiliación y bandeja de cuotas de bajas.
 *
 * @phpstan-type Kpi array{label: string, value: string, detail?: string|null}
 * @phpstan-type Table array{headings: list<string>, rows: list<list<string>>, total: list<string>|null, numeric: list<int>, html?: list<int>, compact?: bool}
 * @phpstan-type Block array{title: string, chart: string|null, layout: string, table: Table|null, note: string|null}
 * @phpstan-type Section array{key: string, title: string, intro: string, kpis: list<Kpi>, blocks: list<Block>}
 */
final class AdministrativeReportBuilder
{
    public const LAYOUT_WIDE = 'wide';

    public const LAYOUT_SIDE = 'side';

    private const TOP = 10;

    private const DONUT_SLICES = 7;

    /** Más meses que esto se muestran por trimestre. */
    private const MAX_MONTHLY_COLUMNS = 12;

    private const MONTHS = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sept', 'oct', 'nov', 'dic'];

    private ?CollectionReportNetwork $network;

    private CarbonImmutable $today;

    /**
     * Totales de cartera: se usan en el resumen y en la sección de cobranza.
     *
     * @var array<string, mixed>|null
     */
    private ?array $receivableMemo = null;

    public function __construct(private readonly AdministrativeReportFilters $filters, ?CarbonImmutable $today = null)
    {
        $this->network = $filters->network();
        $this->today = $today ?? CarbonImmutable::today();
    }

    public function filters(): AdministrativeReportFilters
    {
        return $this->filters;
    }

    public function network(): ?CollectionReportNetwork
    {
        return $this->network;
    }

    public function today(): CarbonImmutable
    {
        return $this->today;
    }

    /**
     * @return array{title: string, period: string, scope: string, generated_at: string, sections: list<Section>}
     */
    public function build(): array
    {
        return [
            'title' => 'Informe administrativo',
            'period' => $this->filters->periodLabel(),
            'scope' => $this->filters->scopeLabel(),
            'generated_at' => now()->format('d/m/Y h:i A'),
            'sections' => array_values(array_map(
                fn (string $key): array => match ($key) {
                    AdministrativeReportFilters::SECTION_SUMMARY => $this->summarySection(),
                    AdministrativeReportFilters::SECTION_SALES => $this->salesSection(),
                    AdministrativeReportFilters::SECTION_RECEIVABLES => $this->receivablesSection(),
                    AdministrativeReportFilters::SECTION_COMMISSIONS => (new Sections\CommissionsSection($this))->build(),
                    AdministrativeReportFilters::SECTION_RENOVATIONS => (new Sections\RenovationsSection($this))->build(),
                    AdministrativeReportFilters::SECTION_DROPOUTS => $this->dropoutsSection(),
                    AdministrativeReportFilters::SECTION_WHITE_COMPANIES => (new Sections\WhiteCompaniesSection($this))->build(),
                    AdministrativeReportFilters::SECTION_TDEV => (new Sections\TdevSection($this))->build(),
                },
                $this->filters->orderedSections(),
            )),
        ];
    }

    /**
     * @return Section
     */
    private function summarySection(): array
    {
        $months = $this->months();
        $collected = $this->bucketize($this->salesByMonth($this->filters->from, $this->filters->to));
        [$previousFrom, $previousTo] = $this->filters->previousPeriod();
        $previousCollected = array_sum(array_column($this->salesByMonth($previousFrom, $previousTo), 'amount'));
        $affiliations = $this->bucketize($this->newAffiliationsByMonth($this->filters->from, $this->filters->to));
        $previousAffiliations = array_sum(array_map(static fn (array $row): int => $row['individual'] + $row['corporate'], $this->newAffiliationsByMonth($previousFrom, $previousTo)));
        $receivable = $this->receivableTotals();

        $amounts = [];
        $payments = [];
        $individual = [];
        $corporate = [];

        foreach ($months as $key => $label) {
            $amounts[] = (float) ($collected[$key]['amount'] ?? 0);
            $payments[] = (int) ($collected[$key]['count'] ?? 0);
            $individual[] = (int) ($affiliations[$key]['individual'] ?? 0);
            $corporate[] = (int) ($affiliations[$key]['corporate'] ?? 0);
        }

        $totalCollected = array_sum($amounts);
        $totalNew = array_sum($individual) + array_sum($corporate);
        $newPerMonth = array_map(static fn (int $a, int $b): int => $a + $b, $individual, $corporate);
        $labels = array_values($months);

        return [
            'key' => 'summary',
            'title' => 'Resumen general',
            'intro' => 'Lo cobrado y las afiliaciones nuevas del periodo, mes a mes, y la cartera por cobrar al día de hoy.',
            'kpis' => [
                ['label' => 'Cobrado en el periodo', 'value' => self::money($totalCollected), 'detail' => self::count(array_sum($payments), 'pago registrado', 'pagos registrados')],
                ['label' => 'Afiliaciones nuevas', 'value' => self::number($totalNew), 'detail' => self::number(array_sum($individual)).' individuales · '.self::number(array_sum($corporate)).' corporativas'],
                ['label' => 'Por cobrar hoy', 'value' => self::money($receivable['pending_amount']), 'detail' => self::count($receivable['pending_count'], 'cuota', 'cuotas')],
                ['label' => 'Vencido hoy', 'value' => self::money($receivable['overdue_amount']), 'detail' => self::count($receivable['overdue_count'], 'cuota', 'cuotas')],
            ],
            'blocks' => [
                [
                    'title' => 'Cobrado por '.$this->bucketWord(),
                    'chart' => SvgChart::bars($labels, $amounts, static fn (float $value): string => self::compactMoney($value)),
                    'layout' => self::LAYOUT_WIDE,
                    'table' => self::monthTable($labels, [['Monto cobrado', $amounts, true], ['Pagos', $payments, false]]),
                    'note' => self::monthlyNote('cobrado', $labels, $amounts, $totalCollected, $previousCollected, true, $this->bucketWord()).$this->partialComparisonNotice($previousFrom),
                ],
                [
                    'title' => 'Afiliaciones nuevas por '.$this->bucketWord(),
                    'chart' => SvgChart::bars($labels, $newPerMonth),
                    'layout' => self::LAYOUT_WIDE,
                    'table' => self::monthTable($labels, [['Individuales', $individual, false], ['Corporativas', $corporate, false], ['Total', $newPerMonth, false]]),
                    'note' => self::monthlyNote('afiliaciones nuevas', $labels, $newPerMonth, $totalNew, $previousAffiliations, false, $this->bucketWord()),
                ],
            ],
        ];
    }

    /**
     * @return Section
     */
    private function salesSection(): array
    {
        $byType = $this->salesGrouped("COALESCE(NULLIF(TRIM(sales.type), ''), 'SIN TIPO')");
        $byPlan = $this->salesGrouped("COALESCE(NULLIF(TRIM(plans.description), ''), CASE WHEN UPPER(sales.type) LIKE '%CORPORATIVA%' THEN 'CORPORATIVAS (VARIOS PLANES)' ELSE 'SIN PLAN' END)", fn (QueryBuilder $query) => $query->leftJoin('plans', 'plans.id', '=', 'sales.plan_id'));
        $byFrequency = $this->salesGrouped("COALESCE(NULLIF(UPPER(TRIM(sales.payment_frequency)), ''), 'SIN FRECUENCIA')");
        $byAgency = $this->salesGrouped(
            "CONCAT(COALESCE(NULLIF(TRIM(sales.code_agency), ''), 'SIN AGENCIA'), COALESCE(CONCAT(' · ', NULLIF(TRIM(agencies.name_corporative), '')), ''))",
            fn (QueryBuilder $query) => $query->leftJoin('agencies', 'agencies.code', '=', 'sales.code_agency'),
        );
        $byAgent = $this->salesGrouped(
            "COALESCE(NULLIF(TRIM(agents.name), ''), 'VENTA DIRECTA (SIN AGENTE)')",
            fn (QueryBuilder $query) => $query->leftJoin('agents', 'agents.id', '=', 'sales.agent_id'),
        );

        $total = array_sum(array_column($byType, 'amount'));
        $count = array_sum(array_column($byType, 'count'));

        return [
            'key' => 'sales',
            'title' => 'Ventas',
            'intro' => 'Pagos registrados en el periodo, por tipo de afiliación, plan, frecuencia de pago, agencia y agente.',
            'kpis' => [
                ['label' => 'Monto vendido', 'value' => self::money($total), 'detail' => self::count($count, 'pago', 'pagos')],
                ['label' => 'Pago promedio', 'value' => self::money($count > 0 ? $total / $count : 0), 'detail' => null],
                ['label' => 'Agencias con ventas', 'value' => self::number(count(array_filter($byAgency, static fn (array $row): bool => ! str_starts_with($row['label'], 'SIN AGENCIA')))), 'detail' => null],
                ['label' => 'Agentes con ventas', 'value' => self::number(count(array_filter($byAgent, static fn (array $row): bool => ! str_starts_with($row['label'], 'VENTA DIRECTA')))), 'detail' => null],
            ],
            'blocks' => [
                $this->shareBlock('Por tipo de afiliación', $byType, 'Tipo', 'del monto vendido'),
                $this->shareBlock('Por plan', $byPlan, 'Plan', 'del monto vendido'),
                $this->shareBlock('Por frecuencia de pago', $byFrequency, 'Frecuencia', 'del monto vendido'),
                $this->rankingBlock('Las '.self::TOP.' agencias con más ventas', $byAgency, 'Agencia', $total),
                $this->rankingBlock('Los '.self::TOP.' agentes con más ventas', $byAgent, 'Agente', $total),
            ],
        ];
    }

    /**
     * @return Section
     */
    private function receivablesSection(): array
    {
        $totals = $this->receivableTotals();
        $windows = array_keys(CollectionReceivableReport::DUE_WINDOWS);
        $windowLabels = array_map(static fn (int $days): string => 'Próximos '.$days.' días', $windows);
        $windowAmounts = array_map(fn (int $days): float => $totals['windows'][$days]['amount'], $windows);
        $windowCounts = array_map(fn (int $days): int => $totals['windows'][$days]['count'], $windows);
        $agingLabels = ['1 a 30 días', '31 a 60 días', '61 a 90 días', 'Más de 90 días'];
        $agingAmounts = array_column($totals['aging'], 'amount');
        $agingCounts = array_column($totals['aging'], 'count');
        $byAgency = $this->receivableByAgency();
        $overdueShare = $totals['pending_amount'] > 0 ? $totals['overdue_amount'] * 100 / $totals['pending_amount'] : 0;

        $agingNote = null;

        if ($totals['overdue_amount'] > 0) {
            $largest = array_keys($agingAmounts, max($agingAmounts))[0];
            $agingNote = 'El '.self::percent($overdueShare).' de lo pendiente ya está vencido ('.self::money($totals['overdue_amount']).'). '
                .'La mayor parte de lo vencido tiene '.Str::lower($agingLabels[$largest]).' de atraso ('.self::money($agingAmounts[$largest]).').';
        }

        return [
            'key' => 'receivables',
            'title' => 'Cobranza',
            'intro' => 'Foto de la cartera al día de hoy ('.$this->today->format('d/m/Y').'), con la misma regla que Cuentas por cobrar: sin afiliaciones excluidas, inactivas ni anuladas.',
            'kpis' => [
                ['label' => 'Por cobrar', 'value' => self::money($totals['pending_amount']), 'detail' => self::count($totals['pending_count'], 'cuota', 'cuotas').' · '.self::count($totals['affiliations'], 'afiliación', 'afiliaciones')],
                ['label' => 'Vencido', 'value' => self::money($totals['overdue_amount']), 'detail' => self::count($totals['overdue_count'], 'cuota', 'cuotas').' · '.self::percent($overdueShare).' de lo pendiente'],
                ['label' => 'Vence en 7 días', 'value' => self::money($totals['due_soon_amount']), 'detail' => self::count($totals['due_soon_count'], 'cuota', 'cuotas')],
                ['label' => 'Por vencer', 'value' => self::money(max($totals['pending_amount'] - $totals['overdue_amount'], 0)), 'detail' => null],
            ],
            'blocks' => [
                [
                    'title' => 'Lo que vence en los próximos días',
                    'chart' => SvgChart::bars(array_map(static fn (int $days): string => $days.' días', $windows), $windowAmounts, static fn (float $value): string => self::compactMoney($value)),
                    'layout' => self::LAYOUT_WIDE,
                    'table' => [
                        'headings' => ['Plazo', 'Monto', 'Cuotas'],
                        'rows' => array_map(static fn (string $label, float $amount, int $count): array => [$label, self::money($amount), self::number($count)], $windowLabels, $windowAmounts, $windowCounts),
                        'total' => null,
                        'numeric' => [1, 2],
                    ],
                    'note' => 'Los plazos se acumulan: «Próximos 30 días» incluye lo de los primeros 15. '
                        .($windowAmounts !== [] && end($windowAmounts) > 0 ? 'En los próximos '.end($windows).' días vencen '.self::money((float) end($windowAmounts)).'.' : ''),
                ],
                [
                    'title' => 'Antigüedad de lo vencido',
                    'chart' => SvgChart::bars($agingLabels, $agingAmounts, static fn (float $value): string => self::compactMoney($value)),
                    'layout' => self::LAYOUT_WIDE,
                    'table' => [
                        'headings' => ['Atraso', 'Monto', 'Cuotas', '%'],
                        'rows' => array_map(fn (string $label, float $amount, int $count): array => [$label, self::money($amount), self::number($count), self::percent($totals['overdue_amount'] > 0 ? $amount * 100 / $totals['overdue_amount'] : 0)], $agingLabels, $agingAmounts, $agingCounts),
                        'total' => ['Total vencido', self::money($totals['overdue_amount']), self::number($totals['overdue_count']), '100 %'],
                        'numeric' => [1, 2, 3],
                    ],
                    'note' => $agingNote ?? 'No hay cuotas vencidas en este alcance.',
                ],
                $this->rankingBlock('Las '.self::TOP.' agencias con más por cobrar', $byAgency, 'Agencia', $totals['pending_amount'], 'del total por cobrar', 'Cuotas'),
            ],
        ];
    }

    /**
     * @return Section
     */
    private function dropoutsSection(): array
    {
        $months = $this->months();
        $byMonth = $this->bucketize($this->dropoutsByMonth());
        $byStatus = $this->dropoutsByCurrentStatus();
        $values = array_map(static fn (string $key): int => (int) ($byMonth[$key] ?? 0), array_keys($months));
        // Cada afiliación una vez, aunque tenga varias bajas en el periodo.
        $totalDropped = array_sum(array_column($byStatus, 'count'));
        $inbox = $this->droppedCollectionsInbox();
        $inboxLabels = array_column($inbox, 'label');
        $inboxAmounts = array_column($inbox, 'amount');
        $inboxTotal = array_sum($inboxAmounts);

        $statusNote = $totalDropped === 0
            ? 'No hubo bajas registradas en el periodo.'
            : 'Se registraron '.self::count($totalDropped, 'baja', 'bajas').' en el periodo.'
                .($byStatus !== [] ? ' Hoy '.self::describeShares($byStatus, 'afiliación', 'afiliaciones').'.' : '');

        return [
            'key' => 'dropouts',
            'title' => 'Bajas',
            'intro' => 'Afiliaciones excluidas, inactivas o anuladas en el periodo (según su bitácora) y las cuotas pendientes que dejaron.',
            'kpis' => [
                ['label' => 'Bajas en el periodo', 'value' => self::number($totalDropped), 'detail' => 'afiliaciones'],
                ['label' => 'Cuotas por decidir', 'value' => self::money($inbox[0]['amount']), 'detail' => self::count($inbox[0]['count'], 'cuota', 'cuotas')],
                ['label' => 'Anuladas por baja', 'value' => self::money($inbox[2]['amount']), 'detail' => self::count($inbox[2]['count'], 'cuota', 'cuotas')],
                ['label' => 'Mantenidas como deuda', 'value' => self::money($inbox[1]['amount']), 'detail' => self::count($inbox[1]['count'], 'cuota', 'cuotas')],
            ],
            'blocks' => [
                [
                    'title' => 'Bajas por '.$this->bucketWord(),
                    'chart' => SvgChart::bars(array_values($months), $values),
                    'layout' => self::LAYOUT_WIDE,
                    'table' => [
                        'headings' => ['Estatus actual', 'Afiliaciones', '%'],
                        'rows' => array_map(static fn (array $row): array => [$row['label'], self::number($row['count']), self::percent($row['share'])], $byStatus),
                        'total' => $byStatus === [] ? null : ['Total', self::number(array_sum(array_column($byStatus, 'count'))), '100 %'],
                        'numeric' => [1, 2],
                    ],
                    'note' => $statusNote,
                ],
                [
                    'title' => 'Cuotas de afiliaciones dadas de baja',
                    'chart' => SvgChart::donut($inboxAmounts, $inboxLabels),
                    'layout' => self::LAYOUT_SIDE,
                    'table' => [
                        'headings' => ['Revisión', 'Cuotas', 'Monto', '%'],
                        'rows' => array_map(static fn (array $row, int $index): array => ['<span style="color:'.SvgChart::color($index).';">■</span> '.e($row['label']), self::number($row['count']), self::money($row['amount']), self::percent($inboxTotal > 0 ? $row['amount'] * 100 / $inboxTotal : 0)], $inbox, array_keys($inbox)),
                        'total' => ['Total', self::number(array_sum(array_column($inbox, 'count'))), self::money($inboxTotal), '100 %'],
                        'numeric' => [1, 2, 3],
                        'html' => [0],
                    ],
                    'note' => $inbox[0]['count'] > 0
                        ? 'Quedan '.self::count($inbox[0]['count'], 'cuota', 'cuotas').' por '.self::money($inbox[0]['amount']).' para que Administración decida si se anulan o se mantienen como deuda.'
                        : 'No hay cuotas de bajas pendientes de decisión.',
                ],
            ],
        ];
    }

    /**
     * Los pagos se registran en el sistema desde cierta fecha: si el periodo anterior empieza
     * antes, la comparación de lo cobrado es parcial y hay que decirlo.
     */
    private function partialComparisonNotice(CarbonImmutable $previousFrom): string
    {
        $first = DB::table('sales')->min('created_at');

        if ($first === null) {
            return '';
        }

        $first = CarbonImmutable::parse((string) $first);

        return $first->greaterThan($previousFrom)
            ? ' Ojo: los pagos se registran en el sistema desde el '.$first->format('d/m/Y').', así que la comparación con el periodo anterior es parcial.'
            : '';
    }

    /**
     * Columnas de los gráficos y tablas en el tiempo: meses o, si el periodo pasa de 12
     * meses, trimestres (36 barras no se leen). Clave => rótulo («ene», «T1 26»).
     *
     * @return array<string, string>
     */
    public function months(): array
    {
        $buckets = [];
        $cursor = $this->filters->from->startOfMonth();
        $sameYear = $this->filters->from->year === $this->filters->to->year;

        while ($cursor->lessThanOrEqualTo($this->filters->to)) {
            $buckets[$this->bucketOf($cursor->format('Y-m'))] = $this->isQuarterly()
                ? 'T'.$cursor->quarter.' '.$cursor->format('y')
                : self::MONTHS[$cursor->month - 1].($sameYear ? '' : ' '.$cursor->format('y'));
            $cursor = $cursor->addMonthNoOverflow();
        }

        return $buckets;
    }

    private function isQuarterly(): bool
    {
        return $this->filters->from->startOfMonth()->diffInMonths($this->filters->to->startOfMonth()) + 1 > self::MAX_MONTHLY_COLUMNS;
    }

    private function bucketOf(string $month): string
    {
        if (! $this->isQuarterly()) {
            return $month;
        }

        [$year, $number] = array_map(intval(...), explode('-', $month));

        return $year.'-T'.intdiv($number - 1, 3) + 1;
    }

    public function bucketWord(): string
    {
        return $this->isQuarterly() ? 'trimestre' : 'mes';
    }

    /**
     * Suma por columna (mes o trimestre) lo que las consultas traen por mes.
     *
     * @param  array<string, array<string, int|float>|int|float>  $byMonth
     * @return array<string, array<string, int|float>|int|float>
     */
    public function bucketize(array $byMonth): array
    {
        $buckets = [];

        foreach ($byMonth as $month => $value) {
            $key = $this->bucketOf((string) $month);

            if (is_array($value)) {
                foreach ($value as $field => $number) {
                    $buckets[$key][$field] = ($buckets[$key][$field] ?? 0) + $number;
                }
            } else {
                $buckets[$key] = ($buckets[$key] ?? 0) + $value;
            }
        }

        return $buckets;
    }

    /**
     * @return array<string, array{amount: float, count: int}>
     */
    private function salesByMonth(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $query = DB::table('sales')->whereBetween('sales.created_at', [$from, $to]);
        AdministrativeReportFilters::applyNetwork($query, 'sales', $this->network);

        return $query
            ->selectRaw("DATE_FORMAT(sales.created_at, '%Y-%m') as month, COUNT(*) as total, COALESCE(SUM(sales.total_amount), 0) as amount")
            ->groupBy('month')
            ->get()
            ->mapWithKeys(static fn (object $row): array => [(string) $row->month => ['amount' => round((float) $row->amount, 2), 'count' => (int) $row->total]])
            ->all();
    }

    /**
     * @return array<string, array{individual: int, corporate: int}>
     */
    private function newAffiliationsByMonth(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $result = [];

        foreach (['affiliations' => 'individual', 'affiliation_corporates' => 'corporate'] as $table => $kind) {
            $emission = "COALESCE(STR_TO_DATE(NULLIF(TRIM({$table}.activated_at), ''), '%d/%m/%Y'), DATE({$table}.created_at))";
            $query = DB::table($table)->whereRaw("{$emission} BETWEEN ? AND ?", [$from->toDateString(), $to->toDateString()]);
            AdministrativeReportFilters::applyNetwork($query, $table, $this->network);

            foreach ($query->selectRaw("DATE_FORMAT({$emission}, '%Y-%m') as month, COUNT(*) as total")->groupBy('month')->get() as $row) {
                $result[(string) $row->month] ??= ['individual' => 0, 'corporate' => 0];
                $result[(string) $row->month][$kind] = (int) $row->total;
            }
        }

        return $result;
    }

    /**
     * @param  (callable(QueryBuilder): mixed)|null  $join
     * @return list<array{label: string, amount: float, count: int}>
     */
    private function salesGrouped(string $labelSql, ?callable $join = null): array
    {
        $query = DB::table('sales')->whereBetween('sales.created_at', [$this->filters->from, $this->filters->to]);

        if ($join !== null) {
            $join($query);
        }

        AdministrativeReportFilters::applyNetwork($query, 'sales', $this->network);

        return $query
            ->selectRaw("{$labelSql} as label, COUNT(*) as total, COALESCE(SUM(sales.total_amount), 0) as amount")
            ->groupBy('label')
            ->orderByDesc('amount')
            ->get()
            ->map(static fn (object $row): array => ['label' => Str::upper(trim((string) $row->label)), 'amount' => round((float) $row->amount, 2), 'count' => (int) $row->total])
            ->all();
    }

    /**
     * Cuotas por pagar cobrables del alcance, en una sola consulta.
     *
     * @return array{pending_count: int, pending_amount: float, overdue_count: int, overdue_amount: float, due_soon_count: int, due_soon_amount: float, affiliations: int, windows: array<int, array{count: int, amount: float}>, aging: list<array{count: int, amount: float}>}
     */
    private function receivableTotals(): array
    {
        if ($this->receivableMemo !== null) {
            return $this->receivableMemo;
        }

        $today = $this->today->toDateString();
        $query = $this->pendingCollectableQuery()
            ->selectRaw('COUNT(*) as pending_count, COALESCE(SUM(collections.total_amount), 0) as pending_amount, COUNT(DISTINCT collections.affiliation_code) as affiliations')
            ->selectRaw('SUM(CASE WHEN collections.filter_next_payment_date < ? THEN 1 ELSE 0 END) as overdue_count', [$today])
            ->selectRaw('COALESCE(SUM(CASE WHEN collections.filter_next_payment_date < ? THEN collections.total_amount ELSE 0 END), 0) as overdue_amount', [$today])
            ->selectRaw('SUM(CASE WHEN collections.filter_next_payment_date BETWEEN ? AND ? THEN 1 ELSE 0 END) as due_soon_count', [$today, $this->today->addDays(7)->toDateString()])
            ->selectRaw('COALESCE(SUM(CASE WHEN collections.filter_next_payment_date BETWEEN ? AND ? THEN collections.total_amount ELSE 0 END), 0) as due_soon_amount', [$today, $this->today->addDays(7)->toDateString()]);

        foreach (array_keys(CollectionReceivableReport::DUE_WINDOWS) as $days) {
            $until = $this->today->addDays($days)->toDateString();
            $query->selectRaw("SUM(CASE WHEN collections.filter_next_payment_date BETWEEN ? AND ? THEN 1 ELSE 0 END) as window_{$days}_count", [$today, $until])
                ->selectRaw("COALESCE(SUM(CASE WHEN collections.filter_next_payment_date BETWEEN ? AND ? THEN collections.total_amount ELSE 0 END), 0) as window_{$days}_amount", [$today, $until]);
        }

        $aging = [[1, 30], [31, 60], [61, 90], [91, null]];

        foreach ($aging as $index => [$minDays, $maxDays]) {
            $newest = $this->today->subDays($minDays)->toDateString();
            $condition = $maxDays === null
                ? 'collections.filter_next_payment_date <= ?'
                : 'collections.filter_next_payment_date BETWEEN ? AND ?';
            $bindings = $maxDays === null ? [$newest] : [$this->today->subDays($maxDays)->toDateString(), $newest];
            $query->selectRaw("SUM(CASE WHEN {$condition} THEN 1 ELSE 0 END) as aging_{$index}_count", $bindings)
                ->selectRaw("COALESCE(SUM(CASE WHEN {$condition} THEN collections.total_amount ELSE 0 END), 0) as aging_{$index}_amount", $bindings);
        }

        $row = $query->toBase()->first();
        $windows = [];

        foreach (array_keys(CollectionReceivableReport::DUE_WINDOWS) as $days) {
            $windows[$days] = ['count' => (int) ($row->{"window_{$days}_count"} ?? 0), 'amount' => round((float) ($row->{"window_{$days}_amount"} ?? 0), 2)];
        }

        return $this->receivableMemo = [
            'pending_count' => (int) ($row->pending_count ?? 0),
            'pending_amount' => round((float) ($row->pending_amount ?? 0), 2),
            'overdue_count' => (int) ($row->overdue_count ?? 0),
            'overdue_amount' => round((float) ($row->overdue_amount ?? 0), 2),
            'due_soon_count' => (int) ($row->due_soon_count ?? 0),
            'due_soon_amount' => round((float) ($row->due_soon_amount ?? 0), 2),
            'affiliations' => (int) ($row->affiliations ?? 0),
            'windows' => $windows,
            'aging' => array_map(static fn (int $index): array => [
                'count' => (int) ($row->{"aging_{$index}_count"} ?? 0),
                'amount' => round((float) ($row->{"aging_{$index}_amount"} ?? 0), 2),
            ], array_keys($aging)),
        ];
    }

    /**
     * @return list<array{label: string, amount: float, count: int}>
     */
    private function receivableByAgency(): array
    {
        return $this->pendingCollectableQuery()
            ->leftJoin('agencies', 'agencies.code', '=', 'collections.code_agency')
            ->selectRaw("CONCAT(COALESCE(NULLIF(TRIM(collections.code_agency), ''), 'SIN AGENCIA'), COALESCE(CONCAT(' · ', NULLIF(TRIM(agencies.name_corporative), '')), '')) as label")
            ->selectRaw('COUNT(*) as total, COALESCE(SUM(collections.total_amount), 0) as amount')
            ->groupBy('label')
            ->orderByDesc('amount')
            ->toBase()
            ->get()
            ->map(static fn (object $row): array => ['label' => Str::upper(trim((string) $row->label)), 'amount' => round((float) $row->amount, 2), 'count' => (int) $row->total])
            ->all();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Collection>
     */
    public function pendingCollectableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = CollectionReceivableReport::excludeNonCollectableAffiliations(
            Collection::query()->where('collections.status', CollectionAdjuster::STATUS_PENDING),
        );
        AdministrativeReportFilters::applyNetwork($query, 'collections', $this->network);

        return $query;
    }

    /**
     * Afiliaciones (individuales y corporativas) dadas de baja en el periodo, por mes.
     *
     * @return array<string, int>
     */
    private function dropoutsByMonth(): array
    {
        $result = [];

        foreach ($this->dropoutLogQueries() as $query) {
            // Cada afiliación cuenta una vez, en el mes de su última baja del periodo: así las barras suman el total.
            $latest = (clone $query)->selectRaw('log.affiliation_ref, MAX(log.created_at) as dropped_at')->groupBy('log.affiliation_ref');

            foreach (DB::query()->fromSub($latest, 'latest')->selectRaw("DATE_FORMAT(latest.dropped_at, '%Y-%m') as month, COUNT(*) as total")->groupBy('month')->get() as $row) {
                $result[(string) $row->month] = ($result[(string) $row->month] ?? 0) + (int) $row->total;
            }
        }

        return $result;
    }

    /**
     * Estatus actual de las afiliaciones dadas de baja en el periodo (pueden haberse reactivado).
     *
     * @return list<array{label: string, count: int, share: float}>
     */
    private function dropoutsByCurrentStatus(): array
    {
        $counts = [];

        foreach ($this->dropoutLogQueries() as $query) {
            foreach ($query->selectRaw('UPPER(TRIM(log.status)) as status, COUNT(DISTINCT log.affiliation_ref) as total')->groupBy('status')->get() as $row) {
                $label = DroppedAffiliationCollections::isDroppedStatus($row->status) ? (string) $row->status : 'REACTIVADA ('.$row->status.')';
                $counts[$label] = ($counts[$label] ?? 0) + (int) $row->total;
            }
        }

        arsort($counts);
        $total = array_sum($counts);

        return array_map(
            static fn (string $label, int $count): array => ['label' => $label, 'count' => $count, 'share' => $total > 0 ? $count * 100 / $total : 0],
            array_keys($counts),
            array_values($counts),
        );
    }

    /**
     * Entradas de baja en la bitácora de estatus, una consulta por tipo de afiliación.
     *
     * @return list<QueryBuilder>
     */
    private function dropoutLogQueries(): array
    {
        $queries = [];

        foreach ([['status_log_affiliations', 'affiliation_id', 'affiliations'], ['status_log_affiliation_corporates', 'affiliation_corporate_id', 'affiliation_corporates']] as [$logTable, $foreignKey, $affiliationTable]) {
            $entries = DB::table("{$logTable} as entry")
                ->join("{$affiliationTable} as affiliation", 'affiliation.id', '=', "entry.{$foreignKey}")
                ->whereBetween('entry.created_at', [$this->filters->from, $this->filters->to])
                ->where(function (QueryBuilder $query): void {
                    $query->where('entry.action', 'like', '%EXCLU%')
                        ->orWhere('entry.action', 'like', '%INACTIV%')
                        ->orWhere('entry.action', 'like', '%ANULAD%');
                })
                ->select(['entry.created_at', "entry.{$foreignKey} as affiliation_ref", 'affiliation.status']);

            AdministrativeReportFilters::applyNetwork($entries, 'affiliation', $this->network);
            $queries[] = DB::query()->fromSub($entries, 'log');
        }

        return $queries;
    }

    /**
     * Bandeja de cuotas de afiliaciones dadas de baja: por decidir, mantenidas y anuladas.
     *
     * @return list<array{label: string, count: int, amount: float}>
     */
    private function droppedCollectionsInbox(): array
    {
        $rows = [];

        foreach ([
            DroppedAffiliationCollections::REVIEW_UNDECIDED => 'Por decidir',
            CollectionDropoutReview::DECISION_KEPT => 'Mantenidas como deuda',
            CollectionDropoutReview::DECISION_CANCELLED => 'Anuladas por la baja',
        ] as $review => $label) {
            $query = DroppedAffiliationCollections::applyReviewFilter(DroppedAffiliationCollections::reviewQuery(), $review);
            AdministrativeReportFilters::applyNetwork($query, 'collections', $this->network);
            $row = $query->toBase()->selectRaw('COUNT(*) as total, COALESCE(SUM(collections.total_amount), 0) as amount')->first();
            $rows[] = ['label' => $label, 'count' => (int) ($row->total ?? 0), 'amount' => round((float) ($row->amount ?? 0), 2)];
        }

        return $rows;
    }

    /**
     * Dona con las porciones principales y «Otros», y su tabla con total y %.
     *
     * @param  list<array{label: string, amount: float, count: int}>  $rows
     * @return Block
     */
    public function shareBlock(string $title, array $rows, string $heading, string $shareOf, string $countHeading = 'Pagos'): array
    {
        $total = array_sum(array_column($rows, 'amount'));
        $slices = array_slice($rows, 0, self::DONUT_SLICES);
        $rest = array_slice($rows, self::DONUT_SLICES);

        if ($rest !== []) {
            $slices[] = ['label' => 'OTROS ('.count($rest).')', 'amount' => array_sum(array_column($rest, 'amount')), 'count' => array_sum(array_column($rest, 'count'))];
        }

        return [
            'title' => $title,
            'chart' => SvgChart::donut(array_column($slices, 'amount'), array_column($slices, 'label')),
            'layout' => self::LAYOUT_SIDE,
            'table' => [
                'headings' => [$heading, $countHeading, 'Monto', '%'],
                'rows' => array_map(static fn (array $row, int $index): array => [
                    '<span style="color:'.SvgChart::color($index).';">■</span> '.e($row['label']),
                    self::number($row['count']),
                    self::money($row['amount']),
                    self::percent($total > 0 ? $row['amount'] * 100 / $total : 0),
                ], $slices, array_keys($slices)),
                'total' => ['Total', self::number(array_sum(array_column($rows, 'count'))), self::money($total), '100 %'],
                'numeric' => [1, 2, 3],
                'html' => [0],
            ],
            'note' => $rows === [] || $total <= 0
                ? 'Sin datos en el periodo.'
                : $rows[0]['label'].' concentra el '.self::percent($rows[0]['amount'] * 100 / $total).' '.$shareOf
                    .(isset($rows[1]) ? ', seguido de '.$rows[1]['label'].' con el '.self::percent($rows[1]['amount'] * 100 / $total) : '').'.',
        ];
    }

    /**
     * Barras horizontales del top y su tabla.
     *
     * @param  list<array{label: string, amount: float, count: int}>  $rows
     * @return Block
     */
    public function rankingBlock(string $title, array $rows, string $heading, float $total, string $shareOf = 'del monto vendido', string $countHeading = 'Pagos'): array
    {
        $top = array_slice($rows, 0, self::TOP);
        $topTotal = array_sum(array_column($top, 'amount'));
        $topThree = array_sum(array_column(array_slice($rows, 0, 3), 'amount'));

        return [
            'title' => $title,
            'chart' => $top === [] ? null : SvgChart::horizontalBars(array_column($top, 'label'), array_column($top, 'amount'), static fn (float $value): string => self::money($value)),
            'layout' => self::LAYOUT_WIDE,
            // Mucho texto chico: no se achica para acomodar la página (dejaría de leerse).
            'shrinkable' => false,
            'table' => [
                'headings' => ['#', $heading, $countHeading, 'Monto', '%'],
                'rows' => array_map(static fn (array $row, int $index): array => [
                    (string) ($index + 1),
                    $row['label'],
                    self::number($row['count']),
                    self::money($row['amount']),
                    self::percent($total > 0 ? $row['amount'] * 100 / $total : 0),
                ], $top, array_keys($top)),
                'total' => $top === [] ? null : ['', 'Total de los '.count($top), self::number(array_sum(array_column($top, 'count'))), self::money($topTotal), self::percent($total > 0 ? $topTotal * 100 / $total : 0)],
                'numeric' => [2, 3, 4],
            ],
            'note' => $top === [] || $total <= 0
                ? 'Sin datos en este alcance.'
                : match (count($rows)) {
                    1 => $top[0]['label'].' concentra el '.self::percent($top[0]['amount'] * 100 / $total).' '.$shareOf.' ('.self::money($top[0]['amount']).').',
                    2 => 'Los dos concentran el '.self::percent($topThree * 100 / $total).' '.$shareOf.'; '.$top[0]['label'].' encabeza con '.self::money($top[0]['amount']).'.',
                    default => 'Los tres primeros concentran el '.self::percent($topThree * 100 / $total).' '.$shareOf.'; '.$top[0]['label'].' encabeza con '.self::money($top[0]['amount']).'.',
                },
        ];
    }

    /**
     * Tabla por mes con una o varias series y su total.
     *
     * @param  list<string>  $labels
     * @param  list<array{0: string, 1: list<float|int>, 2: bool}>  $series  [encabezado, valores, es monto]
     * @return Table
     */
    public static function monthTable(array $labels, array $series): array
    {
        $rows = [];

        foreach ($series as [$heading, $values, $isMoney]) {
            $format = static fn (float|int $value): string => $isMoney ? number_format((float) $value, 0, ',', '.') : self::number($value);
            $rows[] = [
                $heading.($isMoney ? ' (US$)' : ''),
                ...array_map($format, $values),
                $format(array_sum($values)),
            ];
        }

        return [
            'headings' => ['', ...array_map(static fn (string $label): string => Str::ucfirst($label), $labels), 'Total'],
            'rows' => $rows,
            'total' => null,
            'numeric' => range(1, count($labels) + 1),
            'compact' => true,
        ];
    }

    /**
     * Mes más alto, su peso y la comparación con el periodo anterior.
     *
     * @param  list<string>  $labels
     * @param  list<float|int>  $values
     */
    public static function monthlyNote(string $what, array $labels, array $values, float $total, float $previous, bool $isMoney, string $bucket = 'mes'): string
    {
        if ($total <= 0) {
            return 'Sin '.$what.' en el periodo.';
        }

        $format = static fn (float $value): string => $isMoney ? self::money($value) : self::number((int) $value);
        $note = '';

        if (count($values) > 1) {
            $best = array_keys($values, max($values))[0];
            $note = 'El '.$bucket.' con más '.$what.' fue '.$labels[$best].', con '.$format((float) $values[$best]).' ('.self::percent($values[$best] * 100 / $total).' del periodo). ';
        }

        if ($previous > 0) {
            $change = ($total - $previous) * 100 / $previous;
            $note .= 'Frente al periodo anterior ('.$format($previous).') '.($change >= 0 ? 'sube' : 'baja').' un '.self::percent(abs($change)).'.';
        } else {
            $note .= 'En el periodo anterior no hubo '.$what.' para comparar.';
        }

        return trim($note);
    }

    /**
     * «3 siguen EXCLUIDO (60 %), 2 ...».
     *
     * @param  list<array{label: string, count: int, share: float}>  $rows
     */
    private static function describeShares(array $rows, string $singular, string $plural): string
    {
        return implode(', ', array_map(
            static fn (array $row): string => self::count($row['count'], $singular, $plural).' en '.$row['label'].' ('.self::percent($row['share']).')',
            array_slice($rows, 0, 3),
        ));
    }

    public static function money(float $amount): string
    {
        return SummaryCards::money($amount);
    }

    public static function compactMoney(float $amount): string
    {
        return $amount >= 1000 ? number_format($amount / 1000, 1, ',', '.').'k' : number_format($amount, 0, ',', '.');
    }

    public static function number(int|float $value): string
    {
        return number_format((float) $value, 0, ',', '.');
    }

    public static function percent(float $value): string
    {
        return number_format($value, 1, ',', '.')."\u{00A0}%";
    }

    public static function count(int $count, string $singular, string $plural): string
    {
        return self::number($count).' '.($count === 1 ? $singular : $plural);
    }
}
