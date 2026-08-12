<?php

namespace App\Filament\Business\Resources\CorporateQuoteRequests\Pages;

use App\Filament\Business\Resources\CorporateQuoteRequests\CorporateQuoteRequestResource;
use App\Filament\Business\Resources\CorporateQuoteRequests\Widgets\CorporateQuoteRequestChannelChart;
use App\Filament\Business\Resources\CorporateQuoteRequests\Widgets\CorporateQuoteRequestsByAgencyTable;
use App\Filament\Business\Resources\CorporateQuoteRequests\Widgets\CorporateQuoteRequestsByAgentTable;
use App\Filament\Business\Resources\CorporateQuoteRequests\Widgets\StatsOverviewTotalCorporateQuoteRequest;
use Filament\Actions\CreateAction;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\On;

class ListCorporateQuoteRequests extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = CorporateQuoteRequestResource::class;

    protected static ?string $title = 'Solicitudes Dress Taylor';

    #[On('corporate-quote-requests-filter-by-agent')]
    public function filterRequestsByAgent(int|string $agentId, string $agentName): void
    {
        $this->tableFilters ??= [];
        $this->tableFilters['agent_id'] = [
            'value' => (string) $agentId,
        ];

        $this->getTableFiltersForm()->fill($this->tableFilters);
        $this->resetPage();
        $this->flushCachedTableRecords();

        $this->js('window.requestAnimationFrame(() => document.getElementById("corporate-quote-requests-main-table")?.scrollIntoView({ behavior: "smooth", block: "start" }))');
    }

    protected function getActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear solicitud')
                ->icon('heroicon-s-plus')
                ->color('success'),
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
            StatsOverviewTotalCorporateQuoteRequest::class,
            CorporateQuoteRequestsByAgencyTable::class,
            CorporateQuoteRequestsByAgentTable::class,
            CorporateQuoteRequestChannelChart::class,
        ];
    }
}
