<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Filament\Operations\Widgets\Dashboard\OperationsServicesTrendChart;
use App\Support\Operations\Dashboard\OperationsDashboardPeriod;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Una cifra del panel corporativo en el tiempo, con el período anterior
 * superpuesto (misma gráfica que la evolución de servicios de Operaciones).
 */
abstract class CorporateTrendChart extends OperationsServicesTrendChart
{
    /**
     * Qué se grafica, para la descripción («Ingresos cobrados»).
     */
    abstract protected function subject(): string;

    public function getDescription(): string|Htmlable|null
    {
        $period = $this->period();
        $unit = match ($period->granularity()) {
            OperationsDashboardPeriod::GRANULARITY_WEEK => 'por semana',
            OperationsDashboardPeriod::GRANULARITY_MONTH => 'por mes',
            default => 'por día',
        };

        return $this->subject().' '.$unit.' · '.$period->label()
            .($period->compare ? ' frente a '.$period->previousLabel() : '');
    }
}
