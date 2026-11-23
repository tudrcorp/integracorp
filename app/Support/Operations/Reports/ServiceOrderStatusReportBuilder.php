<?php

declare(strict_types=1);

namespace App\Support\Operations\Reports;

use App\Enums\OperationReportType;
use App\Filament\Operations\Resources\OperationServiceOrders\OperationServiceOrderResource;
use App\Models\OperationServiceOrder;
use App\Models\Supplier;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Filament\Operations\OperationsSupplierScope;
use App\Support\Operations\OperationServiceOrderListDisplay;
use App\Support\Operations\ServiceOrderBulkInvoice;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Reporte «Órdenes de servicio por estatus» del Generador de reportes de Operaciones.
 *
 * El analista elige qué estatus incluir. Primero un resumen por estatus (con el cruce
 * de «Documento del Aliado de Servicio», costo del servicio y precio de venta) y luego
 * el detalle, una orden por fila. Lleva costos y precios de TDG: solo analistas internos
 * con el permiso propio. El resumen es una consulta agrupada; el detalle se lee por
 * lotes con sus relaciones precargadas (sin N+1).
 */
final class ServiceOrderStatusReportBuilder
{
    public const STATUS_IN_PROGRESS = 'EN GESTION';

    public const STATUS_FINALIZED = 'FINALIZADO';

    public const STATUS_EXPIRED = 'CADUCADA';

    public const STATUS_CANCELLED = 'CANCELADA';

    /** Valor histórico que la tabla de órdenes ya trata como CANCELADA. */
    private const LEGACY_CANCELLED = 'CANCELADO';

    /**
     * Columnas que caben en el PDF horizontal; Excel y CSV traen todas.
     *
     * @var list<int>
     */
    private const PDF_COLUMNS = [0, 1, 2, 3, 4, 5, 6, 8, 9, 10, 11];

    public static function allows(): bool
    {
        return OperationsSupplierScope::authenticatedUserIsTdgAnalyst()
            && OperationServiceOrderResource::canViewAny()
            && BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::REPORT_SERVICE_ORDERS_BY_STATUS);
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_IN_PROGRESS => 'En gestión',
            self::STATUS_FINALIZED => 'Finalizado',
            self::STATUS_EXPIRED => 'Caducada',
            self::STATUS_CANCELLED => 'Cancelada',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function documentOptions(): array
    {
        return OperationServiceOrderListDisplay::administrativeStatusOptions();
    }

