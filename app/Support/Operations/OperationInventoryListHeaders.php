<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationInventory;
use App\Models\OperationInventoryEntry;
use App\Models\OperationInventoryMovement;
use App\Models\OperationInventoryOutflow;
use App\Models\OperationInventoryProduct;
use App\Models\OperationInventoryProductCategory;
use App\Models\OperationInventorySetting;
use App\Models\OperationInventoryUbication;
use Illuminate\Support\HtmlString;

/**
 * Encabezados de los listados de «Inventario Diagnomóvil» en Operaciones.
 *
 * Mismo diseño que Pacientes, Afiliados o Casos (`filament.operations.partials.list-header`):
 * ícono, título con total y chips de resumen calculados en una sola consulta agregada.
 * Los conteos no aplican los filtros que el usuario elija en la tabla.
 */
final class OperationInventoryListHeaders
{
    private const EYEBROW = 'Inventario Diagnomóvil';

    /**
     * Regla de stock bajo del Inventario General: la misma de la columna de existencia
     * en OperationInventoriesTable (mínimo del registro o 5 si no tiene).
     */
    private const INVENTORY_LOW_STOCK_SQL = 'operation_inventories.existence <= COALESCE(NULLIF(operation_inventories.min_stock, 0), 5)';

    private const PRODUCT_STOCK_SQL = 'COALESCE((SELECT SUM(s.existence) FROM operation_inventory_product_stocks AS s WHERE s.operation_inventory_product_id = operation_inventory_products.id), 0)';

    public static function warehouses(): HtmlString
    {
        $summary = OperationsListHeaderCounts::aggregate(OperationInventoryUbication::query(), [
            'active' => ['operation_inventory_ubications.is_active = 1'],
            'inactive' => ['COALESCE(operation_inventory_ubications.is_active, 0) = 0'],
            'with_stock' => ['EXISTS (SELECT 1 FROM operation_inventory_product_stocks AS s WHERE s.operation_inventory_ubication_id = operation_inventory_ubications.id AND s.existence > 0)'],
        ]);

        return self::render([
            'icon' => 'heroicon-o-building-storefront',
            'title' => 'Almacenes',
            'total' => $summary['total'],
            'totalHint' => 'Almacenes registrados',
            'description' => 'Lugares físicos donde se guarda el inventario. Cada entrada, salida y despacho de telemedicina descuenta o suma en un almacén.',
            'stats' => [
                ['label' => 'Activos', 'value' => $summary['active'], 'icon' => 'heroicon-m-check-circle', 'tone' => 'success', 'hint' => 'Almacenes disponibles para mover inventario'],
                ['label' => 'Con existencia', 'value' => $summary['with_stock'], 'icon' => 'heroicon-m-cube', 'tone' => 'info', 'hint' => 'Almacenes con al menos un producto en existencia'],
                ['label' => 'Inactivos', 'value' => $summary['inactive'], 'icon' => 'heroicon-m-pause-circle', 'tone' => 'gray', 'hint' => 'Almacenes desactivados'],
            ],
        ]);
    }

    public static function categories(): HtmlString
    {
        $summary = OperationsListHeaderCounts::aggregate(OperationInventoryProductCategory::query(), [
            'active' => ['operation_inventory_product_categories.is_active = 1'],
            'inactive' => ['COALESCE(operation_inventory_product_categories.is_active, 0) = 0'],
            'empty' => ['NOT EXISTS (SELECT 1 FROM operation_inventory_products AS p WHERE p.operation_inventory_product_category_id = operation_inventory_product_categories.id)'],
        ]);

        return self::render([
            'icon' => 'heroicon-o-tag',
            'title' => 'Categorías',
            'total' => $summary['total'],
            'totalHint' => 'Categorías de productos registradas',
            'description' => 'Agrupan los productos del inventario (medicamentos, insumos, equipos) para buscarlos y reportarlos.',
            'stats' => [
                ['label' => 'Activas', 'value' => $summary['active'], 'icon' => 'heroicon-m-check-circle', 'tone' => 'success', 'hint' => 'Categorías disponibles al crear productos'],
                ['label' => 'Sin productos', 'value' => $summary['empty'], 'icon' => 'heroicon-m-inbox', 'tone' => 'warning', 'hint' => 'Categorías que todavía no tienen productos'],
                ['label' => 'Inactivas', 'value' => $summary['inactive'], 'icon' => 'heroicon-m-pause-circle', 'tone' => 'gray', 'hint' => 'Categorías desactivadas'],
            ],
        ]);
    }

