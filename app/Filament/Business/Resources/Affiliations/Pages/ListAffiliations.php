<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Affiliations\Pages;

use App\Filament\Business\Resources\Affiliations\AffiliationResource;
use App\Filament\Business\Resources\Affiliations\Widgets\AffiliationChart;
use App\Filament\Business\Resources\Affiliations\Widgets\AffiliationPlanChart;
use App\Filament\Business\Resources\Affiliations\Widgets\AffiliationsByAgencyTable;
use App\Filament\Business\Resources\Affiliations\Widgets\AffiliationsByAgentTable;
use App\Filament\Business\Resources\Affiliations\Widgets\AffiliationSupplierChart;
use App\Filament\Business\Resources\Affiliations\Widgets\StatsOverview;
use App\Filament\Business\Resources\Affiliations\Widgets\StatsOverviewPlan;
use App\Filament\Business\Resources\Affiliations\Widgets\TotalAfiliacionesPorEstado;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\On;

class ListAffiliations extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = AffiliationResource::class;

    protected static ?string $title = 'Afiliaciones Individuales';

    #[On('affiliations-filter-by-agent')]
    public function filterAffiliationsByAgent(int|string $agentId, string $agentName): void
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

        $this->applyAffiliationTableFilters();
    }

    #[On('affiliations-filter-by-agency-without-agent')]
    public function filterAffiliationsByAgencyWithoutAgent(string $agencyCode, string $agencyName): void
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

        $this->applyAffiliationTableFilters();
    }

    protected function applyAffiliationTableFilters(): void
    {
        $this->getTableFiltersForm()->fill($this->tableFilters);
        $this->resetPage();
        $this->flushCachedTableRecords();

        $this->js('window.requestAnimationFrame(() => document.getElementById("affiliations-main-table")?.scrollIntoView({ behavior: "smooth", block: "start" }))');
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

            AffiliationChart::class,
            TotalAfiliacionesPorEstado::class,

            AffiliationPlanChart::class,
            AffiliationSupplierChart::class,
            AffiliationsByAgencyTable::class,
            AffiliationsByAgentTable::class,
        ];
    }
}
