<?php

namespace App\Filament\Administration\Resources\Commissions\Pages;

use App\Filament\Administration\Resources\Commissions\CommissionResource;
use App\Filament\Administration\Resources\Commissions\Widgets\StatsOverviewCommissionUsdVes;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;

class ListCommissions extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = CommissionResource::class;

    protected static ?string $title = 'Detallado de Comisiones';

    protected function getHeaderWidgets(): array
    {
        return [
            StatsOverviewCommissionUsdVes::class,
        ];
    }
}
