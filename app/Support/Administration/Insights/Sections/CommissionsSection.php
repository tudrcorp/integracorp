<?php

declare(strict_types=1);

namespace App\Support\Administration\Insights\Sections;

use App\Support\Administration\Insights\AdministrativeReportBuilder as Report;
use App\Support\Administration\Insights\AdministrativeReportFilters;
use App\Support\Administration\Insights\SvgChart;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Comisiones generadas en el periodo (tabla `commissions`, fecha de registro), por nivel de
 * la red, mes, agencia y agente. No se separa pagado de pendiente: el sistema aún no
 * registra el pago de comisiones.
 */
final class CommissionsSection
{
    /**
     * Columnas en USD de cada nivel que cobra comisión.
     *
     * @var array<string, string>
     */
    private const LEVELS = [
        'commission_agency_master_usd' => 'Agencia master',
        'commission_agency_general_usd' => 'Agencia general',
        'commission_agent_usd' => 'Agente',
        'commission_sub_agent_usd' => 'Subagente',
        'commission_referidor_usd' => 'Referidor',
    ];

    public function __construct(private readonly Report $report) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $total = self::totalSql('commissions');
        $months = $this->report->months();
        $byMonth = $this->report->bucketize(
            $this->query()
                ->selectRaw("DATE_FORMAT(commissions.created_at, '%Y-%m') as month, COUNT(*) as total, COALESCE(SUM({$total}), 0) as amount")
                ->groupBy('month')
                ->get()
                ->mapWithKeys(static fn (object $row): array => [(string) $row->month => ['amount' => round((float) $row->amount, 2), 'count' => (int) $row->total]])
                ->all(),
        );

        $amounts = array_map(static fn (string $key): float => (float) ($byMonth[$key]['amount'] ?? 0), array_keys($months));
        $counts = array_map(static fn (string $key): int => (int) ($byMonth[$key]['count'] ?? 0), array_keys($months));
        $labels = array_values($months);
        $grandTotal = array_sum($amounts);

        $levels = $this->levels();
        $levelRows = array_values(array_filter($levels, static fn (array $row): bool => $row['amount'] > 0));
        $byAgency = $this->grouped(
            "CONCAT(COALESCE(NULLIF(TRIM(commissions.code_agency), ''), 'SIN AGENCIA'), COALESCE(CONCAT(' · ', NULLIF(TRIM(agencies.name_corporative), '')), ''))",
            fn (QueryBuilder $query) => $query->leftJoin('agencies', 'agencies.code', '=', 'commissions.code_agency'),
        );
        $byAgent = $this->grouped(
            "COALESCE(NULLIF(TRIM(agents.name), ''), 'SIN AGENTE')",
            fn (QueryBuilder $query) => $query->leftJoin('agents', 'agents.id', '=', 'commissions.agent_id'),
        );
        $count = array_sum($counts);
        $topLevel = $levelRows === [] ? null : collect($levelRows)->sortByDesc('amount')->first();

        return [
            'key' => 'commissions',
            'title' => 'Comisiones',
            'intro' => 'Comisiones generadas en el periodo por las ventas registradas, por nivel de la red, '.$this->report->bucketWord().', agencia y agente.',
            'kpis' => [
                ['label' => 'Comisión generada', 'value' => Report::money($grandTotal), 'detail' => Report::count($count, 'registro', 'registros')],
                ['label' => 'Comisión promedio', 'value' => Report::money($count > 0 ? $grandTotal / $count : 0), 'detail' => 'por registro'],
                ['label' => 'Mayor nivel', 'value' => $topLevel['label'] ?? '—', 'detail' => $topLevel === null ? null : Report::money($topLevel['amount'])],
                ['label' => 'Agentes con comisión', 'value' => Report::number(count(array_filter($byAgent, static fn (array $row): bool => $row['label'] !== 'SIN AGENTE'))), 'detail' => null],
            ],
            'blocks' => [
                [
                    'title' => 'Comisión generada por '.$this->report->bucketWord(),
                    'chart' => SvgChart::bars($labels, $amounts, static fn (float $value): string => Report::compactMoney($value)),
                    'layout' => Report::LAYOUT_WIDE,
                    'table' => Report::monthTable($labels, [['Comisión', $amounts, true], ['Registros', $counts, false]]),
                    'note' => Report::monthlyNote('comisión generada', $labels, $amounts, $grandTotal, $this->previousTotal(), true, $this->report->bucketWord()),
                ],
                $this->report->shareBlock('Por nivel de la red', $levelRows, 'Nivel', 'de la comisión', 'Registros'),
                $this->report->rankingBlock('Las 10 agencias con más comisión', $byAgency, 'Agencia', $grandTotal, 'de la comisión', 'Registros'),
                $this->report->rankingBlock('Los 10 agentes con más comisión', $byAgent, 'Agente', $grandTotal, 'de la comisión', 'Registros'),
            ],
        ];
    }

    /**
     * Suma de los niveles de una fila, en USD.
     */
    public static function totalSql(string $table): string
    {
        return implode(' + ', array_map(static fn (string $column): string => "COALESCE({$table}.{$column}, 0)", array_keys(self::LEVELS)));
    }

    private function previousTotal(): float
    {
        [$from, $to] = $this->report->filters()->previousPeriod();

        return round((float) $this->query($from, $to)->selectRaw('COALESCE(SUM('.self::totalSql('commissions').'), 0) as amount')->value('amount'), 2);
    }

    private function query(?\Carbon\CarbonImmutable $from = null, ?\Carbon\CarbonImmutable $to = null): QueryBuilder
    {
        $query = DB::table('commissions')->whereBetween('commissions.created_at', [$from ?? $this->report->filters()->from, $to ?? $this->report->filters()->to]);
        AdministrativeReportFilters::applyNetwork($query, 'commissions', $this->report->network(), hasOwnerCode: false);

        return $query;
    }

    /**
     * @return list<array{label: string, amount: float, count: int}>
     */
    private function levels(): array
    {
        $query = $this->query();

        foreach (array_keys(self::LEVELS) as $column) {
            $query->selectRaw("COALESCE(SUM(commissions.{$column}), 0) as {$column}")
                ->selectRaw("SUM(CASE WHEN COALESCE(commissions.{$column}, 0) > 0 THEN 1 ELSE 0 END) as {$column}_count");
        }

        $row = $query->first();
        $levels = [];

        foreach (self::LEVELS as $column => $label) {
            $levels[] = ['label' => Str::upper($label), 'amount' => round((float) ($row->{$column} ?? 0), 2), 'count' => (int) ($row->{$column.'_count'} ?? 0)];
        }

        usort($levels, static fn (array $a, array $b): int => $b['amount'] <=> $a['amount']);

        return $levels;
    }

    /**
     * @param  callable(QueryBuilder): mixed  $join
     * @return list<array{label: string, amount: float, count: int}>
     */
    private function grouped(string $labelSql, callable $join): array
    {
        $query = $this->query();
        $join($query);
        $total = self::totalSql('commissions');

        return $query
            ->selectRaw("{$labelSql} as label, COUNT(*) as total, COALESCE(SUM({$total}), 0) as amount")
            ->groupBy('label')
            ->orderByDesc('amount')
            ->get()
            ->map(static fn (object $row): array => ['label' => Str::upper(trim((string) $row->label)), 'amount' => round((float) $row->amount, 2), 'count' => (int) $row->total])
            ->all();
    }
}
