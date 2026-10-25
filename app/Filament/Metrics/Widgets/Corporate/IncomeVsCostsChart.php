<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporateFinanceMetrics;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Ingresos frente a costo médico en el tiempo (misma unidad, un solo eje).
 */
class IncomeVsCostsChart extends CorporateComparisonChart
{
    public function getHeading(): string|Htmlable|null
    {
        return 'Ingresos frente a costo médico';
    }

    protected function intro(): string
    {
        $notice = CorporateFinanceMetrics::costCoverageNotice($this->period());

        return 'Cuánto entra y cuánto cuesta atender.'.($notice !== null ? ' '.$notice : '');
    }

    protected function isMoney(): bool
    {
        return true;
    }

    protected function series(): array
    {
        $data = CorporateFinanceMetrics::incomeVsCosts($this->period());

        return ['labels' => $data['labels'], 'first' => $data['income'], 'second' => $data['costs']];
    }

    protected function names(): array
    {
        return ['Ingresos', 'Costo médico'];
    }

    protected function differenceLabel(): string
    {
        return 'Diferencia';
    }
}
