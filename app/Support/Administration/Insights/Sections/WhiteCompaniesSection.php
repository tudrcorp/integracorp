<?php

declare(strict_types=1);

namespace App\Support\Administration\Insights\Sections;

use App\Models\WhiteCompany;
use App\Support\Administration\Insights\AdministrativeReportBuilder as Report;
use App\Support\Administration\Insights\AdministrativeReportFilters;
use App\Support\WhiteCompanies\WhiteCompanyOwnership;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Empresas aliadas: lo vendido por su red, sus afiliaciones nuevas, lo que tienen por
 * cobrar, el margen (precio de venta − neta congelada en cada afiliación) y la
 * conciliación de crédito.
 *
 * La pertenencia sale de {@see WhiteCompanyOwnership}: la agencia raíz de la empresa y toda
 * su descendencia, nunca el código de agencia plano. Las consultas agrupan por agencia y
 * aquí se suman por empresa.
 */
final class WhiteCompaniesSection
{
    public function __construct(private readonly Report $report) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $filters = $this->report->filters();
        $sales = $this->byCompany($this->periodQuery('sales', 'created_at')->selectRaw('sales.code_agency as code, COUNT(*) as total, COALESCE(SUM(sales.total_amount), 0) as amount')->groupBy('sales.code_agency')->get());
        $newAffiliations = $this->newAffiliations();
        $margin = $this->margin();
        $receivable = $this->byCompany(
            $this->report->pendingCollectableQuery()->toBase()
                ->selectRaw('collections.code_agency as code, COUNT(*) as total, COALESCE(SUM(collections.total_amount), 0) as amount')
                ->groupBy('collections.code_agency')
                ->get(),
        );
        $credits = $this->credits(array_unique([...array_keys($sales), ...array_keys($newAffiliations), ...array_keys($receivable)]));

        $companyIds = array_unique([...array_keys($sales), ...array_keys($newAffiliations), ...array_keys($receivable), ...array_keys($credits)]);
        $names = WhiteCompany::query()->whereKey($companyIds)->pluck('name', 'id')->all();
        $label = static fn (int $id): string => Str::upper(trim((string) ($names[$id] ?? 'EMPRESA #'.$id)));

        $ranking = array_map(static fn (int $id, array $row): array => ['label' => $label($id), 'amount' => $row['amount'], 'count' => $row['count']], array_keys($sales), $sales);
        usort($ranking, static fn (array $a, array $b): int => $b['amount'] <=> $a['amount']);
        $totalSales = array_sum(array_column($ranking, 'amount'));

        $detailIds = $companyIds;
        usort($detailIds, static fn (int $a, int $b): int => ($sales[$b]['amount'] ?? 0) <=> ($sales[$a]['amount'] ?? 0));
        $detailRows = array_map(static fn (int $id): array => [
            $label($id),
            Report::number($newAffiliations[$id]['count'] ?? 0),
            Report::money($sales[$id]['amount'] ?? 0),
            Report::money($receivable[$id]['amount'] ?? 0),
            Report::money($margin[$id]['sale'] ?? 0),
            Report::money($margin[$id]['neta'] ?? 0),
            Report::money(($margin[$id]['sale'] ?? 0) - ($margin[$id]['neta'] ?? 0)),
            Report::number($credits[$id]['count'] ?? 0),
        ], array_slice($detailIds, 0, 15));

        $totalMargin = array_sum(array_map(static fn (array $row): float => $row['sale'] - $row['neta'], $margin));
        $totalCredits = array_sum(array_column($credits, 'amount'));