    public static function products(): HtmlString
    {
        $threshold = OperationInventorySetting::current()->lowStockThreshold();

        $summary = OperationsListHeaderCounts::aggregate(OperationInventoryProduct::query(), [
            'active' => ['operation_inventory_products.is_active = 1'],
            'low_stock' => ['operation_inventory_products.is_active = 1 AND '.self::PRODUCT_STOCK_SQL.' BETWEEN 1 AND ?', [$threshold]],
            'out_of_stock' => ['operation_inventory_products.is_active = 1 AND '.self::PRODUCT_STOCK_SQL.' <= 0'],
            'inactive' => ['COALESCE(operation_inventory_products.is_active, 0) = 0'],
        ]);

        return self::render([
            'icon' => 'heroicon-o-cube',
            'title' => 'Productos',
            'total' => $summary['total'],
            'totalHint' => 'Productos del catálogo',
            'description' => 'Catálogo de medicamentos e insumos. La existencia es la suma de todos los almacenes; el umbral de stock bajo ('.$threshold.') se ajusta en Parámetros de Inventario.',
            'stats' => [
                ['label' => 'Activos', 'value' => $summary['active'], 'icon' => 'heroicon-m-check-circle', 'tone' => 'success', 'hint' => 'Productos disponibles para despacho'],
                ['label' => 'Stock bajo', 'value' => $summary['low_stock'], 'icon' => 'heroicon-m-exclamation-triangle', 'tone' => 'warning', 'hint' => 'Productos activos con existencia total entre 1 y '.$threshold],
                ['label' => 'Sin existencia', 'value' => $summary['out_of_stock'], 'icon' => 'heroicon-m-no-symbol', 'tone' => 'danger', 'hint' => 'Productos activos sin unidades en ningún almacén'],
                ['label' => 'Inactivos', 'value' => $summary['inactive'], 'icon' => 'heroicon-m-pause-circle', 'tone' => 'gray', 'hint' => 'Productos desactivados'],
            ],
        ]);
    }

    public static function inventory(): HtmlString
    {
        $summary = OperationsListHeaderCounts::aggregate(OperationInventory::query(), [
            'low_stock' => [self::INVENTORY_LOW_STOCK_SQL.' AND operation_inventories.existence > 0'],
            'out_of_stock' => ['operation_inventories.existence <= 0'],
        ], [
            'warehouses' => 'operation_inventories.operation_inventory_ubication_id',
        ]);

        return self::render([
            'icon' => 'heroicon-o-square-3-stack-3d',
            'title' => 'Inventario general',
            'total' => $summary['total'],
            'totalHint' => 'Registros de producto por almacén',
            'description' => 'Existencia de cada producto o medicamento en cada almacén. Se marca en rojo cuando llega a su mínimo (o a 5 si no tiene mínimo).',
            'stats' => [
                ['label' => 'Stock bajo', 'value' => $summary['low_stock'], 'icon' => 'heroicon-m-exclamation-triangle', 'tone' => 'warning', 'hint' => 'Registros en su mínimo o por debajo, con existencia'],
                ['label' => 'Sin existencia', 'value' => $summary['out_of_stock'], 'icon' => 'heroicon-m-no-symbol', 'tone' => 'danger', 'hint' => 'Registros agotados'],
                ['label' => 'Almacenes', 'value' => $summary['warehouses'], 'icon' => 'heroicon-m-building-storefront', 'tone' => 'primary', 'hint' => 'Almacenes con registros de inventario'],
            ],
        ]);
    }

    public static function movements(): HtmlString
    {
        $summary = OperationsListHeaderCounts::aggregate(OperationInventoryMovement::query(), [
            'today' => ['operation_inventory_movements.created_at >= ?', [OperationsListHeaderCounts::startOfToday()]],
            'month' => ['operation_inventory_movements.created_at >= ?', [OperationsListHeaderCounts::startOfMonth()]],
            'medications' => ['operation_inventory_movements.type = ?', ['SALIDA TELEMEDICINA']],
            'supplies' => ['operation_inventory_movements.type = ?', ['SALIDA INSUMOS TELEMEDICINA']],
        ], [
            'cases' => 'operation_inventory_movements.telemedicine_case_id',
        ]);

        return self::render([
            'icon' => 'heroicon-o-adjustments-horizontal',
            'title' => 'Movimientos de inventario',
            'total' => $summary['total'],
            'totalHint' => 'Movimientos registrados',
            'description' => 'Despachos y movimientos vinculados a telemedicina, pacientes y unidades de negocio.',
            'stats' => [
                ['label' => 'Hoy', 'value' => $summary['today'], 'icon' => 'heroicon-m-clock', 'tone' => 'primary', 'hint' => 'Movimientos registrados hoy'],
                ['label' => 'Este mes', 'value' => $summary['month'], 'icon' => 'heroicon-m-calendar-days', 'tone' => 'info', 'hint' => 'Movimientos del mes en curso'],
                ['label' => 'Medicamentos', 'value' => $summary['medications'], 'icon' => 'heroicon-m-beaker', 'tone' => 'success', 'hint' => 'Despachos de medicamentos por telemedicina'],
                ['label' => 'Insumos', 'value' => $summary['supplies'], 'icon' => 'heroicon-m-archive-box', 'tone' => 'success', 'hint' => 'Despachos de insumos por telemedicina'],
                ['label' => 'Casos atendidos', 'value' => $summary['cases'], 'icon' => 'heroicon-m-clipboard-document-list', 'tone' => 'primary', 'hint' => 'Casos de telemedicina distintos con movimientos'],
            ],
        ]);
    }

