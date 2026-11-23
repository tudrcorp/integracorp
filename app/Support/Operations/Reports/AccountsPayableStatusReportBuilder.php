<?php

declare(strict_types=1);

namespace App\Support\Operations\Reports;

use App\Enums\OperationReportType;
use App\Enums\StatusCuentaPorPagar;
use App\Filament\Operations\Resources\OperationAccountsPayables\OperationAccountsPayableResource;
use App\Models\OperationAccountsPayable;
use App\Models\OperationCashPayment;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Filament\Operations\OperationsSupplierScope;
use Generator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reporte «Cuentas por pagar por estatus» del Generador de reportes de Operaciones.
 *
 * El analista elige qué estatus incluir. El periodo es la fecha de registro de la
 * factura (cuándo entró la deuda). Primero un resumen por estatus y moneda (facturado
 * y pagado) y luego el detalle, una cuenta por pagar por fila, indicando si se pagó de
 * contado (individual o masivo). Solo analistas internos con el permiso propio.
 */
final class AccountsPayableStatusReportBuilder
{
    /**
     * Columnas que caben en el PDF horizontal; Excel y CSV traen todas.
     *
     * @var list<int>
     */
    private const PDF_COLUMNS = [0, 1, 3, 5, 6, 7, 8, 9, 10, 12, 14];

    public static function allows(): bool
    {
        return OperationsSupplierScope::authenticatedUserIsTdgAnalyst()
            && OperationAccountsPayableResource::canViewAny()
            && BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::REPORT_ACCOUNTS_PAYABLE_BY_STATUS);
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        $options = [];

        foreach (StatusCuentaPorPagar::cases() as $status) {
            $options[$status->value] = $status->label();
        }

