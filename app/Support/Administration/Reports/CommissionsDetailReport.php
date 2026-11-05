<?php

declare(strict_types=1);

namespace App\Support\Administration\Reports;

use App\Models\Commission;
use App\Support\CommercialStructure\CommissionReferidorPercentage;
use Generator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reporte «Detallado de comisiones»: una fila por comisión calculada, con toda la estructura
 * comercial (agencia master, agencia general, agente y subagente, referidor) y lo que cobra
 * cada nivel en USD y VES. Las mismas columnas que «Exportar XLS» de Detallado de Comisiones.
 *
 * El periodo se aplica sobre la fecha de cálculo de la comisión (`commissions.created_at`).
 */
final class CommissionsDetailReport
{
    public const COL_DATE = 0;

    public const COL_SALE = 1;

    public const COL_MASTER = 2;

    public const COL_GENERAL = 3;

    public const COL_AGENT = 4;

    public const COL_SUBAGENT = 5;

    public const COL_REFERIDOR = 6;

    public const COL_AFFILIATE = 7;

    public const COL_AFFILIATION = 8;

    public const COL_PLAN = 9;

    public const COL_COVERAGE = 10;

    public const COL_AMOUNT = 11;

    public const COL_VETO = 12;

    public const COL_FREQUENCY = 13;

    public const COL_PAY_USD = 14;

    public const COL_PAY_VES = 15;

    public const COL_METHOD = 16;

    public const COL_MASTER_PERCENT = 17;

    public const COL_MASTER_USD = 18;

    public const COL_MASTER_VES = 19;

    public const COL_GENERAL_PERCENT = 20;

    public const COL_GENERAL_USD = 21;

    public const COL_GENERAL_VES = 22;

    public const COL_AGENT_PERCENT = 23;

    public const COL_AGENT_USD = 24;

    public const COL_AGENT_VES = 25;

    public const COL_REFERIDOR_PERCENT = 26;

    public const COL_REFERIDOR_USD = 27;

    public const COL_REFERIDOR_VES = 28;

    /** Columnas que se suman en el total general. */
    public const SUMMED_COLUMNS = [
        self::COL_PAY_USD,
        self::COL_PAY_VES,
        self::COL_MASTER_USD,
        self::COL_MASTER_VES,
        self::COL_GENERAL_USD,
        self::COL_GENERAL_VES,
        self::COL_AGENT_USD,
        self::COL_AGENT_VES,
        self::COL_REFERIDOR_USD,
        self::COL_REFERIDOR_VES,
    ];

    private const CHUNK_SIZE = 300;

    /**
     * @return list<string>
     */
    public static function headings(): array
    {
        return [
            'FECHA DE CALCULO',
            'NRO. VENTA',
            'AGENCIA MASTER',
            'AGENCIA GENERAL',
            'AGENTE',
            'SUBAGENTE',
            'REFERIDOR',
            'AFILIADO',
            'AFILIACION',
            'PLAN',
            'COBERTURA',
            'MONTO',
            'VETO',
            'FRECUENCIA DE PAGO',
            'MONTO PAGADO USD',
            'MONTO PAGADO VES',
            'METODO DE PAGO',
            '% AGENCIA MASTER',
            'COMISION AGENCIA MASTER USD',
            'COMISION AGENCIA MASTER VES',
            '% AGENCIA GENERAL',
            'COMISION AGENCIA GENERAL USD',
            'COMISION AGENCIA GENERAL VES',
            '% AGENTE',
            'COMISION AGENTE USD',
            'COMISION AGENTE VES',
            '% REFERIDOR',
            'COMISION REFERIDOR USD',
            'COMISION REFERIDOR VES',
        ];
    }

    /**
     * Relaciones para resolver toda la estructura sin una consulta por fila.
     *
     * @return list<string>
     */
    public static function eagerLoads(): array
    {
        return [
            'agency.masterAgency',
            'agent.ownerAgent',
            ...CommissionReferidorPercentage::eagerLoadRelations(),
            'plan',
            'coverage',
        ];
    }

    /**
     * @return Builder<Commission>
     */
    public static function query(CollectionReportFilters $filters): Builder
    {
        return Commission::query()
            ->when($filters->from !== null, fn (Builder $query) => $query->where('commissions.created_at', '>=', $filters->from->startOfDay()))
            ->when($filters->to !== null, fn (Builder $query) => $query->where('commissions.created_at', '<=', $filters->to->endOfDay()))
            ->orderBy('commissions.created_at')
            ->orderBy('commissions.id');
    }

