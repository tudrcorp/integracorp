<?php

declare(strict_types=1);

namespace App\Support\Administration\Reports;

use App\Models\Collection;
use App\Models\CollectionDropoutReview;
use App\Support\Collections\CollectionDueDate;
use App\Support\Collections\CollectionReceivableReport;
use App\Support\Collections\DroppedAffiliationCollections;
use Carbon\CarbonImmutable;
use Generator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reporte «Cuotas de afiliaciones dadas de baja»: una fila por cuota por pagar de una
 * afiliación excluida, inactiva o anulada, para que Administración decida si se anula o
 * se mantiene como deuda. Sale de la misma consulta que la página donde se aplica la
 * decisión ({@see DroppedAffiliationCollections::pendingQuery()}).
 */
final class DroppedAffiliationCollectionsReport
{
    public const COL_AFFILIATION_CODE = 0;

    public const COL_TYPE = 1;

    public const COL_AFFILIATION_STATUS = 2;

    public const COL_DROPPED_AT = 3;

    public const COL_HOLDER = 4;

    public const COL_HOLDER_DOCUMENT = 5;

    public const COL_PAYER = 6;

    public const COL_AGENCY = 7;

    public const COL_AGENT = 8;

    public const COL_INVOICE = 9;

    public const COL_INSTALLMENT = 10;

    public const COL_DUE_DATE = 11;

    public const COL_TIMING = 12;

    public const COL_DAYS_OVERDUE = 13;

    public const COL_REVIEW = 14;

    public const COL_REVIEW_NOTE = 15;

    public const COL_AMOUNT = 16;

    private const CHUNK_SIZE = 200;

    /**
     * @return list<string>
     */
    public static function headings(): array
    {
        return [
            'CODIGO DE AFILIACION',
            'TIPO',
            'ESTATUS ACTUAL DE LA AFILIACION',
            'FECHA DE BAJA (REGISTRO)',
            'AFILIADO O TITULAR',
            'C.I./R.I.F.',
            'TOMADOR',
            'AGENCIA',
            'AGENTE',
            'AVISO',
            'PERIODO DE PAGO',
            'FECHA DE VENCIMIENTO',
            'VENCE ANTES O DESPUES DE LA BAJA',
            'DIAS DE ATRASO',
            'REVISION',
            'NOTA DE LA REVISION',
            'MONTO DE LA CUOTA',
        ];
    }

    /**
     * @return Builder<Collection>
     */
    public static function query(CollectionReportFilters $filters): Builder
    {
        $query = DroppedAffiliationCollections::pendingQuery();

        CollectionReceivableReport::applyDueBetween($query, $filters->from?->toDateString(), $filters->to?->toDateString());
        CollectionReceivableReport::applyCollectionStatus($query, $filters->collectionStatus);

        return $query
            ->orderBy('collections.affiliation_code')
            ->orderBy('collections.filter_next_payment_date')
            ->orderBy('collections.id');
    }

    public static function count(CollectionReportFilters $filters): int
    {
        return self::query($filters)->reorder()->count();
    }

    public static function build(CollectionReportFilters $filters, ?int $rowLimit = null): CollectionReportData
    {
        $query = self::query($filters)->with([...CollectionReceivableReport::eagerLoads(), 'activeDropoutReview']);

        if ($rowLimit !== null) {
            $query->limit($rowLimit);
        }

        return new CollectionReportData(
            title: $filters->type->label(),
            headings: self::headings(),
            lines: self::lines($query, $rowLimit),
            numericColumns: [self::COL_DAYS_OVERDUE, self::COL_AMOUNT],
            criteria: [
                ...$filters->describe(),
                'Incluye las cuotas por pagar de afiliaciones excluidas, inactivas o anuladas (estatus actual), por decidir o mantenidas como deuda.',
            ],
            pdfColumns: [
                self::COL_AFFILIATION_CODE,
                self::COL_TYPE,
                self::COL_AFFILIATION_STATUS,
                self::COL_DROPPED_AT,
                self::COL_HOLDER,
                self::COL_AGENCY,
                self::COL_INVOICE,
                self::COL_DUE_DATE,
                self::COL_TIMING,
                self::COL_DAYS_OVERDUE,
                self::COL_REVIEW,
                self::COL_AMOUNT,
            ],
            moneyColumns: [self::COL_AMOUNT],
        );
    }

