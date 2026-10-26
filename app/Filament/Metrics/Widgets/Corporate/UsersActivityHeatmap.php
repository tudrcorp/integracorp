<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Filament\Operations\Widgets\Dashboard\OperationsWorkloadHeatmap;
use App\Support\Metrics\Corporate\CorporateUsersMetrics;

/**
 * Cuándo trabaja el personal: minutos activos por día de la semana y hora.
 */
class UsersActivityHeatmap extends OperationsWorkloadHeatmap
{
    protected function heatmapData(): array
    {
        return CorporateUsersMetrics::heatmap($this->period());
    }

    protected function texts(): array
    {
        return [
            'heading' => 'Cuándo se trabaja en el sistema',
            'description' => 'Minutos activos del personal según el día de la semana y la hora; la escala de abajo va de menos a más.',
            'noun' => 'minutos activos',
            'singular' => 'minuto activo',
            'emptyText' => 'No hay actividad registrada en este período.',
            'clickText' => 'Minutos activos del personal en este tramo',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [...parent::getViewData(), 'clickable' => false];
    }
}
