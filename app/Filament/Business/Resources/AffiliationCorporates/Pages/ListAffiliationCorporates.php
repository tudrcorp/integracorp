<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\AffiliationCorporates\Pages;

use App\Filament\Business\Resources\AffiliationCorporates\AffiliationCorporateResource;
use App\Filament\Business\Resources\AffiliationCorporates\Widgets\AffiliationCorporateChart;
use App\Filament\Business\Resources\AffiliationCorporates\Widgets\AffiliationCorporatePorEstadoChart;
use App\Filament\Business\Resources\AffiliationCorporates\Widgets\AffiliationCorporatesByAgencyTable;
use App\Filament\Business\Resources\AffiliationCorporates\Widgets\AffiliationCorporatesByAgentTable;
use App\Filament\Business\Resources\AffiliationCorporates\Widgets\StatsOverview;
use App\Filament\Business\Resources\AffiliationCorporates\Widgets\StatsOverviewPlan;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\On;

class ListAffiliationCorporates extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = AffiliationCorporateResource::class;

    protected static ?string $title = 'Afiliaciones Corporativas';

    #[On('affiliation-corporates-filter-by-agent')]
    public function filterAffiliationsByAgent(int|string $agentId, string $agentName): void
    {
        $this->tableFilters ??= [];
        $this->tableFilters['agent_id'] = [
            'value' => (string) $agentId,
        ];

        $this->getTableFiltersForm()->fill($this->tableFilters);
        $this->resetPage();
        $this->flushCachedTableRecords();

        $this->js('window.requestAnimationFrame(() => document.getElementById("affiliation-corporates-main-table")?.scrollIntoView({ behavior: "smooth", block: "start" }))');
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
            StatsOverview::class,
            StatsOverviewPlan::class,
            AffiliationCorporateChart::class,
            AffiliationCorporatePorEstadoChart::class,
            AffiliationCorporatesByAgencyTable::class,
            AffiliationCorporatesByAgentTable::class,
        ];
    }
}
