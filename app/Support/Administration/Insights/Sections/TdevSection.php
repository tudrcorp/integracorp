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
 * TDEV (viajes): vouchers emitidos en el periodo, venta (PVP) y comisión.
 *
 * `tdev_reports` guarda la agencia como texto y la fecha como texto dd/mm/aaaa, así que
 * esta sección no se acota por agencia, agente ni empresa aliada: siempre es el total de
 * TDEV, y el informe lo dice. Un voucher cuya comisión supera su propio precio de venta es
 * un dato dañado y se deja fuera de las sumas (también se dice).
 */
final class TdevSection
{
    private const DATE_SQL = "STR_TO_DATE(tdev_reports.fecha, '%d/%m/%Y')";

    /** La comisión de un voucher sano nunca supera su precio de venta. */
    private const SANE_SQL = 'COALESCE(tdev_reports.monto_comision, 0) <= COALESCE(tdev_reports.monto_pvp_precio_de_venta, 0)';

    public function __construct(private readonly Report $report) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $months = $this->report->months();
        $byMonth = $this->report->bucketize(
            $this->query()
                ->selectRaw('DATE_FORMAT('.self::DATE_SQL.", '%Y-%m') as month, COUNT(*) as total, COALESCE(SUM(tdev_reports.monto_pvp_precio_de_venta), 0) as amount")
                ->groupBy('month')
                ->get()
                ->mapWithKeys(static fn (object $row): array => [(string) $row->month => ['amount' => round((float) $row->amount, 2), 'count' => (int) $row->total]])
                ->all(),
        );
        $labels = array_values($months);
        $amounts = array_map(static fn (string $key): float => (float) ($byMonth[$key]['amount'] ?? 0), array_keys($months));
        $counts = array_map(static fn (string $key): int => (int) ($byMonth[$key]['count'] ?? 0), array_keys($months));
        $totals = $this->query()
            ->selectRaw('COUNT(*) as total, COALESCE(SUM(tdev_reports.monto_pvp_precio_de_venta), 0) as sales, COALESCE(SUM(tdev_reports.monto_comision), 0) as commission')
            ->first();
        $excluded = (int) $this->query(sane: false)->whereRaw('NOT ('.self::SANE_SQL.')')->count();

        $sales = round((float) ($totals->sales ?? 0), 2);
        $commission = round((float) ($totals->commission ?? 0), 2);
        $vouchers = (int) ($totals->total ?? 0);
        [$previousFrom, $previousTo] = $this->report->filters()->previousPeriod();
        $previous = round((float) $this->query($previousFrom, $previousTo)->sum('tdev_reports.monto_pvp_precio_de_venta'), 2);

        $notFiltered = $this->report->filters()->scope !== AdministrativeReportFilters::SCOPE_ALL
            ? ' Esta sección no se acota por agencia, agente ni empresa aliada: muestra el total de TDEV.'
            : '';
        $excludedNote = $excluded > 0
            ? ' Se excluyó '.Report::count($excluded, 'voucher', 'vouchers').' con la comisión mayor que su precio de venta (dato a corregir en TDEV).'
            : '';

        return [
            'key' => 'tdev',
            'title' => 'TDEV (viajes)',
            'intro' => 'Vouchers de asistencia en viajes emitidos en el periodo: venta, comisión, categoría del plan, estatus de pago y agencias.'.$notFiltered,
            'kpis' => [
                ['label' => 'Vouchers', 'value' => Report::number($vouchers), 'detail' => 'emitidos en el periodo'],
                ['label' => 'Venta (PVP)', 'value' => Report::money($sales), 'detail' => null],
                ['label' => 'Comisión', 'value' => Report::money($commission), 'detail' => $sales > 0 ? Report::percent($commission * 100 / $sales).' de la venta' : null],
                ['label' => 'Venta promedio', 'value' => Report::money($vouchers > 0 ? $sales / $vouchers : 0), 'detail' => 'por voucher'],
            ],
            'blocks' => [
                [
                    'title' => 'Venta por '.$this->report->bucketWord(),
                    'chart' => SvgChart::bars($labels, $amounts, static fn (float $value): string => Report::compactMoney($value)),
                    'layout' => Report::LAYOUT_WIDE,
                    'table' => Report::monthTable($labels, [['Venta', $amounts, true], ['Vouchers', $counts, false]]),
                    'note' => Report::monthlyNote('venta TDEV', $labels, $amounts, $sales, $previous, true, $this->report->bucketWord()).$excludedNote,
                ],
                $this->report->shareBlock('Por categoría del plan', $this->grouped("COALESCE(NULLIF(TRIM(tdev_reports.categoria_del_plan), ''), 'SIN CATEGORÍA')"), 'Categoría', 'de la venta', 'Vouchers'),
                $this->report->shareBlock('Por estatus de pago', $this->grouped("COALESCE(NULLIF(TRIM(tdev_reports.estatus_pago), ''), 'SIN ESTATUS')"), 'Estatus', 'de la venta', 'Vouchers'),
                $this->report->rankingBlock('Las 10 agencias TDEV con más ventas', $this->grouped("COALESCE(NULLIF(TRIM(tdev_reports.agencia), ''), 'SIN AGENCIA')"), 'Agencia', $sales, 'de la venta', 'Vouchers'),
            ],
        ];
    }

    private function query(?\Carbon\CarbonImmutable $from = null, ?\Carbon\CarbonImmutable $to = null, bool $sane = true): QueryBuilder
    {
        $query = DB::table('tdev_reports')->whereRaw(self::DATE_SQL.' BETWEEN ? AND ?', [
            ($from ?? $this->report->filters()->from)->toDateString(),
            ($to ?? $this->report->filters()->to)->toDateString(),
        ]);

        return $sane ? $query->whereRaw(self::SANE_SQL) : $query;
    }

    /**
     * @return list<array{label: string, amount: float, count: int}>
     */
    private function grouped(string $labelSql): array
    {
        return $this->query()
            ->selectRaw("{$labelSql} as label, COUNT(*) as total, COALESCE(SUM(tdev_reports.monto_pvp_precio_de_venta), 0) as amount")
            ->groupBy('label')
            ->orderByDesc('amount')
            ->get()
            ->map(static fn (object $row): array => ['label' => Str::upper(Str::squish((string) $row->label)), 'amount' => round((float) $row->amount, 2), 'count' => (int) $row->total])
            ->all();
    }
}
