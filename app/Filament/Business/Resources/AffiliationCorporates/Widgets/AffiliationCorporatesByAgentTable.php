<?php

namespace App\Filament\Business\Resources\AffiliationCorporates\Widgets;

use App\Filament\Business\Resources\AffiliationCorporates\Pages\ListAffiliationCorporates;
use App\Filament\Business\Resources\AffiliationCorporates\Widgets\Concerns\InteractsWithAffiliationCorporatesRankingTable;
use App\Models\Agent;
use App\Support\AffiliationCorporates\AffiliationCorporatesRankingQuery;
use App\Support\Filament\AffiliationCorporatesRankingTableUi;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;

class AffiliationCorporatesByAgentTable extends TableWidget
{
    use InteractsWithAffiliationCorporatesRankingTable;

    protected string $view = 'filament.widgets.affiliation-corporates-ranking-table-widget';

    protected static ?int $sort = 5;

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
        $this->bootInteractsWithAffiliationCorporatesRankingTable();
    }

    public function viewAgentAffiliations(Agent $agent): void
    {
        $this->selectedAgentIdForAffiliations = $agent->id;

        $this->dispatch(
            'affiliation-corporates-filter-by-agent',
            agentId: $agent->id,
            agentName: $agent->name,
        )->to(ListAffiliationCorporates::class);
    }

    #[On('affiliation-corporates-period-changed')]
    public function applyPeriodFilter(string $year, string $month): void
    {
        $this->filterYear = $year;
        $this->filterMonth = $month;
        $this->selectedAgentIdForAffiliations = null;
        $this->resetPage();
        $this->flushCachedTableRecords();

        $this->dispatch('affiliation-corporates-agent-filter-end');
    }

    #[On('affiliation-corporates-agency-selected')]
    public function filterAgentsByAgency(string $agencyCode, string $agencyName): void
    {
        if ($this->filteredAgencyCode !== $agencyCode) {
            $this->filteredAgencyCode = $agencyCode;
            $this->filteredAgencyName = $agencyName;
            $this->selectedAgentIdForAffiliations = null;
            $this->resetPage();
            $this->flushCachedTableRecords();
        }

        $this->dispatch('affiliation-corporates-agent-filter-end');
    }

    #[On('affiliation-corporates-agency-filter-cleared')]
    public function clearAgencyFilter(): void
    {
        if ($this->filteredAgencyCode === null) {
            $this->dispatch('affiliation-corporates-agent-filter-end');

            return;
        }

        $this->filteredAgencyCode = null;
        $this->filteredAgencyName = null;
        $this->selectedAgentIdForAffiliations = null;
        $this->resetPage();
        $this->flushCachedTableRecords();

        $this->dispatch('affiliation-corporates-agent-filter-end');
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
                ? 'No hay agentes de la agencia seleccionada con afiliaciones corporativas.'
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
                        $this->dispatch('affiliation-corporates-agent-filter-start');
                        $this->clearAgencyFilter();
                        $this->dispatch('affiliation-corporates-agency-filter-cleared')
                            ->to(AffiliationCorporatesByAgencyTable::class);
                    }),
            ]);
        }

        return $table;
    }

    protected function agentAffiliationsQuery(): Builder
    {
        return AffiliationCorporatesRankingQuery::agents(
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