    /**
     * Filas de datos y, al final, subtotales por estatus de la afiliación y por
     * «antes / después de la baja», y el total general.
     *
     * @param  Builder<Collection>  $query
     * @return Generator<int, array{kind: string, values: list<scalar|null>, label?: string}>
     */
    private static function lines(Builder $query, ?int $rowLimit): Generator
    {
        $today = CarbonImmutable::today();
        $byStatus = [];
        $byTiming = [];
        $total = ['count' => 0, 'amount' => 0.0];

        $records = $rowLimit !== null ? $query->get()->lazy() : $query->lazy(self::CHUNK_SIZE);

        foreach ($records->chunk(self::CHUNK_SIZE) as $chunk) {
            $dates = DroppedAffiliationCollections::dropoutDates($chunk->pluck('affiliation_code')->all());

            foreach ($chunk as $record) {
                $values = self::row($record, $dates[(string) $record->affiliation_code] ?? null, $today);
                $amount = (float) ($values[self::COL_AMOUNT] ?? 0);

                self::add($byStatus, (string) ($values[self::COL_AFFILIATION_STATUS] ?? 'SIN ESTATUS'), $amount);
                self::add($byTiming, (string) $values[self::COL_TIMING], $amount);
                $total['count']++;
                $total['amount'] += $amount;

                yield ['kind' => CollectionReportData::LINE_ROW, 'values' => $values];
            }
        }

        ksort($byStatus);
        ksort($byTiming);

        foreach ($byStatus as $status => $sum) {
            yield self::summaryLine(CollectionReportData::LINE_SUBTOTAL, 'Afiliaciones '.$status.': '.self::cuotas($sum['count']), $sum['amount']);
        }

        foreach ($byTiming as $timing => $sum) {
            yield self::summaryLine(CollectionReportData::LINE_SUBTOTAL, $timing.': '.self::cuotas($sum['count']), $sum['amount']);
        }

        yield self::summaryLine(CollectionReportData::LINE_TOTAL, 'TOTAL GENERAL: '.self::cuotas($total['count']), $total['amount']);
    }

    /**
     * @return list<scalar|null>
     */
    public static function row(Collection $record, ?CarbonImmutable $droppedAt, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $due = CollectionDueDate::of($record);
        $review = $record->activeDropoutReview;

        return [
            self::text($record->affiliation_code),
            DroppedAffiliationCollections::affiliationType($record) === DroppedAffiliationCollections::TYPE_CORPORATE ? 'Corporativa' : 'Individual',
            CollectionReceivableReport::affiliateStatus($record),
            $droppedAt?->format('d/m/Y'),
            self::text(CollectionReceivableReport::holderName($record) ?? $record->affiliate_full_name),
            self::text(CollectionReceivableReport::holderDocument($record) ?? $record->affiliate_ci_rif),
            CollectionReceivableReport::payerName($record),
            CollectionReceivableReport::agencyLabel($record),
            self::text($record->agent?->name),
            self::text($record->collection_invoice_number),
            CollectionReceivableReport::installmentLabel($record),
            $due?->format('d/m/Y'),
            DroppedAffiliationCollections::timingLabel($due, $droppedAt),
            $due !== null && $due->lessThan($today) ? (int) $due->diffInDays($today) : 0,
            $review instanceof CollectionDropoutReview ? $review->decisionLabel() : 'Por decidir',
            $review instanceof CollectionDropoutReview ? self::text($review->note) : null,
            round((float) ($record->total_amount ?? 0), 2),
        ];
    }

    /**
     * @return array{kind: string, values: list<scalar|null>, label: string}
     */
    private static function summaryLine(string $kind, string $label, float $amount): array
    {
        $values = array_fill(0, count(self::headings()), null);
        $values[self::COL_AMOUNT] = round($amount, 2);

        return ['kind' => $kind, 'label' => $label, 'values' => $values];
    }

    /**
     * @param  array<string, array{count: int, amount: float}>  $bucket
     */
    private static function add(array &$bucket, string $key, float $amount): void
    {
        $bucket[$key] ??= ['count' => 0, 'amount' => 0.0];
        $bucket[$key]['count']++;
        $bucket[$key]['amount'] += $amount;
    }

    private static function cuotas(int $count): string
    {
        return number_format($count, 0, ',', '.').' '.($count === 1 ? 'cuota' : 'cuotas');
    }

    private static function text(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' || $value === 'N/A' ? null : $value;
    }
}
