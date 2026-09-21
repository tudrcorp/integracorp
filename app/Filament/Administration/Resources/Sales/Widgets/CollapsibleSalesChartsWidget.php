<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Sales\Widgets;

use App\Filament\Administration\Resources\Sales\Pages\ListSales;
use App\Filament\Administration\Resources\Sales\Widgets\Concerns\HasCollapsibleSalesChartsPanel;
use App\Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\Widget;

class CollapsibleSalesChartsWidget extends Widget
{
    use HasCollapsibleSalesChartsPanel;
    use InteractsWithPageTable;

    protected static bool $isLazy = false;

    protected string $view = 'filament.administration.widgets.sales-collapsible-charts-overview';

    /**
     * @var int | string | array<string, int | string | null>
     */
    protected int|string|array $columnSpan = 'full';

    protected function getTablePage(): string
    {
        return ListSales::class;
    }

    public function yearChartWidget(): string
    {
        return SaleYearChart::class;
    }

    public function planChartWidget(): string
    {
        return SalePlanChart::class;
    }
}
