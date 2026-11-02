<?php

declare(strict_types=1);

namespace App\Support\Operations\Reports;

use App\Filament\Operations\Resources\OperationInventoryProducts\OperationInventoryProductResource;
use App\Support\Filament\Operations\OperationsSupplierScope;

/**
 * Quién puede generar reportes de inventario: el inventario es propio de TDG
 * (Diagnomóvil), así que solo analistas internos —nunca un usuario de
 * proveedor o del portal AMD— y con el mismo permiso que abre «Productos».
 *
 * Se resuelve con el usuario autenticado; el job de cola se autentica como
 * quien pidió el reporte, así que la regla vale igual en ambos caminos.
 */
final class OperationInventoryReportAccess
{
    public static function allows(): bool
    {
        return OperationsSupplierScope::authenticatedUserIsTdgAnalyst()
            && OperationInventoryProductResource::canAccess();
    }
}
