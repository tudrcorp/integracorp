<?php

declare(strict_types=1);

namespace App\Support\Administration\Reports;

use App\Enums\AdministrationReportType;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\Collection;
use App\Support\Collections\CollectionReceivableReport;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Arma los reportes de cobranza del Centro de Reportes.
 *
 * Sale de la misma consulta que la tabla «Cobranza Por Mes»
 * ({@see CollectionReceivableReport::scopeNextPendingPerAffiliation()}): una fila
 * por afiliación con su próxima cuota pendiente, con las afiliaciones excluidas,
 * inactivas o anuladas fuera. Así el reporte siempre cuadra con lo que el
 * analista ve en pantalla.
 */
final class CollectionReportBuilder
{
    public const COL_EMISSION_DATE = 0;

    public const COL_HOLDER = 1;

    public const COL_AFFILIATION_CODE = 2;

    public const COL_HOLDER_DOCUMENT = 3;

    public const COL_PAYER = 4;

    public const COL_PAYER_DOCUMENT = 5;

    public const COL_PHONE = 6;

    public const COL_EMAIL = 7;

    public const COL_PLAN = 8;

    public const COL_PERSONS = 9;

    public const COL_AGENCY = 10;

    public const COL_AGENT = 11;

    public const COL_ANNUAL_FEE = 12;

    public const COL_AMOUNT_BY_FREQUENCY = 13;

    public const COL_EFFECTIVE_DATE = 14;

    public const COL_FREQUENCY = 15;

    public const COL_INSTALLMENT = 16;

    public const COL_DUE_DATE = 17;

    public const COL_COLLECTION_STATUS = 18;

    public const COL_DAYS = 19;

    public const COL_AFFILIATE_STATUS = 20;

    public const COL_INSTALLMENT_AMOUNT = 21;

    private const CHUNK_SIZE = 200;

    /**
     * Mismas columnas que la tabla «Cobranza Por Mes», en su orden.
     *
     * @return list<string>
     */
    public static function headings(): array
    {
        return [
            'FECHA DE EMISION',
            'AFILIADO O TITULAR',
            'CODIGO DE AFILIACION',
            'C.I./R.I.F.',
            'TOMADOR',
            'C.I./R.I.F. TOMADOR',
            'TELEFONO',
            'EMAIL',
            'PLAN',
            'POBLACION',
            'AGENCIA',
            'AGENTE',
            'TARIFA ANUAL (US$)',
            'MONTO SEGUN FRECUENCIA (US$)',
            'FECHA DE VIGENCIA',
            'FRACCIONAMIENTO DE CUOTAS PARA PAGO',
            'PERIODOS DE PAGO',
            'FECHA DE VENCIMIENTO',
            'ESTATUS DE COBRO',
            'DIAS',
            'ESTATUS DEL AFILIADO O TITULAR',
            'MONTO DE LA CUOTA',
        ];
    }

    /**
     * @return list<int>
     */
    public static function numericColumns(): array
    {
        return [self::COL_ANNUAL_FEE, self::COL_AMOUNT_BY_FREQUENCY, self::COL_DAYS, self::COL_INSTALLMENT_AMOUNT];
    }

    /**
     * Las columnas que caben legibles en un A4 horizontal. Excel y CSV llevan todas.
     *
     * @return list<int>
     */
    public static function pdfColumns(): array
    {
        return [
            self::COL_HOLDER,
            self::COL_AFFILIATION_CODE,
            self::COL_PAYER,
            self::COL_PLAN,
            self::COL_AGENCY,
            self::COL_AGENT,
            self::COL_ANNUAL_FEE,
            self::COL_AMOUNT_BY_FREQUENCY,
            self::COL_FREQUENCY,
            self::COL_INSTALLMENT,
            self::COL_INSTALLMENT_AMOUNT,
            self::COL_DUE_DATE,
            self::COL_COLLECTION_STATUS,
            self::COL_DAYS,
            self::COL_AFFILIATE_STATUS,
        ];
    }

