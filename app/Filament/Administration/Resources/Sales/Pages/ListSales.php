<?php

namespace App\Filament\Administration\Resources\Sales\Pages;

use App\Filament\Administration\Resources\Agencies\Actions\DownloadAgencySalesReportAction;
use App\Filament\Administration\Resources\Sales\SaleResource;
use App\Filament\Administration\Resources\Sales\Widgets\SalePlanChart;
use App\Filament\Administration\Resources\Sales\Widgets\SaleYearChart;
use App\Filament\Administration\Resources\Sales\Widgets\StatsOverviewSales;
use App\Filament\Administration\Resources\Sales\Widgets\StatsOverviewSalesUsdVes;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;

class ListSales extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = SaleResource::class;

    protected static ?string $title = 'GESTIÓN DE VENTAS';

    protected function getHeaderActions(): array
    {
        return [
            DownloadAgencySalesReportAction::makeHeader(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            StatsOverviewSalesUsdVes::class,
            StatsOverviewSales::class,
            SaleYearChart::class,
            SalePlanChart::class,
        ];
    }
}
