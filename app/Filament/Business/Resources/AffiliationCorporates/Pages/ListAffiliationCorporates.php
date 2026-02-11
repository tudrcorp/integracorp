<?php

namespace App\Filament\Business\Resources\AffiliationCorporates\Pages;

use App\Filament\Business\Resources\AffiliationCorporates\AffiliationCorporateResource;
use App\Filament\Business\Resources\AffiliationCorporates\Widgets\AffiliationCorporateChart;
use App\Filament\Business\Resources\AffiliationCorporates\Widgets\AffiliationCorporatePorEstadoChart;
use App\Filament\Business\Resources\AffiliationCorporates\Widgets\StatsOverview;
use App\Filament\Business\Resources\AffiliationCorporates\Widgets\StatsOverviewPlan;
use Filament\Actions\CreateAction;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;

class ListAffiliationCorporates extends ListRecords
{
    use ExposesTableToWidgets;
    protected static string $resource = AffiliationCorporateResource::class;

    protected static ?string $title = 'Afiliaciones Corporativas';

    protected function getHeaderWidgets(): array
    {
        return [
            StatsOverview::class,
            StatsOverviewPlan::class,
            AffiliationCorporateChart::class,
            AffiliationCorporatePorEstadoChart::class,
        ];
    }

}