    public static function count(CollectionReportFilters $filters): int
    {
        return self::query($filters)->reorder()->count();
    }

    public static function build(CollectionReportFilters $filters, ?int $rowLimit = null): CollectionReportData
    {
        $query = self::query($filters)->with(self::eagerLoads());

        if ($rowLimit !== null) {
            $query->limit($rowLimit);
        }

        $numeric = [
            self::COL_AMOUNT,
            self::COL_MASTER_PERCENT,
            self::COL_GENERAL_PERCENT,
            self::COL_AGENT_PERCENT,
            self::COL_REFERIDOR_PERCENT,
            ...self::SUMMED_COLUMNS,
        ];

        return new CollectionReportData(
            title: $filters->type->label(),
            headings: self::headings(),
            lines: self::lines($query, $rowLimit),
            numericColumns: $numeric,
            criteria: $filters->describe(),
            pdfColumns: [
                self::COL_DATE,
                self::COL_SALE,
                self::COL_MASTER,
                self::COL_GENERAL,
                self::COL_AGENT,
                self::COL_SUBAGENT,
                self::COL_AFFILIATION,
                self::COL_PAY_USD,
                self::COL_MASTER_USD,
                self::COL_GENERAL_USD,
                self::COL_AGENT_USD,
                self::COL_REFERIDOR_USD,
            ],
            moneyColumns: [self::COL_AMOUNT, ...self::SUMMED_COLUMNS],
        );
    }

    /**
     * @param  Builder<Commission>  $query
     * @return Generator<int, array{kind: string, values: list<scalar|null>, label?: string}>
     */
    private static function lines(Builder $query, ?int $rowLimit): Generator
    {
        $totals = array_fill_keys(self::SUMMED_COLUMNS, 0.0);
        $count = 0;

        $records = $rowLimit !== null ? $query->get()->lazy() : $query->lazy(self::CHUNK_SIZE);

        foreach ($records as $record) {
            $values = self::row($record);

            foreach (self::SUMMED_COLUMNS as $column) {
                $totals[$column] += (float) ($values[$column] ?? 0);
            }

            $count++;

            yield ['kind' => CollectionReportData::LINE_ROW, 'values' => $values];
        }

        $values = array_fill(0, count(self::headings()), null);

        foreach ($totals as $column => $sum) {
            $values[$column] = round($sum, 2);
        }

        yield [
            'kind' => CollectionReportData::LINE_TOTAL,
            'label' => 'TOTAL GENERAL: '.number_format($count, 0, ',', '.').' '.($count === 1 ? 'comisión' : 'comisiones'),
            'values' => $values,
        ];
    }

    /**
     * @return list<scalar|null>
     */
    public static function row(Commission $record): array
    {
        return [
            $record->created_at?->format('d/m/Y'),
            self::text($record->code),
            self::dash($record->masterAgencyDisplayName()),
            self::dash($record->generalAgencyDisplayName()),
            self::dash($record->structureAgentName()),
            self::dash($record->subagentName()),
            $record->referidorBeneficiaryLabel() === 'Sin referidor' ? null : $record->referidorBeneficiaryLabel(),
            self::text($record->affiliate_full_name),
            self::text($record->affiliation_code),
            self::text($record->plan?->description),
            self::number($record->coverage?->price),
            self::number($record->amount),
            self::text($record->veto),
            self::text($record->payment_frequency),
            self::number($record->pay_amount_usd),
            self::number($record->pay_amount_ves),
            self::text($record->payment_method),
            self::number($record->porcent_agency_master),
            self::number($record->commission_agency_master_usd),
            self::number($record->commission_agency_master_ves),
            self::number($record->porcent_agency_general),
            self::number($record->commission_agency_general_usd),
            self::number($record->commission_agency_general_ves),
            self::number($record->porcent_agente),
            self::number($record->commission_agent_usd),
            self::number($record->commission_agent_ves),
            self::number($record->porcent_referidor),
            self::number($record->commission_referidor_usd),
            self::number($record->commission_referidor_ves),
        ];
    }

    private static function dash(string $value): ?string
    {
        return $value === '-' ? null : $value;
    }

    private static function number(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    private static function text(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' || $value === 'N/A' ? null : $value;
    }
}
