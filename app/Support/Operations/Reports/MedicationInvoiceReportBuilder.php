<?php

declare(strict_types=1);

namespace App\Support\Operations\Reports;

use App\Enums\OperationReportType;
use App\Filament\Operations\Resources\OperationMedicationInvoices\OperationMedicationInvoiceResource;
use App\Models\OperationMedicationInvoice;
use App\Models\OperationMedicationInvoiceItem;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Operations\MedicationInvoiceLifecycle;
use Generator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reporte de facturas a pacientes: el mismo para la acción masiva de la tabla y para el
 * Generador de reportes de Operaciones.
 *
 * Una fila por factura y la totalización al pie. Las anuladas se listan (dejan traza) pero no
 * suman al total. Parte de la consulta del recurso, así que un usuario de proveedor nunca ve
 * más facturas que en la tabla. Tres consultas en total: las filas (cursor) y dos agregadas.
 */
final class MedicationInvoiceReportBuilder
{
    /** Tope de facturas elegidas a mano en una sola acción masiva. */
    public const MAX_SELECTED = 20000;

    /**
     * Columnas que caben en el PDF horizontal; Excel y CSV traen todas.
     *
     * @var list<int>
     */
    private const PDF_COLUMNS = [0, 1, 2, 3, 5, 7, 9, 10, 12];

