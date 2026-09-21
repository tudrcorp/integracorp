<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\SupplierDashboard;

use App\Support\Operations\SupplierDashboardMetrics;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

/**
 * Cabecera del Dashboard del Proveedor: identifica la casa a la que pertenece
 * el espacio de trabajo antes de mostrar cualquier cifra.
 */
class SupplierIdentityHeader extends Widget
{
    protected static bool $isDiscovered = false;

    protected string $view = 'filament.operations.widgets.supplier-dashboard-identity';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -1;

    /**
     * @return array{
     *     supplierName: string,
     *     analystName: string,
     *     date: string,
     *     totalCases: int
     * }
     */
    protected function getViewData(): array
    {
        $user = Filament::auth()->user();

        return [
            'supplierName' => SupplierDashboardMetrics::supplierName() ?? 'Proveedor aliado',
            'analystName' => $user !== null ? Filament::getUserName($user) : '',
            'date' => ucfirst(Carbon::now()->locale('es')->translatedFormat('l, d \d\e F \d\e Y')),
            'totalCases' => SupplierDashboardMetrics::totalCasesCount(),
        ];
    }
}