        return [
            'key' => 'white_companies',
            'title' => 'Empresas aliadas',
            'intro' => 'Lo vendido por la red de cada empresa aliada en el periodo, sus afiliaciones nuevas, lo que tiene por cobrar hoy, el margen sobre la neta negociada y la conciliación de crédito.',
            'kpis' => [
                ['label' => 'Empresas con actividad', 'value' => Report::number(count($companyIds)), 'detail' => null],
                ['label' => 'Vendido por aliadas', 'value' => Report::money($totalSales), 'detail' => Report::count(array_sum(array_column($ranking, 'count')), 'pago', 'pagos')],
                ['label' => 'Margen sobre la neta', 'value' => Report::money($totalMargin), 'detail' => 'afiliaciones nuevas del periodo'],
                ['label' => 'Conciliación de crédito', 'value' => Report::money($totalCredits), 'detail' => Report::count(array_sum(array_column($credits, 'count')), 'movimiento', 'movimientos')],
            ],
            'blocks' => [
                $this->report->rankingBlock('Empresas aliadas con más ventas', $ranking, 'Empresa aliada', $totalSales, 'de lo vendido por aliadas'),
                [
                    'title' => 'Detalle por empresa aliada',
                    'chart' => null,
                    'layout' => Report::LAYOUT_WIDE,
                    'table' => $detailRows === [] ? null : [
                        'headings' => ['Empresa aliada', 'Afil. nuevas', 'Vendido', 'Por cobrar', 'Precio venta', 'Neta', 'Margen', 'Créditos'],
                        'rows' => $detailRows,
                        'total' => null,
                        'numeric' => [1, 2, 3, 4, 5, 6, 7],
                    ],
                    'note' => $companyIds === []
                        ? 'Sin actividad de empresas aliadas en este periodo y alcance.'
                        : 'Precio de venta y neta son los anuales congelados en las afiliaciones individuales nuevas del periodo (las corporativas no los registran). Por cobrar es la foto de hoy.',
                ],
            ],
        ];
    }

    private function periodQuery(string $table, string $dateColumn): QueryBuilder
    {
        $query = DB::table($table)->whereBetween("{$table}.{$dateColumn}", [$this->report->filters()->from, $this->report->filters()->to]);
        AdministrativeReportFilters::applyNetwork($query, $table, $this->report->network());

        return $query;
    }

    /**
     * @return array<int, array{amount: float, count: int}>
     */
    private function newAffiliations(): array
    {
        $rows = collect();
        $from = $this->report->filters()->from->toDateString();
        $to = $this->report->filters()->to->toDateString();

        foreach (['affiliations', 'affiliation_corporates'] as $table) {
            $emission = "COALESCE(STR_TO_DATE(NULLIF(TRIM({$table}.activated_at), ''), '%d/%m/%Y'), DATE({$table}.created_at))";
            $query = DB::table($table)->whereRaw("{$emission} BETWEEN ? AND ?", [$from, $to]);
            AdministrativeReportFilters::applyNetwork($query, $table, $this->report->network());
            $rows = $rows->merge($query->selectRaw("{$table}.code_agency as code, COUNT(*) as total, 0 as amount")->groupBy("{$table}.code_agency")->get());
        }

        return $this->byCompany($rows);
    }

    /**
     * Precio de venta y neta anuales de las afiliaciones individuales nuevas del periodo.
     *
     * @return array<int, array{sale: float, neta: float}>
     */
    private function margin(): array
    {
        $table = 'affiliations';
        $emission = "COALESCE(STR_TO_DATE(NULLIF(TRIM({$table}.activated_at), ''), '%d/%m/%Y'), DATE({$table}.created_at))";
        $query = DB::table($table)
            ->whereRaw("{$emission} BETWEEN ? AND ?", [$this->report->filters()->from->toDateString(), $this->report->filters()->to->toDateString()])
            ->whereNotNull("{$table}.white_company_neta");
        AdministrativeReportFilters::applyNetwork($query, $table, $this->report->network());

        $result = [];

        foreach ($query->selectRaw("{$table}.code_agency as code, COALESCE(SUM({$table}.white_company_sale_price), 0) as sale, COALESCE(SUM({$table}.white_company_neta), 0) as neta")->groupBy("{$table}.code_agency")->get() as $row) {
            $companyId = WhiteCompanyOwnership::companyIdForAgencyCode((string) $row->code);

            if ($companyId === null) {
                continue;
            }

            $result[$companyId] ??= ['sale' => 0.0, 'neta' => 0.0];
            $result[$companyId]['sale'] += (float) $row->sale;
            $result[$companyId]['neta'] += (float) $row->neta;
        }

        return $result;
    }

    /**
     * Conciliación de crédito del periodo. Con alcance comercial, solo de las empresas que
     * aparecen en ese alcance.
     *
     * @param  list<int>  $companyIds
     * @return array<int, array{amount: float, count: int}>
     */
    private function credits(array $companyIds): array
    {
        $filters = $this->report->filters();
        $query = DB::table('credit_reconciliations')
            ->whereNotNull('white_company_id')
            ->whereBetween('created_at', [$filters->from, $filters->to]);

        if ($filters->scope === AdministrativeReportFilters::SCOPE_WHITE_COMPANY) {
            $query->where('white_company_id', $filters->whiteCompanyId);
        } elseif ($this->report->network() !== null) {
            $query->whereIn('white_company_id', $companyIds !== [] ? $companyIds : [0]);
        }

        return $query
            ->selectRaw('white_company_id, COUNT(*) as total, COALESCE(SUM(total_to_pay), 0) as amount')
            ->groupBy('white_company_id')
            ->get()
            ->mapWithKeys(static fn (object $row): array => [(int) $row->white_company_id => ['amount' => round((float) $row->amount, 2), 'count' => (int) $row->total]])
            ->all();
    }

    /**
     * Suma por empresa aliada filas agrupadas por código de agencia.
     *
     * @param  iterable<object{code: mixed, total: mixed, amount: mixed}>  $rows
     * @return array<int, array{amount: float, count: int}>
     */
    private function byCompany(iterable $rows): array
    {
        $result = [];

        foreach ($rows as $row) {
            $companyId = WhiteCompanyOwnership::companyIdForAgencyCode(is_string($row->code) ? $row->code : null);

            if ($companyId === null) {
                continue;
            }

            $result[$companyId] ??= ['amount' => 0.0, 'count' => 0];
            $result[$companyId]['amount'] = round($result[$companyId]['amount'] + (float) $row->amount, 2);
            $result[$companyId]['count'] += (int) $row->total;
        }

        return $result;
    }
}