    /**
     * Proveedores con órdenes, para el filtro (catálogo corto).
     *
     * @return array<int, string>
     */
    public static function supplierOptions(): array
    {
        return Supplier::query()
            ->whereIn('id', OperationServiceOrder::query()->whereNotNull('supplier_id')->select('supplier_id'))
            ->orderBy('name')
            ->limit(1000)
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return Builder<OperationServiceOrder>
     */
    public static function query(OperationReportFilters $filters): Builder
    {
        $statuses = $filters->orderStatuses;

        if (in_array(self::STATUS_CANCELLED, $statuses, true)) {
            $statuses[] = self::LEGACY_CANCELLED;
        }

        $query = OperationServiceOrder::query()
            ->whereBetween('operation_service_orders.created_at', [$filters->from->startOfDay(), $filters->to->endOfDay()])
            ->whereIn('operation_service_orders.status', $statuses === [] ? ['__ninguno__'] : $statuses)
            ->when($filters->orderSuppliers !== [], fn (Builder $q): Builder => $q->whereIn('operation_service_orders.supplier_id', $filters->orderSuppliers));

        if ($filters->documentStatuses !== []) {
            $wantsPending = in_array(OperationServiceOrderListDisplay::ADMINISTRATIVE_STATUS_PENDING, $filters->documentStatuses, true);

            $query->where(function (Builder $q) use ($filters, $wantsPending): void {
                $q->whereIn('operation_service_orders.administrative_status', $filters->documentStatuses);

                /** Una orden sin estatus administrativo es «Pendiente por Registrar». */
                if ($wantsPending) {
                    $q->orWhereNull('operation_service_orders.administrative_status')
                        ->orWhere('operation_service_orders.administrative_status', '');
                }
            });
        }

        return $query;
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
            ->with([
                'supplier:id,name',
                'telemedicineSupplier:id,name',
                'doctorNurse:id,name',
                'approvedOperationQuote:id,operation_service_order_id,costo_dolares,subtotal,total',
                'operationServiceOrderQuotes:id,operation_service_order_id,total_amount_usd',
                'operationCoordinationService.telemedicinePatient',
            ]);

        $rows = (static function () use ($rowsQuery, $rowLimit): Generator {
            $emitted = 0;

            foreach ($rowsQuery->lazyById(500, 'operation_service_orders.id', 'id') as $order) {
                if ($rowLimit !== null && $emitted >= $rowLimit) {
                    return;
                }

                $emitted++;

                yield [
                    (string) ($order->order_number ?: '#'.$order->getKey()),
                    $order->created_at?->format('d/m/Y'),
                    self::statusOptions()[self::normalizedStatus($order->status)] ?? (string) $order->status,
                    OperationServiceOrderListDisplay::administrativeStatusLabel($order->administrative_status),
                    ServiceOrderBulkInvoice::supplierDisplayName($order),
                    filled($order->service_type) ? mb_strtoupper((string) $order->service_type) : null,
                    OperationServiceOrderListDisplay::patientFullName($order),
                    OperationServiceOrderListDisplay::patientDocumentLabel($order),
                    OperationServiceOrderListDisplay::serviceReferenceNumber($order),
                    OperationServiceOrderListDisplay::serviceCostUsd($order),
                    OperationServiceOrderListDisplay::quoteAmountUsd($order),
                    OperationServiceOrderListDisplay::hasInvoice($order) ? $order->invoiceDocumentLabel() : null,
                    $order->invoice_amount_usd !== null ? round((float) $order->invoice_amount_usd, 2) : null,
                    $order->invoice_date?->format('d/m/Y'),
                ];
            }
        })();

        return new OperationReportData(
            title: $type->label(),
            headings: ['N.º orden', 'Fecha', 'Estatus', 'Documento del aliado', 'Proveedor', 'Tipo de servicio', 'Paciente', 'Cédula', 'N.º referencia', 'Costo del servicio (US$)', 'Precio de venta (US$)', 'Factura', 'Monto facturado (US$)', 'Fecha factura'],
            rows: $rows,
            numericColumns: [9, 10, 12],
            criteria: self::criteria($filters),
            pdfColumns: self::PDF_COLUMNS,
            totalRows: $total,
            leadingSections: [self::summarySection($summary)],
            detailTitle: 'Detalle de órdenes',
        );
    }

    /**
     * Resumen por estatus: una sola consulta agrupada. Costo y precio de venta siguen
     * la misma regla que la pantalla ({@see OperationServiceOrderListDisplay::serviceCostUsd()}).
     *
     * @return list<array{status: string, count: int, pending: int, registered: int, cost: float, sale: float, margin: float, without_sale: int, invoiced: float}>
     */
    public static function summary(OperationReportFilters $filters): array
    {
        $quote = static fn (string $column): string => "(SELECT NULLIF(g.{$column}, 0) FROM operation_quote_generators g WHERE g.operation_service_order_id = operation_service_orders.id ORDER BY g.id LIMIT 1)";
        $supplierQuotes = '(SELECT SUM(q.total_amount_usd) FROM operation_service_order_quotes q WHERE q.operation_service_order_id = operation_service_orders.id)';
        $cost = 'COALESCE('.$quote('costo_dolares').', '.$quote('subtotal').', '.$supplierQuotes.', NULLIF(operation_service_orders.total_amount_usd, 0))';
        $sale = 'COALESCE('.$quote('total').', '.$quote('costo_dolares').', '.$supplierQuotes.')';
        $invoiced = OperationServiceOrderListDisplay::ADMINISTRATIVE_STATUS_INVOICED;

        $base = self::query($filters)
            ->toBase()
            ->selectRaw('CASE WHEN operation_service_orders.status = ? THEN ? ELSE UPPER(TRIM(operation_service_orders.status)) END AS status_group', [self::LEGACY_CANCELLED, self::STATUS_CANCELLED])
            ->selectRaw('CASE WHEN UPPER(TRIM(COALESCE(operation_service_orders.administrative_status, \'\'))) = ? THEN 1 ELSE 0 END AS is_registered', [$invoiced])
            ->selectRaw($cost.' AS cost_usd')
            ->selectRaw($sale.' AS sale_usd')
            ->selectRaw('COALESCE(operation_service_orders.invoice_amount_usd, 0) AS invoiced_usd');

        $grouped = DB::query()
            ->fromSub($base, 'orders')
            ->groupBy('status_group')
            ->selectRaw('status_group, COUNT(*) AS total_count, SUM(is_registered) AS registered_count')
            ->selectRaw('COALESCE(SUM(cost_usd), 0) AS cost_sum, COALESCE(SUM(sale_usd), 0) AS sale_sum, COALESCE(SUM(invoiced_usd), 0) AS invoiced_sum')
            /** La ganancia solo se mide en órdenes con costo y precio de venta: una orden directa no tiene precio de venta. */
            ->selectRaw('COALESCE(SUM(CASE WHEN sale_usd IS NOT NULL AND cost_usd IS NOT NULL THEN sale_usd - cost_usd END), 0) AS margin_sum')
            ->selectRaw('SUM(CASE WHEN sale_usd IS NULL THEN 1 ELSE 0 END) AS without_sale_count')
            ->get()
            ->keyBy('status_group');

        $summary = [];

        foreach (array_keys(self::statusOptions()) as $status) {
            if (! in_array($status, $filters->orderStatuses, true)) {
                continue;
            }

            $row = $grouped->get($status);
            $count = (int) ($row->total_count ?? 0);
            $registered = (int) ($row->registered_count ?? 0);

            $summary[] = [
                'status' => $status,
                'count' => $count,
                'pending' => $count - $registered,
                'registered' => $registered,
                'cost' => round((float) ($row->cost_sum ?? 0), 2),
                'sale' => round((float) ($row->sale_sum ?? 0), 2),
                'margin' => round((float) ($row->margin_sum ?? 0), 2),
                'without_sale' => (int) ($row->without_sale_count ?? 0),
                'invoiced' => round((float) ($row->invoiced_sum ?? 0), 2),
            ];
        }

        return $summary;
    }

    /**
     * @param  list<array{status: string, count: int, pending: int, registered: int, cost: float, sale: float, margin: float, without_sale: int, invoiced: float}>  $summary
     * @return array{title: string, headings: list<string>, rows: list<list<scalar|null>>, totals: list<scalar|null>, numericColumns: list<int>}
     */
    private static function summarySection(array $summary): array
    {
        $rows = array_map(static fn (array $row): array => [
            self::statusOptions()[$row['status']] ?? $row['status'],
            $row['count'],
            $row['pending'],
            $row['registered'],
            $row['cost'],
            $row['sale'],
            $row['margin'],
            $row['without_sale'],
            $row['invoiced'],
        ], $summary);

        $sum = static fn (string $key): float|int => array_sum(array_column($summary, $key));

        return [
            'title' => 'Resumen por estatus',
            'headings' => ['Estatus', 'Órdenes', 'Pendiente por Registrar', 'Registrado', 'Costo del servicio (US$)', 'Precio de venta (US$)', 'Ganancia (US$)', 'Sin precio de venta', 'Facturado por el aliado (US$)'],
            'rows' => $rows,
            'totals' => ['TOTAL', $sum('count'), $sum('pending'), $sum('registered'), round((float) $sum('cost'), 2), round((float) $sum('sale'), 2), round((float) $sum('margin'), 2), $sum('without_sale'), round((float) $sum('invoiced'), 2)],
            'numericColumns' => [1, 2, 3, 4, 5, 6, 7, 8],
        ];
    }

    /**
     * @return list<string>
     */
    private static function criteria(OperationReportFilters $filters): array
    {
        $lines = [
            'Órdenes creadas del '.$filters->from->format('d/m/Y').' al '.$filters->to->format('d/m/Y'),
            'Estatus: '.implode(', ', array_map(fn (string $status): string => self::statusOptions()[$status] ?? $status, $filters->orderStatuses)),
        ];

        if ($filters->documentStatuses !== []) {
            $lines[] = 'Documento del aliado: '.implode(', ', array_map(fn (string $status): string => self::documentOptions()[$status] ?? $status, $filters->documentStatuses));
        }

        if ($filters->orderSuppliers !== []) {
            $names = Supplier::query()->whereIn('id', $filters->orderSuppliers)->orderBy('name')->pluck('name')->all();
            $lines[] = 'Proveedor: '.implode(', ', $names);
        }

        $lines[] = 'Costo del servicio: lo que factura el aliado (sin ganancia). Precio de venta: total de la cotización aprobada. Ganancia: solo de las órdenes con costo y precio de venta; las órdenes directas sin cotización no tienen precio de venta.';

        return $lines;
    }

    private static function normalizedStatus(mixed $status): string
    {
        $status = mb_strtoupper(trim((string) $status));

        return $status === self::LEGACY_CANCELLED ? self::STATUS_CANCELLED : $status;
    }
}
