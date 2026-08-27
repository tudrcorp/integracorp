<?php

declare(strict_types=1);

namespace App\Filament\Administration\Widgets;

use App\Filament\Administration\Resources\Sales\Widgets\SaleYearChart;

class DashboardSaleYearChart extends SaleYearChart
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 1;

    /**
     * @var int | string | array<string, int | string | null>
     */
    protected int|string|array $columnSpan = [
        'default' => 'full',
        'lg' => 1,
    ];
}
