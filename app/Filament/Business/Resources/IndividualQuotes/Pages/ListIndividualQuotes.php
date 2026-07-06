<?php

namespace App\Filament\Business\Resources\IndividualQuotes\Pages;

use App\Filament\Business\Resources\IndividualQuotes\IndividualQuoteResource;
use App\Filament\Business\Resources\IndividualQuotes\Widgets\IndividualQuotesByAgencyTable;
use App\Filament\Business\Resources\IndividualQuotes\Widgets\IndividualQuotesByAgentTable;
use App\Filament\Business\Resources\IndividualQuotes\Widgets\StatsOverviewIndividualQuote;
use App\Filament\Business\Resources\IndividualQuotes\Widgets\StatsOverviewTotalIndividualQuote;
use App\Filament\Business\Resources\IndividualQuotes\Widgets\TotalIndividualQuoteChart;
use Filament\Actions\CreateAction;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\On;

class ListIndividualQuotes extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = IndividualQuoteResource::class;

    protected static ?string $title = 'Cotizaciones Individuales';

    /**
     * Idéntico a Crear Ticket / Crear Nuevo Paciente: .ticket-btn-ios en theme.css (verde, sombras iOS, hover).
     */
    private const TICKET_BUTTON_CLASS = 'ticket-btn-ios shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    #[On('individual-quotes-filter-by-agent')]
    public function filterQuotesByAgent(int|string $agentId, string $agentName): void
    {
        $this->tableFilters ??= [];
        $this->tableFilters['agent_id'] = [
            'value' => (string) $agentId,
        ];

        $this->getTableFiltersForm()->fill($this->tableFilters);
        $this->resetPage();
        $this->flushCachedTableRecords();

        $this->js('window.requestAnimationFrame(() => document.getElementById("individual-quotes-main-table")?.scrollIntoView({ behavior: "smooth", block: "start" }))');
    }

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

    /**
     * @return int|array<string, int|null>
     */
    public function getHeaderWidgetsColumns(): int|array
    {
        return [
            'default' => 1,
            'lg' => 2,
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            StatsOverviewTotalIndividualQuote::class,
            StatsOverviewIndividualQuote::class,
            TotalIndividualQuoteChart::class,
            IndividualQuotesByAgencyTable::class,
            IndividualQuotesByAgentTable::class,
        ];
    }
}