        return $options;
    }

    /**
     * Proveedores con cuentas por pagar (nombre congelado en la factura), para el filtro.
     *
     * @return array<string, string>
     */
    public static function supplierOptions(): array
    {
        return OperationAccountsPayable::query()
            ->whereNotNull('supplier_name')
            ->where('supplier_name', '<>', '')
            ->distinct()
            ->orderBy('supplier_name')
            ->limit(1000)
            ->pluck('supplier_name', 'supplier_name')
            ->all();
    }

    /**
     * @return Builder<OperationAccountsPayable>
     */
    public static function query(OperationReportFilters $filters): Builder
    {
        return OperationAccountsPayable::query()
            ->whereBetween('operation_accounts_payables.invoice_registration_date', [$filters->from->toDateString(), $filters->to->toDateString()])
            ->whereIn('operation_accounts_payables.payment_status', $filters->payableStatuses === [] ? ['__ninguno__'] : $filters->payableStatuses)
            ->when($filters->payableSuppliers !== [], fn (Builder $q): Builder => $q->whereIn('operation_accounts_payables.supplier_name', $filters->payableSuppliers));
    }

    public static function count(OperationReportFilters $filters): int
    {
        return self::query($filters)->count();
    }

    public static function build(OperationReportType $type, OperationReportFilters $filters, ?int $rowLimit = null): OperationReportData
    {
        $summary = self::summary($filters);
        $total = array_sum(array_column($summary, 'count'));

        $rowsQuery = self::query($filters)
            ->with(['operationServiceOrder:id,order_number', 'businessUnit'])
            ->addSelect([
                'cash_payment_batch' => OperationCashPayment::query()
                    ->select('payment_batch')
                    ->whereColumn('operation_accounts_payable_id', 'operation_accounts_payables.id')
                    ->where('status', OperationCashPayment::STATUS_REGISTERED)
                    ->limit(1),
                'cash_payment_id' => OperationCashPayment::query()
                    ->select('id')
                    ->whereColumn('operation_accounts_payable_id', 'operation_accounts_payables.id')
                    ->where('status', OperationCashPayment::STATUS_REGISTERED)
                    ->limit(1),
            ]);

        $rows = (static function () use ($rowsQuery, $rowLimit): Generator {
            $emitted = 0;

            foreach ($rowsQuery->lazyById(500, 'operation_accounts_payables.id', 'id') as $payable) {
                if ($rowLimit !== null && $emitted >= $rowLimit) {
                    return;
                }

                $emitted++;
                $status = $payable->payment_status instanceof StatusCuentaPorPagar
                    ? $payable->payment_status
                    : StatusCuentaPorPagar::fromStored($payable->payment_status);

                yield [
                    (string) ($payable->operationServiceOrder?->order_number ?: '—'),
                    (string) $payable->supplier_name,
                    $payable->supplier_rif,
                    self::documentLabel($payable),
                    $payable->invoice_control_number,
                    $payable->invoice_date?->format('d/m/Y'),
                    $payable->invoice_registration_date?->format('d/m/Y'),
                    round((float) $payable->invoice_amount, 2),
                    (string) $payable->invoice_currency,
                    $status?->label() ?? (string) $payable->getRawOriginal('payment_status'),
                    $payable->payment_date?->format('d/m/Y'),
                    $payable->payment_reference,
                    $payable->payment_amount_usd !== null ? round((float) $payable->payment_amount_usd, 2) : null,
                    $payable->payment_amount_ves !== null ? round((float) $payable->payment_amount_ves, 2) : null,
                    self::cashLabel($payable),
                    $payable->national_bank ?: $payable->international_bank,
                    $payable->businessUnit?->definition,
                ];
            }
        })();

        return new OperationReportData(
            title: $type->label(),
            headings: ['N.º orden', 'Proveedor', 'RIF', 'Factura / recibo', 'N.º control', 'Emisión', 'Registro', 'Monto facturado', 'Moneda', 'Estatus', 'Fecha de pago', 'Referencia', 'Pagado (US$)', 'Pagado (Bs.)', 'Pago de contado', 'Banco', 'Unidad de negocio'],
            rows: $rows,
            numericColumns: [7, 12, 13],
            criteria: self::criteria($filters),
            pdfColumns: self::PDF_COLUMNS,
            totalRows: $total,
            leadingSections: [self::summarySection($summary)],
            detailTitle: 'Detalle de cuentas por pagar',
        );
    }

    /**
     * Resumen por estatus y moneda de la factura: una sola consulta agrupada.
     *
     * @return list<array{status: string, currency: string, count: int, invoiced: float, paid_usd: float, paid_ves: float, cash: int}>
     */
    public static function summary(OperationReportFilters $filters): array
    {
        $grouped = self::query($filters)
            ->toBase()
            ->selectRaw('operation_accounts_payables.payment_status AS status_value, operation_accounts_payables.invoice_currency AS currency')
            ->selectRaw('COUNT(*) AS total_count')
            ->selectRaw('COALESCE(SUM(operation_accounts_payables.invoice_amount), 0) AS invoiced_sum')
            ->selectRaw('COALESCE(SUM(operation_accounts_payables.payment_amount_usd), 0) AS paid_usd_sum')
            ->selectRaw('COALESCE(SUM(operation_accounts_payables.payment_amount_ves), 0) AS paid_ves_sum')
            ->selectRaw('SUM(EXISTS (SELECT 1 FROM operation_cash_payments c WHERE c.operation_accounts_payable_id = operation_accounts_payables.id AND c.status = ?)) AS cash_count', [OperationCashPayment::STATUS_REGISTERED])
            ->groupBy('operation_accounts_payables.payment_status', 'operation_accounts_payables.invoice_currency')
            ->get();

        $summary = [];

        foreach (array_keys(self::statusOptions()) as $status) {
            if (! in_array($status, $filters->payableStatuses, true)) {
                continue;
            }

            $rows = $grouped->where('status_value', $status);

            if ($rows->isEmpty()) {
                $summary[] = ['status' => $status, 'currency' => 'USD', 'count' => 0, 'invoiced' => 0.0, 'paid_usd' => 0.0, 'paid_ves' => 0.0, 'cash' => 0];

                continue;
            }

            foreach ($rows->sortBy('currency') as $row) {
                $summary[] = [
                    'status' => $status,
                    'currency' => (string) ($row->currency ?: 'USD'),
                    'count' => (int) $row->total_count,
                    'invoiced' => round((float) $row->invoiced_sum, 2),
                    'paid_usd' => round((float) $row->paid_usd_sum, 2),
                    'paid_ves' => round((float) $row->paid_ves_sum, 2),
                    'cash' => (int) $row->cash_count,
                ];
            }
        }

        return $summary;
    }

    /**
     * @param  list<array{status: string, currency: string, count: int, invoiced: float, paid_usd: float, paid_ves: float, cash: int}>  $summary
     * @return array{title: string, headings: list<string>, rows: list<list<scalar|null>>, totals: list<scalar|null>|null, numericColumns: list<int>}
     */
    private static function summarySection(array $summary): array
    {
        $rows = array_map(static fn (array $row): array => [
            self::statusOptions()[$row['status']] ?? $row['status'],
            $row['currency'],
            $row['count'],
            $row['invoiced'],
            $row['paid_usd'],
            $row['paid_ves'],
            $row['cash'],
        ], $summary);

        /** Los montos solo se suman al pie cuando todo está en una sola moneda. */
        $currencies = array_values(array_unique(array_column(array_filter($summary, fn (array $row): bool => $row['count'] > 0), 'currency')));
        $sum = static fn (string $key): float|int => array_sum(array_column($summary, $key));

        return [
            'title' => 'Resumen por estatus',
            'headings' => ['Estatus', 'Moneda de la factura', 'Cuentas', 'Monto facturado', 'Pagado (US$)', 'Pagado (Bs.)', 'Pagadas de contado'],
            'rows' => $rows,
            'totals' => [
                'TOTAL',
                count($currencies) === 1 ? $currencies[0] : 'Varias',
                $sum('count'),
                count($currencies) <= 1 ? round((float) $sum('invoiced'), 2) : '',
                round((float) $sum('paid_usd'), 2),
                round((float) $sum('paid_ves'), 2),
                $sum('cash'),
            ],
            'numericColumns' => [2, 3, 4, 5, 6],
        ];
    }

    /**
     * @return list<string>
     */
    private static function criteria(OperationReportFilters $filters): array
    {
        $lines = [
            'Facturas registradas del '.$filters->from->format('d/m/Y').' al '.$filters->to->format('d/m/Y'),
            'Estatus: '.implode(', ', array_map(fn (string $status): string => self::statusOptions()[$status] ?? $status, $filters->payableStatuses)),
        ];

        if ($filters->payableSuppliers !== []) {
            $lines[] = 'Proveedor: '.implode(', ', $filters->payableSuppliers);
        }

        return $lines;
    }

    private static function documentLabel(OperationAccountsPayable $payable): ?string
    {
        $parts = array_filter([
            filled($payable->invoice_number) ? 'Factura '.$payable->invoice_number : null,
            filled($payable->invoice_receipt_number) ? 'Recibo '.$payable->invoice_receipt_number : null,
        ]);

        return $parts === [] ? null : implode(' · ', $parts);
    }

    private static function cashLabel(OperationAccountsPayable $payable): string
    {
        if (blank($payable->getAttribute('cash_payment_id'))) {
            return 'No';
        }

        return filled($payable->getAttribute('cash_payment_batch')) ? 'Sí (masivo)' : 'Sí';
    }
}
