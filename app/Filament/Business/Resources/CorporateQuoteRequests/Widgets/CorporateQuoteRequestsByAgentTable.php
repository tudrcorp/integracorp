<?php

namespace App\Filament\Business\Resources\CorporateQuoteRequests\Widgets;

use App\Filament\Business\Resources\CorporateQuoteRequests\Pages\ListCorporateQuoteRequests;
use App\Filament\Business\Resources\CorporateQuoteRequests\Widgets\Concerns\InteractsWithCorporateQuoteRequestsRankingTable;
use App\Models\Agent;
use App\Support\CorporateQuoteRequests\CorporateQuoteRequestsRankingQuery;
use App\Support\Filament\CorporateQuoteRequestsRankingTableUi;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;

class CorporateQuoteRequestsByAgentTable extends TableWidget
{
    use InteractsWithCorporateQuoteRequestsRankingTable;

    protected string $view = 'filament.widgets.corporate-quote-requests-ranking-table-widget';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    public ?string $filteredAgencyCode = null;

    public ?string $filteredAgencyName = null;

    public ?int $selectedAgentIdForRequests = null;

    protected function rankingTableVariant(): string
    {
        return 'agent';
    }

    public function mount(): void
    {
        $this->bootInteractsWithCorporateQuoteRequestsRankingTable();
    }

    public function viewAgentRequests(Agent $agent): void
    {
        $this->selectedAgentIdForRequests = $agent->id;

        $this->dispatch(
            'corporate-quote-requests-filter-by-agent',
            agentId: $agent->id,
            agentName: $agent->name,
        )->to(ListCorporateQuoteRequests::class);
    }

    #[On('corporate-quote-requests-period-changed')]
    public function applyPeriodFilter(string $year, string $month): void
    {
        $this->filterYear = $year;
        $this->filterMonth = $month;
        $this->selectedAgentIdForRequests = null;
        $this->resetPage();
        $this->flushCachedTableRecords();

        $this->dispatch('corporate-quote-requests-agent-filter-end');
    }

    #[On('corporate-quote-requests-agency-selected')]
    public function filterAgentsByAgency(string $agencyCode, string $agencyName): void
    {
        if ($this->filteredAgencyCode !== $agencyCode) {
            $this->filteredAgencyCode = $agencyCode;
            $this->filteredAgencyName = $agencyName;
            $this->selectedAgentIdForRequests = null;
            $this->resetPage();
            $this->flushCachedTableRecords();
        }

        $this->dispatch('corporate-quote-requests-agent-filter-end');
    }

    #[On('corporate-quote-requests-agency-filter-cleared')]
    public function clearAgencyFilter(): void
    {
        if ($this->filteredAgencyCode === null) {
            $this->dispatch('corporate-quote-requests-agent-filter-end');

            return;
        }

        $this->filteredAgencyCode = null;
        $this->filteredAgencyName = null;
        $this->selectedAgentIdForRequests = null;
        $this->resetPage();
        $this->flushCachedTableRecords();

        $this->dispatch('corporate-quote-requests-agent-filter-end');
    }

    public function table(Table $table): Table
    {
        $year = $this->resolvedRankingFilterYear();
        $baseHeading = $this->filteredAgencyName
            ? 'Agentes · '.$this->filteredAgencyName
            : CorporateQuoteRequestsRankingTableUi::heading('agent');
        $heading = $baseHeading.' ('.$year.')';

        $table = CorporateQuoteRequestsRankingTableUi::apply(
            table: $table,
            variant: 'agent',
            query: fn (): Builder => $this->agentRequestsQuery(),
            modelClass: Agent::class,
            nameAttribute: 'name',
            nameLabel: 'Agente',
            typeRelation: 'typeAgent',
            searchPlaceholder: 'Buscar agente o código…',
            emptyHeading: $this->filteredAgencyCode
                ? 'Sin solicitudes para esta agencia'
                : 'Sin solicitudes por agente',
            emptyDescription: $this->filteredAgencyCode
                ? 'No hay agentes de la agencia seleccionada con solicitudes Dress Taylor.'
                : 'Las solicitudes con agente asignado aparecerán aquí agrupadas por agente.',
            heading: $heading,
        )
            ->recordActionsColumnLabel('')
            ->recordActions([
                Action::make('viewRequests')
                    ->label('Ver solicitudes')
                    ->icon(Heroicon::Bars3)
                    ->color(fn (Agent $record): string => $this->selectedAgentIdForRequests === $record->id ? 'primary' : 'gray')
                    ->extraAttributes(['class' => 'iq-ranking-quotes-btn'])
                    ->action(fn (Agent $record): mixed => $this->viewAgentRequests($record)),
            ])
            ->recordClasses(fn (Agent $record): array => ($this->selectedAgentIdForRequests === $record->id)
                ? ['iq-ranking-row--selected']
                : []);

        if ($this->filteredAgencyCode !== null) {
            $table->headerActions([
                Action::make('clearAgencyFilter')
                    ->label('Ver todos los agentes')
                    ->icon(Heroicon::XMark)
                    ->color('gray')
                    ->action(function (): void {
                        $this->dispatch('corporate-quote-requests-agent-filter-start');
                        $this->clearAgencyFilter();
                        $this->dispatch('corporate-quote-requests-agency-filter-cleared')
                            ->to(CorporateQuoteRequestsByAgencyTable::class);
                    }),
            ]);
        }

        return $table;
    }

    protected function agentRequestsQuery(): Builder
    {
        return CorporateQuoteRequestsRankingQuery::agents(
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
