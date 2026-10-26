<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Filament\Operations\Widgets\Dashboard\OperationsServiceTypeChart;

/**
 * Barras horizontales de una sola serie (un solo color: el largo da el valor).
 * Si la dimensión de detalle está vacía, las barras no son clicables.
 */
abstract class CorporateBarChart extends OperationsServiceTypeChart
{
    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 4];

    /**
     * @return array<string, int|float>
     */
    abstract protected function values(): array;

    /**
     * @return array<string, int|float>
     */
    protected function typeCounts(): array
    {
        return $this->values();
    }

    protected function detailDimension(): string
    {
        return '';
    }

    /**
     * Una sola serie: las barras salen de {@see self::values()}.
     */
    protected function typeBreakdown(): ?array
    {
        return null;
    }
}
