<?php

namespace App\Filament\Business\Resources\IndividualQuotes\Pages;

use App\Filament\Business\Resources\IndividualQuotes\IndividualQuoteResource;
use App\Filament\Business\Resources\IndividualQuotes\Widgets\IndividualQuotesAgencyAverageChart;
use App\Filament\Business\Resources\IndividualQuotes\Widgets\IndividualQuotesAgentAverageChart;
use App\Filament\Business\Resources\IndividualQuotes\Widgets\IndividualQuotesQuotesByUserPerMonthChart;
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

    /**
     * Idéntico a Crear Ticket / Crear Nuevo Paciente: .ticket-btn-ios en theme.css (verde, sombras iOS, hover).
     */
    private const TICKET_BUTTON_CLASS = 'ticket-btn-ios shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear cotización individual')
                ->icon('heroicon-s-plus')
                ->color('success')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ]),

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
            IndividualQuotesQuotesByUserPerMonthChart::class,
        ];
    }
}