    /**
     * Consulta filtrada y ordenada del reporte. En el reporte por estructura el
     * orden agrupa por agencia y luego por agente para poder intercalar subtotales.
     *
     * @return Builder<Collection>
     */
    public static function query(CollectionReportFilters $filters, ?CollectionReportNetwork $network = null): Builder
    {
        $query = CollectionReceivableReport::scopeNextPendingPerAffiliation(Collection::query());

        CollectionReceivableReport::applyDueBetween($query, $filters->from?->toDateString(), $filters->to?->toDateString());
        CollectionReceivableReport::applyCollectionStatus($query, $filters->collectionStatus);

        if ($filters->type !== AdministrationReportType::CollectionsByStructure) {
            return $query
                ->orderBy('collections.filter_next_payment_date')
                ->orderBy('collections.id');
        }

        $network ??= self::network($filters);

        if ($network->isEmpty()) {
            return $query->whereRaw('0 = 1');
        }

        return $query
            ->where(function (Builder $scoped) use ($network): void {
                if ($network->agencyCodes !== []) {
                    $scoped
                        ->whereIn('collections.code_agency', $network->agencyCodes)
                        ->orWhereIn('collections.owner_code', $network->agencyCodes);
                }

                if ($network->agentIds !== []) {
                    $scoped->orWhereIn('collections.agent_id', $network->agentIds);
                }
            })
            ->orderBy('collections.code_agency')
            ->orderBy('collections.agent_id')
            ->orderBy('collections.filter_next_payment_date')
            ->orderBy('collections.id');
    }

    public static function network(CollectionReportFilters $filters): CollectionReportNetwork
    {
        return match ($filters->scope) {
            CollectionReportFilters::SCOPE_AGENCY => CollectionReportNetwork::forAgency((string) $filters->agencyCode),
            CollectionReportFilters::SCOPE_AGENT => CollectionReportNetwork::forAgent((int) $filters->agentId, $filters->includeSubagents),
            default => new CollectionReportNetwork([], []),
        };
    }

    public static function count(CollectionReportFilters $filters, ?CollectionReportNetwork $network = null): int
    {
        if ($filters->type === AdministrationReportType::DroppedAffiliationCollections) {
            return DroppedAffiliationCollectionsReport::count($filters);
        }

        return self::query($filters, $network)->reorder()->count();
    }

    /**
     * @param  int|null  $rowLimit  Tope de filas de datos (el PDF no admite miles).
     */
    public static function build(CollectionReportFilters $filters, ?int $rowLimit = null): CollectionReportData
    {
        if ($filters->type === AdministrationReportType::DroppedAffiliationCollections) {
            return DroppedAffiliationCollectionsReport::build($filters, $rowLimit);
        }

        $network = self::network($filters);
        $query = self::query($filters, $network)->with(self::eagerLoads());

        if ($rowLimit !== null) {
            $query->limit($rowLimit);
        }

        return new CollectionReportData(
            title: $filters->type->label(),
            headings: self::headings(),
            lines: $filters->type->isGrouped()
                ? self::groupedLines($query, $rowLimit)
                : self::flatLines($query, $rowLimit),
            numericColumns: self::numericColumns(),
            criteria: $filters->describe(self::scopeLabel($filters, $network)),
            pdfColumns: self::pdfColumns(),
        );
    }

    /**
     * Texto del alcance comercial: a quién cubre el reporte y cuánta red incluye.
     */
    public static function scopeLabel(CollectionReportFilters $filters, ?CollectionReportNetwork $network = null): ?string
    {
        if ($filters->type !== AdministrationReportType::CollectionsByStructure) {
            return null;
        }

        $network ??= self::network($filters);

        if ($filters->scope === CollectionReportFilters::SCOPE_AGENCY) {
            $agency = Agency::query()->where('code', $filters->agencyCode)->first(['code', 'name_corporative', 'agency_type_id']);
            $name = $agency instanceof Agency ? self::agencyName($agency) : (string) $filters->agencyCode;
            $others = max(count($network->agencyCodes) - 1, 0);

            return 'Agencia: '.$filters->agencyCode.' · '.$name.' — incluye '
                .($others === 0 ? 'sin agencias en su estructura' : $others.' '.($others === 1 ? 'agencia' : 'agencias').' de su estructura')
                .' y '.count($network->agentIds).' '.(count($network->agentIds) === 1 ? 'agente' : 'agentes');
        }

        $agent = Agent::query()->find((int) $filters->agentId, ['id', 'name', 'code_agent']);
        $subagents = max(count($network->agentIds) - 1, 0);

        return 'Agente: '.($agent instanceof Agent ? self::agentLabel($agent) : '#'.$filters->agentId)
            .($filters->includeSubagents
                ? ' — incluye '.$subagents.' '.($subagents === 1 ? 'subagente' : 'subagentes')
                : ' — sin subagentes');
    }

