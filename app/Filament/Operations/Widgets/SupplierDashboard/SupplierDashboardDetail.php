<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\SupplierDashboard;

use App\Filament\Operations\Widgets\Dashboard\OperationsDashboardDetail;
use App\Support\Operations\Dashboard\SupplierDashboardAnalytics;

/**
 * Detalle del dashboard del proveedor: lista los casos del corte, siempre del
 * proveedor en sesión ({@see SupplierDashboardAnalytics}).
 */
class SupplierDashboardDetail extends OperationsDashboardDetail
{
    protected function allowedDimensions(): array
    {
        return SupplierDashboardAnalytics::dimensions();
    }

    protected function fetchDetail(string $dimension, string $value): array
    {
        return SupplierDashboardAnalytics::detail($this->period(), $dimension, $value, self::LIMIT);
    }

    protected function dimensionLabel(string $dimension, string $value): string
    {
        return SupplierDashboardAnalytics::dimensionLabel($dimension, $value);
    }

    protected function noun(): string
    {
        return 'casos';
    }

    protected function columns(): array
    {
        return [
            ['label' => 'Apertura', 'key' => 'date'],
            ['label' => 'Caso', 'key' => 'case_code'],
            ['label' => 'Paciente', 'key' => 'patient'],
            ['label' => 'Estado', 'key' => 'status'],
            ['label' => 'Prioridad', 'key' => 'priority'],
            ['label' => 'Médico asignado', 'key' => 'doctor'],
            ['label' => 'Consultas', 'key' => 'consultations'],
        ];
    }
}
