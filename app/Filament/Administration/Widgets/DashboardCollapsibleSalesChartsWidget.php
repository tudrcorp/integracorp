<?php

declare(strict_types=1);

namespace App\Filament\Administration\Widgets;

use App\Filament\Administration\Resources\Sales\Widgets\Concerns\HasCollapsibleSalesChartsPanel;
use Filament\Widgets\Widget;

class DashboardCollapsibleSalesChartsWidget extends Widget
{
    use HasCollapsibleSalesChartsPanel;

    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    protected string $view = 'filament.administration.widgets.sales-collapsible-charts-overview';

    /**
     * @var int | string | array<string, int | string | null>
     */
    protected int|string|array $columnSpan = 'full';

    public function yearChartWidget(): string
    {
        return DashboardSaleYearChart::class;
    }

    public function planChartWidget(): string
    {
        return DashboardSalePlanChart::class;
    }
}
