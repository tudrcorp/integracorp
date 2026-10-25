<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporateFinanceMetrics;
use Illuminate\Contracts\Support\Htmlable;

class IncomeByTypeChart extends CorporateDonutChart
{
    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 4];

    public function getHeading(): string|Htmlable|null
    {
        return 'De dónde vienen los ingresos';
    }

    public function getDescription(): string|Htmlable|null
    {
        return 'Ingresos del período por tipo de venta. Haga clic para ver los pagos.';
    }

    protected function values(): array
    {
        return CorporateFinanceMetrics::incomeByType($this->period());
    }

    public function noun(): string
    {
        return 'ingresos';
    }

    public function isMoney(): bool
    {
        return true;
    }

    public function detailDimension(): string
    {
        return 'sale_type';
    }

    protected function colorRoles(): array
    {
        $roles = ['series1', 'series2', 'violet'];
        $out = [];

        foreach (array_keys($this->values()) as $index => $label) {
            $out[(string) $label] = $roles[$index] ?? 'muted';
        }

        return $out;
    }
}