    public static function allows(): bool
    {
        return OperationMedicationInvoiceResource::canViewAny()
            && BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::REPORT_MEDICATION_INVOICES);
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            OperationMedicationInvoice::STATUS_PENDING_PAYMENT => MedicationInvoiceLifecycle::statusLabel(OperationMedicationInvoice::STATUS_PENDING_PAYMENT),
            OperationMedicationInvoice::STATUS_PAID => MedicationInvoiceLifecycle::statusLabel(OperationMedicationInvoice::STATUS_PAID),
            OperationMedicationInvoice::STATUS_ANNULLED => MedicationInvoiceLifecycle::statusLabel(OperationMedicationInvoice::STATUS_ANNULLED),
        ];
    }

    /**
     * @return Builder<OperationMedicationInvoice>
     */
    public static function query(OperationReportFilters $filters): Builder
    {
        $query = OperationMedicationInvoiceResource::getEloquentQuery();

        if ($filters->invoiceIds !== []) {
            return $query->whereIn('operation_medication_invoices.id', $filters->invoiceIds);
        }

        return $query
            ->whereBetween('operation_medication_invoices.issued_at', [$filters->from->startOfDay(), $filters->to->endOfDay()])
            ->when($filters->invoiceStatuses !== [], fn (Builder $q): Builder => $q->whereIn('operation_medication_invoices.status', $filters->invoiceStatuses))
            ->when($filters->invoiceTypes !== [], fn (Builder $q): Builder => $q->whereIn('operation_medication_invoices.invoice_type', $filters->invoiceTypes));
    }

    public static function count(OperationReportFilters $filters): int
    {
        return self::query($filters)->count();
    }

    public static function build(OperationReportType $type, OperationReportFilters $filters, ?int $rowLimit = null): OperationReportData
    {
        $summary = self::summary($filters);

        // El select va antes de withCount/withSum: si va después, borra esas columnas calculadas.
        $rowsQuery = self::query($filters)
            ->select([
                'operation_medication_invoices.id', 'invoice_number', 'issued_at', 'patient_name', 'patient_identification',
                'case_code', 'operation_coordination_service_id', 'status', 'invoice_type', 'total_usd', 'bcv_rate', 'total_ves',
                'issued_by_name', 'paid_at', 'payment_method', 'payment_reference',
            ])
            ->withCount('items')
            ->withSum('items as units', 'quantity')
            ->orderBy('operation_medication_invoices.issued_at')
            ->orderBy('operation_medication_invoices.id');

        if ($rowLimit !== null) {
            $rowsQuery->limit($rowLimit);
        }

        $rows = (static function () use ($rowsQuery): Generator {
            foreach ($rowsQuery->cursor() as $invoice) {
                yield [
                    (string) $invoice->invoice_number,
                    OperationMedicationInvoice::typeLabel($invoice->invoice_type),
                    $invoice->issued_at?->format('d/m/Y H:i'),
                    (string) $invoice->patient_name,
                    $invoice->patient_identification,
                    $invoice->case_code,
                    $invoice->operation_coordination_service_id ? '#'.$invoice->operation_coordination_service_id : null,
                    MedicationInvoiceLifecycle::statusLabel($invoice->status),
                    (int) $invoice->items_count,
                    (int) $invoice->units,
                    round((float) $invoice->total_usd, 2),
                    round((float) $invoice->bcv_rate, 2),
                    round((float) $invoice->total_ves, 2),
                    $invoice->issued_by_name,
                    $invoice->paid_at?->format('d/m/Y'),
                    filled($invoice->payment_method) ? MedicationInvoiceLifecycle::paymentMethodLabel($invoice->payment_method) : null,
                    $invoice->payment_reference,
                ];
            }
        })();

        $valid = $summary['count'] - $summary['annulled_count'];

        return new OperationReportData(
            title: $type->label(),
            headings: ['N.º factura', 'Tipo', 'Emisión', 'Paciente', 'Cédula', 'Caso', 'Servicio médico', 'Estado', 'Líneas', 'Unidades', 'Total (US$)', 'Tasa BCV', 'Total (Bs.)', 'Emitida por', 'Cobrada el', 'Forma de pago', 'Referencia'],
            rows: $rows,
            totals: $summary['count'] === 0 ? null : [
                'TOTAL (sin anuladas)', '', '', '', '', '', '',
                $valid.' '.($valid === 1 ? 'factura' : 'facturas'),
                $summary['lines'],
                $summary['units'],
                $summary['total_usd'],
                '',
                $summary['total_ves'],
                '', '', '', '',
            ],
            numericColumns: [8, 9, 10, 11, 12],
            criteria: [...self::criteria($filters), ...self::summaryLines($summary)],
            pdfColumns: self::PDF_COLUMNS,
            totalRows: $summary['count'],
        );
    }

    /**
     * Totales: una consulta agregada por facturas y otra por sus líneas. Montos y unidades excluyen las anuladas.
     *
     * @return array{count: int, annulled_count: int, pending_count: int, paid_count: int, pending_usd: float, paid_usd: float, annulled_usd: float, total_usd: float, total_ves: float, lines: int, units: int}
     */
    public static function summary(OperationReportFilters $filters): array
    {
        $annulled = OperationMedicationInvoice::STATUS_ANNULLED;
        $pending = OperationMedicationInvoice::STATUS_PENDING_PAYMENT;
        $paid = OperationMedicationInvoice::STATUS_PAID;
        $invoiceIds = self::query($filters)->select('operation_medication_invoices.id');

        $row = OperationMedicationInvoice::query()
            ->whereIn('id', $invoiceIds)
            ->toBase()
            ->selectRaw('COUNT(*) AS total_count')
            ->selectRaw('SUM(status = ?) AS annulled_count', [$annulled])
            ->selectRaw('SUM(status = ?) AS pending_count', [$pending])
            ->selectRaw('SUM(status = ?) AS paid_count', [$paid])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN total_usd END), 0) AS pending_usd', [$pending])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN total_usd END), 0) AS paid_usd', [$paid])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN total_usd END), 0) AS annulled_usd', [$annulled])
            ->selectRaw('COALESCE(SUM(CASE WHEN status <> ? THEN total_usd END), 0) AS sum_usd', [$annulled])
            ->selectRaw('COALESCE(SUM(CASE WHEN status <> ? THEN total_ves END), 0) AS sum_ves', [$annulled])
            ->first();

        $items = OperationMedicationInvoiceItem::query()
            ->whereIn('operation_medication_invoice_id', self::query($filters)
                ->where('operation_medication_invoices.status', '<>', $annulled)
                ->select('operation_medication_invoices.id'))
            ->toBase()
            ->selectRaw('COUNT(*) AS sum_lines')
            ->selectRaw('COALESCE(SUM(quantity), 0) AS sum_units')
            ->first();

        return [
            'count' => (int) ($row->total_count ?? 0),
            'annulled_count' => (int) ($row->annulled_count ?? 0),
            'pending_count' => (int) ($row->pending_count ?? 0),
            'paid_count' => (int) ($row->paid_count ?? 0),
            'pending_usd' => round((float) ($row->pending_usd ?? 0), 2),
            'paid_usd' => round((float) ($row->paid_usd ?? 0), 2),
            'annulled_usd' => round((float) ($row->annulled_usd ?? 0), 2),
            'total_usd' => round((float) ($row->sum_usd ?? 0), 2),
            'total_ves' => round((float) ($row->sum_ves ?? 0), 2),
            'lines' => (int) ($items->sum_lines ?? 0),
            'units' => (int) ($items->sum_units ?? 0),
        ];
    }

    /**
     * @return list<string>
     */
    private static function criteria(OperationReportFilters $filters): array
    {
        if ($filters->invoiceIds !== []) {
            return ['Facturas seleccionadas en la tabla: '.number_format(count($filters->invoiceIds), 0, ',', '.')];
        }

        $lines = ['Emitidas del '.$filters->from->format('d/m/Y').' al '.$filters->to->format('d/m/Y')];

        if ($filters->invoiceTypes !== []) {
            $lines[] = 'Tipo: '.implode(', ', array_map(fn (string $type): string => OperationMedicationInvoice::typeLabel($type), $filters->invoiceTypes));
        }

        if ($filters->invoiceStatuses !== []) {
            $labels = array_map(fn (string $status): string => self::statusOptions()[$status] ?? $status, $filters->invoiceStatuses);
            $lines[] = 'Estado: '.implode(', ', $labels);
        }

        return $lines;
    }

    /**
     * @param  array{count: int, annulled_count: int, pending_count: int, paid_count: int, pending_usd: float, paid_usd: float, annulled_usd: float, total_usd: float, total_ves: float, lines: int, units: int}  $summary
     * @return list<string>
     */
    private static function summaryLines(array $summary): array
    {
        $usd = static fn (float $amount): string => 'US$ '.number_format($amount, 2, ',', '.');

        return [
            'Pendientes por cobrar: '.$summary['pending_count'].' ('.$usd($summary['pending_usd']).') · Cobradas: '.$summary['paid_count'].' ('.$usd($summary['paid_usd']).')'
                .' · Anuladas: '.$summary['annulled_count'].' ('.$usd($summary['annulled_usd']).', no suman)',
            'Total facturado: '.$usd($summary['total_usd']).' · Bs. '.number_format($summary['total_ves'], 2, ',', '.').' · '.$summary['units'].($summary['units'] === 1 ? ' unidad.' : ' unidades.'),
        ];
    }
}
