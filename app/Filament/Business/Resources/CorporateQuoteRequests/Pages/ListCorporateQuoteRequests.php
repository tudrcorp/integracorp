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
        $this->tableFilters['without_agent'] = [
            'isActive' => false,
        ];
        $this->tableFilters['code_agency'] = [
            'value' => null,
        ];

        $this->applyCorporateQuoteRequestTableFilters();
    }

    #[On('corporate-quote-requests-filter-by-agency-without-agent')]
    public function filterRequestsByAgencyWithoutAgent(string $agencyCode, string $agencyName): void
    {
        $this->tableFilters ??= [];
        $this->tableFilters['code_agency'] = [
            'value' => $agencyCode,
        ];
        $this->tableFilters['without_agent'] = [
            'isActive' => true,
        ];
        $this->tableFilters['agent_id'] = [
            'value' => null,
        ];

        $this->applyCorporateQuoteRequestTableFilters();
    }

    protected function applyCorporateQuoteRequestTableFilters(): void
    {
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
