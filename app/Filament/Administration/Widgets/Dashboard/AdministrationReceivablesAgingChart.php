<?php

declare(strict_types=1);

namespace App\Filament\Administration\Widgets\Dashboard;

use App\Filament\Metrics\Widgets\Corporate\ReceivablesAgingChart;

class AdministrationReceivablesAgingChart extends ReceivablesAgingChart
{
    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 4];
}