    public static function entries(): HtmlString
    {
        $summary = OperationsListHeaderCounts::aggregate(OperationInventoryEntry::query(), [
            'today' => ['operation_inventory_entries.created_at >= ?', [OperationsListHeaderCounts::startOfToday()]],
            'month' => ['operation_inventory_entries.created_at >= ?', [OperationsListHeaderCounts::startOfMonth()]],
            'first_load' => ['operation_inventory_entries.type_entry = ?', ['PRIMERA CARGA']],
            'restock' => ['operation_inventory_entries.type_entry = ?', ['REPOSICIÓN DE INVENTARIO']],
        ]);

        return self::render([
            'icon' => 'heroicon-o-truck',
            'title' => 'Entradas de inventario',
            'total' => $summary['total'],
            'totalHint' => 'Entradas registradas',
            'description' => 'Cada carga de unidades a un almacén: la primera carga de un producto y sus reposiciones.',
            'stats' => [
                ['label' => 'Hoy', 'value' => $summary['today'], 'icon' => 'heroicon-m-clock', 'tone' => 'primary', 'hint' => 'Entradas registradas hoy'],
                ['label' => 'Este mes', 'value' => $summary['month'], 'icon' => 'heroicon-m-calendar-days', 'tone' => 'info', 'hint' => 'Entradas del mes en curso'],
                ['label' => 'Primera carga', 'value' => $summary['first_load'], 'icon' => 'heroicon-m-sparkles', 'tone' => 'success', 'hint' => 'Primeras cargas de producto'],
                ['label' => 'Reposiciones', 'value' => $summary['restock'], 'icon' => 'heroicon-m-arrow-path', 'tone' => 'success', 'hint' => 'Reposiciones de inventario'],
            ],
        ]);
    }

    public static function outflows(): HtmlString
    {
        $summary = OperationsListHeaderCounts::aggregate(OperationInventoryOutflow::query(), [
            'today' => ['operation_inventory_outflows.created_at >= ?', [OperationsListHeaderCounts::startOfToday()]],
            'month' => ['operation_inventory_outflows.created_at >= ?', [OperationsListHeaderCounts::startOfMonth()]],
            'telemedicine' => ['operation_inventory_outflows.type_entry LIKE ?', ['SALIDA%']],
            'adjustments' => ['operation_inventory_outflows.type_entry LIKE ?', ['AJUSTE%']],
        ]);

        return self::render([
            'icon' => 'heroicon-o-arrow-left-start-on-rectangle',
            'title' => 'Salidas de inventario',
            'total' => $summary['total'],
            'totalHint' => 'Salidas registradas',
            'description' => 'Salidas, ajustes y despachos de telemedicina por producto y almacén.',
            'stats' => [
                ['label' => 'Hoy', 'value' => $summary['today'], 'icon' => 'heroicon-m-clock', 'tone' => 'primary', 'hint' => 'Salidas registradas hoy'],
                ['label' => 'Este mes', 'value' => $summary['month'], 'icon' => 'heroicon-m-calendar-days', 'tone' => 'info', 'hint' => 'Salidas del mes en curso'],
                ['label' => 'Telemedicina', 'value' => $summary['telemedicine'], 'icon' => 'heroicon-m-heart', 'tone' => 'success', 'hint' => 'Despachos de medicamentos e insumos por telemedicina'],
                ['label' => 'Ajustes', 'value' => $summary['adjustments'], 'icon' => 'heroicon-m-wrench-screwdriver', 'tone' => 'warning', 'hint' => 'Ajustes de existencia o de inventario'],
            ],
        ]);
    }

    public static function parameters(): HtmlString
    {
        return self::render([
            'icon' => 'heroicon-o-adjustments-horizontal',
            'title' => 'Parámetros de inventario',
            'description' => 'Reglas que usa el inventario, como el umbral de stock bajo que dispara la alerta diaria.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function render(array $data): HtmlString
    {
        return new HtmlString(view('filament.operations.partials.list-header', [
            'eyebrow' => self::EYEBROW,
            ...$data,
        ])->render());
    }
}
