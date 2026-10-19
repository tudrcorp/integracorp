<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationServiceOrder;
use App\Models\Supplier;
use App\Support\Filament\Operations\OperationsSupplierScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Grupos de órdenes de servicio de un proveedor (el que ejecuta, `supplier_id`).
 *
 * El encabezado de la ficha, su pestaña y la pantalla de órdenes cuentan con
 * estas mismas reglas:
 *  - En gestión: abiertas (hoy toda orden nace EN GESTION).
 *  - Pendientes de facturar: finalizadas sin factura del proveedor.
 *  - Finalizadas: todas las FINALIZADO.
 *  - Cerradas sin finalizar: canceladas o caducadas.
 */
final class SupplierServiceOrderBuckets
{
    public const IN_PROGRESS = 'en-gestion';

    public const PENDING_INVOICE = 'pendientes';

    public const FINALIZED = 'finalizadas';

    public const CLOSED_UNFINISHED = 'cerradas';

    public const FINALIZED_STATUS = 'FINALIZADO';

    /** CANCELADO es heredado: el sistema guarda CANCELADA. */
    public const CLOSED_UNFINISHED_STATUSES = ['CANCELADA', 'CANCELADO', OperationServiceOrderValidity::STATUS_EXPIRED];

    /**
     * Orden de las pestañas y su rótulo.
     *
     * @return array<string, array{label: string, description: string, icon: string, color: string}>
     */
    public static function definitions(): array
    {
        return [
            self::IN_PROGRESS => [
                'label' => 'En gestión',
                'description' => 'Órdenes abiertas. Puede finalizarlas cargando el documento.',
                'icon' => 'heroicon-o-arrow-path',
                'color' => 'info',
            ],
            self::PENDING_INVOICE => [
                'label' => 'Pendientes',
                'description' => 'Finalizadas que aún no tienen la factura del proveedor.',
                'icon' => 'heroicon-o-receipt-percent',
                'color' => 'warning',
            ],
            self::FINALIZED => [
                'label' => 'Finalizadas',
                'description' => 'Todas las órdenes finalizadas, con o sin factura.',
                'icon' => 'heroicon-o-check-badge',
                'color' => 'success',
            ],
            self::CLOSED_UNFINISHED => [
                'label' => 'Canceladas o caducadas',
                'description' => 'Órdenes que se cerraron sin finalizar el servicio.',
                'icon' => 'heroicon-o-x-circle',
                'color' => 'gray',
            ],
        ];
    }

    public static function isValid(?string $bucket): bool
    {
        return $bucket !== null && array_key_exists($bucket, self::definitions());
    }

    /**
     * Órdenes del proveedor visibles para el usuario actual.
     *
     * @return Builder<OperationServiceOrder>
     */
    public static function baseQuery(Supplier|int $supplier): Builder
    {
        $supplierId = $supplier instanceof Supplier ? (int) $supplier->getKey() : $supplier;

        $query = OperationServiceOrder::query()->where('supplier_id', $supplierId);

        // Un usuario de proveedor o de ATENMEDI solo ve lo suyo, igual que en la tabla de órdenes.
        OperationsSupplierScope::applyServiceOrderListScope($query);

        return $query;
    }

    /**
     * @param  Builder<OperationServiceOrder>  $query
     * @return Builder<OperationServiceOrder>
     */
    public static function apply(Builder $query, string $bucket): Builder
    {
        return match ($bucket) {
            self::IN_PROGRESS => $query->where(fn (Builder $inner): Builder => $inner
                ->whereNotIn('status', OperationServiceOrderValidity::closedStatuses())
                ->orWhereNull('status')),
            self::PENDING_INVOICE => $query->where('status', self::FINALIZED_STATUS)
                ->where(fn (Builder $inner): Builder => $inner
                    ->whereNull('administrative_status')
                    ->orWhere('administrative_status', '!=', OperationServiceOrderListDisplay::ADMINISTRATIVE_STATUS_INVOICED)),
            self::FINALIZED => $query->where('status', self::FINALIZED_STATUS),
            self::CLOSED_UNFINISHED => $query->whereIn('status', self::CLOSED_UNFINISHED_STATUSES),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * Cantidad por grupo en una sola consulta.
     *
     * @return array<string, int>
     */
    public static function counts(Supplier|int $supplier): array
    {
        $closed = OperationServiceOrderValidity::closedStatuses();
        $finalized = self::FINALIZED_STATUS;
        $invoiced = OperationServiceOrderListDisplay::ADMINISTRATIVE_STATUS_INVOICED;
        $closedUnfinished = self::CLOSED_UNFINISHED_STATUSES;

        $placeholders = static fn (array $values): string => implode(',', array_fill(0, count($values), '?'));

        $row = self::baseQuery($supplier)
            ->toBase()
            ->selectRaw('SUM(CASE WHEN status NOT IN ('.$placeholders($closed).') OR status IS NULL THEN 1 ELSE 0 END) AS in_progress', $closed)
            ->selectRaw('SUM(CASE WHEN status = ? AND (administrative_status IS NULL OR administrative_status <> ?) THEN 1 ELSE 0 END) AS pending_invoice', [$finalized, $invoiced])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS finalized', [$finalized])
            ->selectRaw('SUM(CASE WHEN status IN ('.$placeholders($closedUnfinished).') THEN 1 ELSE 0 END) AS closed_unfinished', $closedUnfinished)
            ->first();

        return [
            self::IN_PROGRESS => (int) ($row->in_progress ?? 0),
            self::PENDING_INVOICE => (int) ($row->pending_invoice ?? 0),
            self::FINALIZED => (int) ($row->finalized ?? 0),
            self::CLOSED_UNFINISHED => (int) ($row->closed_unfinished ?? 0),
        ];
    }
}
