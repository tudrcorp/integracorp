<?php

namespace App\Filament\Business\Resources\Affiliations\Widgets;

use App\Models\Affiliate;
use App\Models\Affiliation;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use App\Filament\Business\Resources\Affiliations\Pages\ListAffiliations;

class StatsOverview extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ListAffiliations::class;
    }

    protected function getStats(): array
    {
        // dd($this->getPageTableQuery(), $this->getPageTableRecords());

        return [

            Stat::make('Total Afiliados Individuales', $this->getPageTableQuery()->where('status', 'ACTIVA')->count() . ' Afiliados')
                ->icon('heroicon-m-user-group')
                ->description('Con Planes Individuales')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('planIncial')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:scale-[1.02] transition-all duration-300 rounded-xl border-b-4 border-[#25b4e7] dark:border-[#25b4e7]',
                ]),
            Stat::make('Total Neto', 'US$ ' . number_format($this->getPageTableQuery()->where('status', 'ACTIVA')->sum('total_amount'), 2, ',', '.'))
                ->icon('heroicon-m-currency-dollar')
                ->description('Total Neto Cuantificable')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('planIdeal')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:scale-[1.02] transition-all duration-300 rounded-xl border-b-4 border-[#25b4e7] dark:border-[#25b4e7]',
                ]),
        ];
    }
}