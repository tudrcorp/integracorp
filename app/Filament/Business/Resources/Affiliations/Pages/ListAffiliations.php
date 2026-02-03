<?php

namespace App\Filament\Business\Resources\Affiliations\Pages;

use App\Filament\Business\Resources\Affiliations\AffiliationResource;
use App\Filament\Business\Resources\Affiliations\Widgets\AffiliationAgencyChart;
use App\Filament\Business\Resources\Affiliations\Widgets\AffiliationChart;
use App\Filament\Business\Resources\Affiliations\Widgets\AffiliationPlanChart;
use App\Filament\Business\Resources\Affiliations\Widgets\AffiliationSupplierChart;
use App\Filament\Business\Resources\Affiliations\Widgets\ExclutionChart;
use App\Filament\Business\Resources\Affiliations\Widgets\StatsOverview;
use App\Filament\Business\Resources\Affiliations\Widgets\StatsOverviewPlan;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Pages\Concerns\ExposesTableToWidgets;

class ListAffiliations extends ListRecords
{
    use ExposesTableToWidgets;
    protected static string $resource = AffiliationResource::class;

    protected static ?string $title = 'Afiliaciones Individuales';

    protected function getHeaderWidgets(): array
    {
        return [
            StatsOverview::class,
            StatsOverviewPlan::class,
            AffiliationChart::class,
            ExclutionChart::class,
            AffiliationPlanChart::class,
            AffiliationSupplierChart::class,
            // AffiliationAgencyChart::class,
            // AffiliationAgencyChart::class,
        ];
    }

}