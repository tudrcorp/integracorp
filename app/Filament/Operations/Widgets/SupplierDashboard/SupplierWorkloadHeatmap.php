<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\SupplierDashboard;

use App\Filament\Operations\Widgets\Dashboard\OperationsWorkloadHeatmap;
use App\Support\Operations\Dashboard\SupplierDashboardAnalytics;

/**
 * Cuándo registra consultas el proveedor: día de la semana × hora.
 */
class SupplierWorkloadHeatmap extends OperationsWorkloadHeatmap
{
    protected static ?int $sort = 8;

    protected function heatmapData(): array
    {
        return SupplierDashboardAnalytics::heatmap($this->period());
    }

    protected function texts(): array
    {
        return [
            'heading' => 'Consultas por día y hora',
            'description' => 'Cuándo se registran las consultas del proveedor; la escala de abajo va de menos a más. Haga clic en una celda para ver esos casos.',
            'noun' => 'consultas',
            'singular' => 'consulta',
            'emptyText' => 'No hay consultas en este período.',
            'clickText' => 'Clic para ver los casos de este tramo',
        ];
    }
}
