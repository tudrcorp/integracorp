<?php

namespace App\Filament\Business\Resources\Affiliations\Widgets;

use App\Filament\Business\Resources\Affiliations\Pages\ListAffiliations;
use App\Filament\Business\Resources\Affiliations\Widgets\Concerns\InteractsWithAffiliationsRankingTable;
use App\Models\Agent;
use App\Support\Affiliations\AffiliationsRankingQuery;
use App\Support\Filament\AffiliationCorporatesRankingTableUi;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;

class AffiliationsByAgentTable extends TableWidget
{
    use InteractsWithAffiliationsRankingTable;

    protected string $view = 'filament.widgets.affiliations-ranking-table-widget';

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 1;

    public ?string $filteredAgencyCode = null;

    public ?string $filteredAgencyName = null;

    public ?int $selectedAgentIdForAffiliations = null;

    protected function rankingTableVariant(): string
    {
        return 'agent';
    }

    public function mount(): void
    {
        $this->bootInteractsWithAffiliationsRankingTable();
    }

    public function viewAgentAffiliations(Agent $agent): void
    {
        $this->selectedAgentIdForAffiliations = $agent->id;

        $this->dispatch(
            'affiliations-filter-by-agent',
            agentId: $agent->id,
            agentName: $agent->name,
        )->to(ListAffiliations::class);
    }

    #[On('affiliations-period-changed')]
    public function applyPeriodFilter(string $year, string $month): void
    {
        $this->filterYear = $year;
        $this->filterMonth = $month;
        $this->selectedAgentIdForAffiliations = null;
        $this->resetPage();
        $this->flushCachedTableRecords();

        $this->dispatch('affiliations-agent-filter-end');
    }

    #[On('affiliations-agency-selected')]
    public function filterAgentsByAgency(string $agencyCode, string $agencyName): void
    {
        if ($this->filteredAgencyCode !== $agencyCode) {
            $this->filteredAgencyCode = $agencyCode;
            $this->filteredAgencyName = $agencyName;
            $this->selectedAgentIdForAffiliations = null;
            $this->resetPage();
            $this->flushCachedTableRecords();
        }

        $this->dispatch('affiliations-agent-filter-end');
    }

    #[On('affiliations-agency-filter-cleared')]
    public function clearAgencyFilter(): void
    {
        if ($this->filteredAgencyCode === null) {
            $this->dispatch('affiliations-agent-filter-end');

            return;
        }

        $this->filteredAgencyCode = null;
        $this->filteredAgencyName = null;
        $this->selectedAgentIdForAffiliations = null;
        $this->resetPage();
        $this->flushCachedTableRecords();

        $this->dispatch('affiliations-agent-filter-end');
    }

    public function table(Table $table): Table
    {
        $year = $this->resolvedRankingFilterYear();
        $baseHeading = $this->filteredAgencyName
            ? 'Agentes · '.$this->filteredAgencyName
            : AffiliationCorporatesRankingTableUi::heading('agent');
        $heading = $baseHeading.' ('.$year.')';

        $table = AffiliationCorporatesRankingTableUi::apply(
            table: $table,
            variant: 'agent',
            query: fn (): Builder => $this->agentAffiliationsQuery(),
            modelClass: Agent::class,
            nameAttribute: 'name',
            nameLabel: 'Agente',
            typeRelation: 'typeAgent',
            searchPlaceholder: 'Buscar agente o código…',
            emptyHeading: $this->filteredAgencyCode
                ? 'Sin afiliaciones para esta agencia'
                : 'Sin afiliaciones por agente',
            emptyDescription: $this->filteredAgencyCode
                ? 'No hay agentes de la agencia seleccionada con afiliaciones individuales.'
                : 'Las afiliaciones con agente asignado aparecerán aquí agrupadas por agente.',
            heading: $heading,
        )
            ->recordActionsColumnLabel('')
            ->recordActions([
                Action::make('viewAffiliations')
                    ->label('Ver afiliaciones')
                    ->icon(Heroicon::Bars3)
                    ->color(fn (Agent $record): string => $this->selectedAgentIdForAffiliations === $record->id ? 'primary' : 'gray')
                    ->extraAttributes(['class' => 'iq-ranking-quotes-btn'])
                    ->action(fn (Agent $record): mixed => $this->viewAgentAffiliations($record)),
            ])
            ->recordClasses(fn (Agent $record): array => ($this->selectedAgentIdForAffiliations === $record->id)
                ? ['iq-ranking-row--selected']
                : []);

        if ($this->filteredAgencyCode !== null) {
            $table->headerActions([
                Action::make('clearAgencyFilter')
                    ->label('Ver todos los agentes')
                    ->icon(Heroicon::XMark)
                    ->color('gray')
                    ->action(function (): void {
                        $this->dispatch('affiliations-agent-filter-start');
                        $this->clearAgencyFilter();
                        $this->dispatch('affiliations-agency-filter-cleared')
                            ->to(AffiliationsByAgencyTable::class);
                    }),
            ]);
        }

        return $table;
    }

    protected function agentAffiliationsQuery(): Builder
    {
        return AffiliationsRankingQuery::agents(
            $this->filteredAgencyCode,
            $this->resolvedRankingFilterYear(),
            $this->resolvedRankingFilterMonth(),
        )->with('typeAgent');
    }

    /**
     * @return Model|array<string, mixed>|null
     */
    public function getTableRecord(?string $key): Model|array|null
    {
        if ($key === null) {
            return null;
        }

        $records = $this->getTableRecords();

        $collection = $records instanceof \Illuminate\Contracts\Pagination\Paginator
            || $records instanceof \Illuminate\Contracts\Pagination\CursorPaginator
            ? $records->getCollection()
            : collect($records);

        $cachedRecord = $collection->first(
            fn (mixed $record): bool => (string) $this->getTableRecordKey($record) === (string) $key,
        );

        if ($cachedRecord instanceof Agent) {
            return $cachedRecord;
        }

        return Agent::query()->find($key);
    }
}