    /**
     * Las de la tabla, con los datos del agente que piden los encabezados de grupo.
     *
     * @return list<string>
     */
    public static function eagerLoads(): array
    {
        return array_map(
            static fn (string $relation): string => $relation === 'agent:id,name' ? 'agent:id,name,code_agent,agent_type_id' : $relation,
            CollectionReceivableReport::eagerLoads(),
        );
    }

    /**
     * @return list<scalar|null>
     */
    public static function row(Collection $record): array
    {
        return [
            CollectionReceivableReport::emissionDate($record),
            self::text($record->affiliate_full_name),
            self::text($record->affiliation_code),
            self::text($record->affiliate_ci_rif),
            CollectionReceivableReport::payerName($record),
            CollectionReceivableReport::payerDocument($record),
            self::text($record->affiliate_phone),
            self::text($record->affiliate_email),
            CollectionReceivableReport::planLabel($record),
            self::text($record->persons),
            CollectionReceivableReport::agencyLabel($record),
            self::text($record->agent?->name),
            CollectionReceivableReport::annualFee($record),
            CollectionReceivableReport::amountByFrequency($record),
            CollectionReceivableReport::effectiveDate($record),
            CollectionReceivableReport::paymentFrequency($record),
            CollectionReceivableReport::installmentLabel($record),
            CollectionReceivableReport::dueDate($record)?->format('d/m/Y'),
            CollectionReceivableReport::collectionStatus($record),
            CollectionReceivableReport::daysCount($record),
            CollectionReceivableReport::affiliateStatus($record),
            CollectionReceivableReport::installmentAmount($record),
        ];
    }

    /**
     * @param  Builder<Collection>  $query
     * @return Generator<int, array{kind: string, values: list<scalar|null>, label?: string}>
     */
    private static function flatLines(Builder $query, ?int $rowLimit): Generator
    {
        $total = self::emptyTotals();

        foreach (self::records($query, $rowLimit) as $record) {
            $values = self::row($record);
            self::accumulate($total, $values);

            yield ['kind' => CollectionReportData::LINE_ROW, 'values' => $values];
        }

        yield self::totalsLine(CollectionReportData::LINE_TOTAL, 'TOTAL GENERAL', $total);
    }

    /**
     * Encabezado por agencia, encabezado por agente, filas, subtotal del agente,
     * subtotal de la agencia y, al final, el total general.
     *
     * @param  Builder<Collection>  $query
     * @return Generator<int, array{kind: string, values: list<scalar|null>, label?: string}>
     */
    private static function groupedLines(Builder $query, ?int $rowLimit): Generator
    {
        $agencies = Agency::query()
            ->get(['code', 'name_corporative', 'agency_type_id'])
            ->keyBy(fn (Agency $agency): string => Str::upper(trim((string) $agency->code)));

        $total = self::emptyTotals();
        $agencyTotals = self::emptyTotals();
        $agentTotals = self::emptyTotals();
        $currentAgency = null;
        $currentAgent = null;
        $agencyTitle = '';
        $agentTitle = '';

        foreach (self::records($query, $rowLimit) as $record) {
            $agencyKey = Str::upper(trim((string) $record->code_agency));
            $agentKey = (string) ((int) $record->agent_id);

            if ($agencyKey !== $currentAgency) {
                if ($currentAgency !== null) {
                    yield self::totalsLine(CollectionReportData::LINE_SUBTOTAL, 'Subtotal '.$agentTitle, $agentTotals);
                    yield self::totalsLine(CollectionReportData::LINE_SUBTOTAL, 'Subtotal '.$agencyTitle, $agencyTotals);
                }

                $agency = $agencies->get($agencyKey);
                $agencyTitle = self::agencyTitle($agencyKey, $agency);
                $currentAgency = $agencyKey;
                $currentAgent = null;
                $agencyTotals = self::emptyTotals();

                yield ['kind' => CollectionReportData::LINE_AGENCY, 'label' => $agencyTitle, 'values' => []];
            }

            if ($agentKey !== $currentAgent) {
                if ($currentAgent !== null) {
                    yield self::totalsLine(CollectionReportData::LINE_SUBTOTAL, 'Subtotal '.$agentTitle, $agentTotals);
                }

                $agentTitle = $record->agent instanceof Agent
                    ? 'Agente: '.self::agentLabel($record->agent).((int) $record->agent->agent_type_id === CollectionReportNetwork::AGENT_TYPE_SUBAGENT ? ' · Subagente' : '')
                    : 'Venta directa de la agencia (sin agente)';
                $currentAgent = $agentKey;
                $agentTotals = self::emptyTotals();

                yield ['kind' => CollectionReportData::LINE_AGENT, 'label' => $agentTitle, 'values' => []];
            }

            $values = self::row($record);
            self::accumulate($total, $values);
            self::accumulate($agencyTotals, $values);
            self::accumulate($agentTotals, $values);

            yield ['kind' => CollectionReportData::LINE_ROW, 'values' => $values];
        }

        if ($currentAgency !== null) {
            yield self::totalsLine(CollectionReportData::LINE_SUBTOTAL, 'Subtotal '.$agentTitle, $agentTotals);
            yield self::totalsLine(CollectionReportData::LINE_SUBTOTAL, 'Subtotal '.$agencyTitle, $agencyTotals);
        }

        yield self::totalsLine(CollectionReportData::LINE_TOTAL, 'TOTAL GENERAL', $total);
    }

