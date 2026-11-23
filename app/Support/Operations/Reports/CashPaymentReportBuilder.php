<?php

declare(strict_types=1);

namespace App\Support\Operations\Reports;

use App\Enums\OperationReportType;
use App\Filament\Operations\Resources\OperationCashPayments\OperationCashPaymentResource;
use App\Models\OperationCashPayment;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Filament\Operations\OperationsSupplierScope;
use Generator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reporte «Pagos de contado» del Generador de reportes de Operaciones.
 *
 * Sale de `operation_cash_payments`: cada factura de proveedor que se pagó de contado al
 * cargarla (un registro por orden; las partes de un pago masivo comparten `payment_batch`).
 * El periodo es la fecha del pago. El analista elige qué estatus incluir (Registrado,
 * Reversado). Resumen por estatus y moneda y luego el detalle. Solo analistas internos
 * con el permiso propio.
 */
final class CashPaymentReportBuilder
{
    public const KIND_SINGLE = 'individual';

    public const KIND_BATCH = 'masivo';

    /**
     * Columnas que caben en el PDF horizontal; Excel y CSV traen todas.
     *
     * @var list<int>
     */
    private const PDF_COLUMNS = [0, 1, 2, 4, 5, 6, 8, 9, 10, 11, 12];

    public static function allows(): bool
    {
        return OperationsSupplierScope::authenticatedUserIsTdgAnalyst()
            && OperationCashPaymentResource::canViewAny()
            && BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::REPORT_CASH_PAYMENTS);
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            OperationCashPayment::STATUS_REGISTERED => 'Registrado',
            OperationCashPayment::STATUS_REVERSED => 'Reversado',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function kindOptions(): array
    {
        return [
            self::KIND_SINGLE => 'Individual (una orden)',
            self::KIND_BATCH => 'Masivo (una factura de varias órdenes)',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function currencyOptions(): array
    {
        return ['USD' => 'US$', 'VES' => 'Bolívares'];
    }

    /**
     * Proveedores con pagos de contado (nombre congelado), para el filtro.
     *
     * @return array<string, string>
     */
    public static function supplierOptions(): array
    {
        return OperationCashPayment::query()
            ->whereNotNull('supplier_name')
            ->where('supplier_name', '<>', '')
            ->distinct()
            ->orderBy('supplier_name')
            ->limit(1000)
            ->pluck('supplier_name', 'supplier_name')
            ->all();
    }

    /**
     * @return Builder<OperationCashPayment>
     */
    public static function query(OperationReportFilters $filters): Builder
    {
        $kinds = $filters->cashKinds;

        return OperationCashPayment::query()
            ->whereBetween('operation_cash_payments.payment_date', [$filters->from->toDateString(), $filters->to->toDateString()])
            ->whereIn('operation_cash_payments.status', $filters->cashStatuses === [] ? ['__ninguno__'] : $filters->cashStatuses)
            ->when($filters->cashCurrencies !== [], fn (Builder $q): Builder => $q->whereIn('operation_cash_payments.payment_currency', $filters->cashCurrencies))
            ->when($filters->cashSuppliers !== [], fn (Builder $q): Builder => $q->whereIn('operation_cash_payments.supplier_name', $filters->cashSuppliers))
            ->when(count($kinds) === 1, fn (Builder $q): Builder => $kinds[0] === self::KIND_BATCH
                ? $q->whereNotNull('operation_cash_payments.payment_batch')
                : $q->whereNull('operation_cash_payments.payment_batch'));
    }

    public static function count(OperationReportFilters $filters): int
    {
        return self::query($filters)->count();
    }

    public static function build(OperationReportType $type, OperationReportFilters $filters, ?int $rowLimit = null): OperationReportData
    {
        $summary = self::summary($filters);
        $total = array_sum(array_column($summary, 'count'));
        $rowsQuery = self::query($filters)->with('serviceOrder:id,order_number');

        $rows = (static function () use ($rowsQuery, $rowLimit): Generator {
            $emitted = 0;

            foreach ($rowsQuery->lazyById(500, 'operation_cash_payments.id', 'id') as $payment) {
                if ($rowLimit !== null && $emitted >= $rowLimit) {
                    return;
                }

                $emitted++;

                yield [
                    $payment->payment_date?->format('d/m/Y'),
                    (string) ($payment->serviceOrder?->order_number ?: '#'.$payment->operation_service_order_id),
                    (string) $payment->supplier_name,
                    $payment->supplier_rif,
                    $payment->documentLabel(),
                    (string) $payment->payment_currency,
                    round((float) $payment->payment_amount, 2),
                    $payment->bcv_rate !== null ? round((float) $payment->bcv_rate, 4) : null,
                    round((float) $payment->amount_in_invoice_currency, 2),
                    (string) $payment->invoice_currency,
                    $payment->bank,
                    $payment->payment_reference,
                    $payment->isBatch() ? 'Masivo' : 'Individual',
                    self::statusOptions()[$payment->status] ?? (string) $payment->status,
                    $payment->registered_by_name,
                    $payment->created_at?->format('d/m/Y h:i A'),
                    $payment->reversed_by_name,
                    $payment->reversed_at?->format('d/m/Y h:i A'),
                    $payment->reversal_reason,
                ];
            }
        })();

        return new OperationReportData(
            title: $type->label(),
            headings: ['Fecha del pago', 'N.º orden', 'Proveedor', 'RIF', 'Factura / recibo', 'Moneda del pago', 'Monto pagado', 'Tasa BCV', 'Equivalente en la factura', 'Moneda de la factura', 'Banco', 'Referencia', 'Tipo', 'Estatus', 'Registrado por', 'Registrado el', 'Reversado por', 'Reversado el', 'Razón de la reversión'],
            rows: $rows,
            numericColumns: [6, 7, 8],
            criteria: self::criteria($filters),
            pdfColumns: self::PDF_COLUMNS,
            totalRows: $total,
            leadingSections: [self::summarySection($summary)],
            detailTitle: 'Detalle de pagos de contado',
        );
    }

    /**
     * Resumen por estatus y moneda del pago: una sola consulta agrupada.
     *
     * @return list<array{status: string, currency: string, count: int, paid: float, equivalent_usd: float, equivalent_ves: float, single: int, batch: int, batches: int}>
     */
    public static function summary(OperationReportFilters $filters): array
    {
        $grouped = self::query($filters)
            ->toBase()
            ->selectRaw('operation_cash_payments.status AS status_value, operation_cash_payments.payment_currency AS currency')
            ->selectRaw('COUNT(*) AS total_count')
            ->selectRaw('COALESCE(SUM(operation_cash_payments.payment_amount), 0) AS paid_sum')
            ->selectRaw("COALESCE(SUM(CASE WHEN operation_cash_payments.invoice_currency = 'USD' THEN operation_cash_payments.amount_in_invoice_currency END), 0) AS equivalent_usd_sum")
            ->selectRaw("COALESCE(SUM(CASE WHEN operation_cash_payments.invoice_currency = 'VES' THEN operation_cash_payments.amount_in_invoice_currency END), 0) AS equivalent_ves_sum")
            ->selectRaw('SUM(operation_cash_payments.payment_batch IS NULL) AS single_count')
            ->selectRaw('SUM(operation_cash_payments.payment_batch IS NOT NULL) AS batch_count')
            ->selectRaw('COUNT(DISTINCT operation_cash_payments.payment_batch) AS batches_count')
            ->groupBy('operation_cash_payments.status', 'operation_cash_payments.payment_currency')
            ->get();

        $summary = [];

        foreach (array_keys(self::statusOptions()) as $status) {
            if (! in_array($status, $filters->cashStatuses, true)) {
                continue;
            }

            $rows = $grouped->where('status_value', $status);

            if ($rows->isEmpty()) {
                $summary[] = ['status' => $status, 'currency' => '—', 'count' => 0, 'paid' => 0.0, 'equivalent_usd' => 0.0, 'equivalent_ves' => 0.0, 'single' => 0, 'batch' => 0, 'batches' => 0];

                continue;
            }

            foreach ($rows->sortBy('currency') as $row) {
                $summary[] = [
                    'status' => $status,
                    'currency' => (string) $row->currency,
                    'count' => (int) $row->total_count,
                    'paid' => round((float) $row->paid_sum, 2),
                    'equivalent_usd' => round((float) $row->equivalent_usd_sum, 2),
                    'equivalent_ves' => round((float) $row->equivalent_ves_sum, 2),
                    'single' => (int) $row->single_count,
                    'batch' => (int) $row->batch_count,
                    'batches' => (int) $row->batches_count,
                ];
            }
        }

        return $summary;
    }

    /**
     * @param  list<array{status: string, currency: string, count: int, paid: float, equivalent_usd: float, equivalent_ves: float, single: int, batch: int, batches: int}>  $summary
     * @return array{title: string, headings: list<string>, rows: list<list<scalar|null>>, totals: list<scalar|null>|null, numericColumns: list<int>}
     */
    private static function summarySection(array $summary): array
    {
        $rows = array_map(static fn (array $row): array => [
            self::statusOptions()[$row['status']] ?? $row['status'],
            $row['currency'] === '—' ? '—' : (self::currencyOptions()[$row['currency']] ?? $row['currency']),
            $row['count'],
            $row['paid'],
            $row['equivalent_usd'],
            $row['equivalent_ves'],
            $row['single'],
            $row['batch'],
            $row['batches'],
        ], $summary);

        /** El monto pagado solo se suma al pie cuando todos los pagos son en la misma moneda. */
        $currencies = array_values(array_unique(array_column(array_filter($summary, fn (array $row): bool => $row['count'] > 0), 'currency')));
        $sum = static fn (string $key): float|int => array_sum(array_column($summary, $key));

        return [
            'title' => 'Resumen por estatus',
            'headings' => ['Estatus', 'Moneda del pago', 'Registros', 'Monto pagado', 'Equivalente en facturas US$', 'Equivalente en facturas Bs.', 'Individuales', 'Partes de pagos masivos', 'Pagos masivos'],
            'rows' => $rows,
            'totals' => [
                'TOTAL',
                count($currencies) === 1 ? (self::currencyOptions()[$currencies[0]] ?? $currencies[0]) : 'Varias',
                $sum('count'),
                count($currencies) <= 1 ? round((float) $sum('paid'), 2) : '',
                round((float) $sum('equivalent_usd'), 2),
                round((float) $sum('equivalent_ves'), 2),
                $sum('single'),
                $sum('batch'),
                $sum('batches'),
            ],
            'numericColumns' => [2, 3, 4, 5, 6, 7, 8],
        ];
    }

    /**
     * @return list<string>
     */
    private static function criteria(OperationReportFilters $filters): array
    {
        $lines = [
            'Pagos hechos del '.$filters->from->format('d/m/Y').' al '.$filters->to->format('d/m/Y'),
            'Estatus: '.implode(', ', array_map(fn (string $status): string => self::statusOptions()[$status] ?? $status, $filters->cashStatuses)),
        ];

        if ($filters->cashKinds !== []) {
            $lines[] = 'Tipo: '.implode(', ', array_map(fn (string $kind): string => self::kindOptions()[$kind] ?? $kind, $filters->cashKinds));
        }

        if ($filters->cashCurrencies !== []) {
            $lines[] = 'Moneda del pago: '.implode(', ', array_map(fn (string $currency): string => self::currencyOptions()[$currency] ?? $currency, $filters->cashCurrencies));
        }

        if ($filters->cashSuppliers !== []) {
            $lines[] = 'Proveedor: '.implode(', ', $filters->cashSuppliers);
        }

        $lines[] = 'Un pago masivo (una factura de varias órdenes) aparece con una fila por orden y se reversa completo. Cada monto está en la moneda indicada en su fila.';

        return $lines;
    }
}
