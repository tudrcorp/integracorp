<?php

namespace App\Filament\Business\Widgets;

use App\Filament\Business\Resources\Agencies\Widgets\TotalSaleForEstructureChart;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class SalesForAgency extends ChartWidget
{
    protected static ?int $sort = 5;

    protected ?string $heading = 'Ventas por Agencia';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '350px';

    protected ?string $description = 'Ventas por agencia. Selecciona el año para ver el movimiento.';

    public ?string $filter = null;

    public function mount(): void
    {
        $this->filter = $this->filter ?? (string) Carbon::now()->year;
    }

    protected function getFilters(): ?array
    {
        $now = Carbon::now();
        $filters = [];
        for ($i = 0; $i < 3; $i++) {
            $y = $now->year - $i;
            $filters[(string) $y] = (string) $y;
        }

        return $filters;
    }

    protected function getData(): array
    {
        return TotalSaleForEstructureChart::buildChartData([
            'filter' => 'year',
            'year' => (int) ($this->filter ?? Carbon::now()->year),
        ]);
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