    /**
     * Recorre por lotes respetando el orden. Con tope (PDF) basta una consulta.
     *
     * @param  Builder<Collection>  $query
     * @return iterable<int, Collection>
     */
    private static function records(Builder $query, ?int $rowLimit): iterable
    {
        return $rowLimit !== null ? $query->get() : $query->lazy(self::CHUNK_SIZE);
    }

    /**
     * @return array{count: int, annual_fee: float, amount_by_frequency: float, installment_amount: float}
     */
    private static function emptyTotals(): array
    {
        return ['count' => 0, 'annual_fee' => 0.0, 'amount_by_frequency' => 0.0, 'installment_amount' => 0.0];
    }

    /**
     * @param  array{count: int, annual_fee: float, amount_by_frequency: float, installment_amount: float}  $totals
     * @param  list<scalar|null>  $values
     */
    private static function accumulate(array &$totals, array $values): void
    {
        $totals['count']++;
        $totals['annual_fee'] += (float) ($values[self::COL_ANNUAL_FEE] ?? 0);
        $totals['amount_by_frequency'] += (float) ($values[self::COL_AMOUNT_BY_FREQUENCY] ?? 0);
        $totals['installment_amount'] += (float) ($values[self::COL_INSTALLMENT_AMOUNT] ?? 0);
    }

    /**
     * @param  array{count: int, annual_fee: float, amount_by_frequency: float, installment_amount: float}  $totals
     * @return array{kind: string, values: list<scalar|null>, label: string}
     */
    private static function totalsLine(string $kind, string $label, array $totals): array
    {
        $values = array_fill(0, count(self::headings()), null);
        $values[self::COL_ANNUAL_FEE] = round($totals['annual_fee'], 2);
        $values[self::COL_AMOUNT_BY_FREQUENCY] = round($totals['amount_by_frequency'], 2);
        $values[self::COL_INSTALLMENT_AMOUNT] = round($totals['installment_amount'], 2);

        return [
            'kind' => $kind,
            'label' => $label.' · '.$totals['count'].' '.($totals['count'] === 1 ? 'afiliación' : 'afiliaciones'),
            'values' => $values,
        ];
    }

    private static function agencyTitle(string $code, ?Agency $agency): string
    {
        if ($code === '') {
            return 'Sin agencia registrada';
        }

        if (! $agency instanceof Agency) {
            return 'Agencia '.$code;
        }

        $role = match ((int) $agency->agency_type_id) {
            CollectionReportNetwork::AGENCY_TYPE_MASTER => 'Agencia master',
            CollectionReportNetwork::AGENCY_TYPE_GENERAL => 'Agencia general',
            default => 'Agencia',
        };

        return $role.': '.trim((string) $agency->code).' · '.self::agencyName($agency);
    }

    private static function agencyName(Agency $agency): string
    {
        if (Str::upper(trim((string) $agency->code)) === 'TDG-100') {
            return 'TUDRENCASA';
        }

        return trim((string) ($agency->name_corporative ?? '')) ?: 'Sin razón social';
    }

    public static function agentLabel(Agent $agent): string
    {
        $code = trim((string) ($agent->code_agent ?? ''));

        return trim((string) $agent->name).' ('.($code !== '' ? $code : 'AGT-000'.$agent->id).')';
    }

    private static function text(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' || $value === 'N/A' ? null : $value;
    }
}
