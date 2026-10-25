<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Filament\Operations\Widgets\Dashboard\OperationsServicesStatusChart;

/**
 * Anillo con total al centro y leyenda con valor y porcentaje (la categoría
 * nunca se lee solo por color).
 */
abstract class CorporateDonutChart extends OperationsServicesStatusChart
{
    /**
     * @return array<string, int|float>
     */
    abstract protected function values(): array;

    /**
     * @return array<string, int|float>
     */
    protected function statusCounts(): array
    {
        return $this->values();
    }

    public function detailDimension(): string
    {
        return '';
    }
}
