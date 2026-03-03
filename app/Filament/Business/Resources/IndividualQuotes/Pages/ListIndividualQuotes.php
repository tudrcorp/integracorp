<?php

namespace App\Filament\Business\Resources\IndividualQuotes\Pages;

use App\Filament\Business\Resources\IndividualQuotes\IndividualQuoteResource;
use App\Filament\Business\Resources\IndividualQuotes\Widgets\IndividualQuotesAgencyAverageChart;
use App\Filament\Business\Resources\IndividualQuotes\Widgets\IndividualQuotesAgentAverageChart;
use App\Filament\Business\Resources\IndividualQuotes\Widgets\StatsOverviewIndividualQuote;
use App\Filament\Business\Resources\IndividualQuotes\Widgets\StatsOverviewIndividualQuoteAprobada;
use App\Filament\Business\Resources\IndividualQuotes\Widgets\StatsOverviewTotalIndividualQuote;
use App\Filament\Business\Resources\IndividualQuotes\Widgets\TotalIndividualQuoteChart;
use Filament\Actions\CreateAction;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;

class ListIndividualQuotes extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = IndividualQuoteResource::class;

    protected static ?string $title = 'Cotizaciones Individuales';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear cotización individual')
                ->icon('heroicon-s-plus')
                ->color('success'),

        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            StatsOverviewTotalIndividualQuote::class,
            StatsOverviewIndividualQuote::class,
            StatsOverviewIndividualQuoteAprobada::class,
            TotalIndividualQuoteChart::class,
            IndividualQuotesAgentAverageChart::class,
            IndividualQuotesAgencyAverageChart::class,
        ];
    }
}
