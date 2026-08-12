<?php

namespace App\Filament\Business\Resources\CorporateQuotes\Pages;

use App\Filament\Business\Resources\CorporateQuotes\CorporateQuoteResource;
use App\Filament\Business\Resources\CorporateQuotes\Widgets\CorporateQuotesByAgencyTable;
use App\Filament\Business\Resources\CorporateQuotes\Widgets\CorporateQuotesByAgentTable;
use App\Filament\Business\Resources\CorporateQuotes\Widgets\StatsOverviewCorporateQuote;
use App\Filament\Business\Resources\CorporateQuotes\Widgets\StatsOverviewTotalCorporateQuote;
use App\Filament\Business\Resources\CorporateQuotes\Widgets\TotalCorporateQuoteChart;
use Filament\Actions\CreateAction;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\On;

class ListCorporateQuotes extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = CorporateQuoteResource::class;

    protected static ?string $title = 'Cotizaciones Corporativas';

    /**
     * Idéntico a Crear Ticket / Crear Nuevo Paciente: .ticket-btn-ios en theme.css (verde, sombras iOS, hover).
     */
    private const TICKET_BUTTON_CLASS = 'ticket-btn-ios shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    #[On('corporate-quotes-filter-by-agent')]
    public function filterQuotesByAgent(int|string $agentId, string $agentName): void
    {
        $this->tableFilters ??= [];
        $this->tableFilters['agent_id'] = [
            'value' => (string) $agentId,
        ];

        $this->getTableFiltersForm()->fill($this->tableFilters);
        $this->resetPage();
        $this->flushCachedTableRecords();

        $this->js('window.requestAnimationFrame(() => document.getElementById("corporate-quotes-main-table")?.scrollIntoView({ behavior: "smooth", block: "start" }))');
    }

    protected function getActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear cotización corporativa')
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
            StatsOverviewTotalCorporateQuote::class,
            StatsOverviewCorporateQuote::class,
            TotalCorporateQuoteChart::class,
            CorporateQuotesByAgencyTable::class,
            CorporateQuotesByAgentTable::class,
        ];
    }
}
