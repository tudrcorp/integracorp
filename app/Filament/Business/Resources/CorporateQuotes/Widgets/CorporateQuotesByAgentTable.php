<?php

namespace App\Filament\Business\Resources\CorporateQuotes\Widgets;

use App\Filament\Business\Resources\CorporateQuotes\Pages\ListCorporateQuotes;
use App\Filament\Business\Resources\CorporateQuotes\Widgets\Concerns\InteractsWithCorporateQuotesRankingTable;
use App\Models\Agent;
use App\Support\CorporateQuotes\CorporateQuotesRankingQuery;
use App\Support\Filament\CorporateQuotesRankingTableUi;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;

class CorporateQuotesByAgentTable extends TableWidget
{
    use InteractsWithCorporateQuotesRankingTable;

    protected string $view = 'filament.widgets.corporate-quotes-ranking-table-widget';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 1;

    public ?string $filteredAgencyCode = null;

    public ?string $filteredAgencyName = null;

    public ?int $selectedAgentIdForQuotes = null;

    protected function rankingTableVariant(): string
    {
        return 'agent';
    }

    public function mount(): void
    {
        $this->bootInteractsWithCorporateQuotesRankingTable();
    }

    public function viewAgentQuotes(Agent $agent): void
    {
        $this->selectedAgentIdForQuotes = $agent->id;

        $this->dispatch(
            'corporate-quotes-filter-by-agent',
            agentId: $agent->id,
            agentName: $agent->name,
        )->to(ListCorporateQuotes::class);
    }

    #[On('corporate-quotes-period-changed')]
    public function applyPeriodFilter(string $year, string $month): void
    {
        $this->filterYear = $year;
        $this->filterMonth = $month;
        $this->selectedAgentIdForQuotes = null;
        $this->resetPage();
        $this->flushCachedTableRecords();

        $this->dispatch('corporate-quotes-agent-filter-end');
    }

    #[On('corporate-quotes-agency-selected')]
    public function filterAgentsByAgency(string $agencyCode, string $agencyName): void
    {
        if ($this->filteredAgencyCode !== $agencyCode) {
            $this->filteredAgencyCode = $agencyCode;
            $this->filteredAgencyName = $agencyName;
            $this->selectedAgentIdForQuotes = null;
            $this->resetPage();
            $this->flushCachedTableRecords();
        }

        $this->dispatch('corporate-quotes-agent-filter-end');
    }

    #[On('corporate-quotes-agency-filter-cleared')]
    public function clearAgencyFilter(): void
    {
        if ($this->filteredAgencyCode === null) {
            $this->dispatch('corporate-quotes-agent-filter-end');

            return;
        }

        $this->filteredAgencyCode = null;
        $this->filteredAgencyName = null;
        $this->selectedAgentIdForQuotes = null;
        $this->resetPage();
        $this->flushCachedTableRecords();

        $this->dispatch('corporate-quotes-agent-filter-end');
    }

    public function table(Table $table): Table
    {
        $year = $this->resolvedRankingFilterYear();
        $baseHeading = $this->filteredAgencyName
            ? 'Agentes · '.$this->filteredAgencyName
            : CorporateQuotesRankingTableUi::heading('agent');
        $heading = $baseHeading.' ('.$year.')';

        $table = CorporateQuotesRankingTableUi::apply(
            table: $table,
            variant: 'agent',
            query: fn (): Builder => $this->agentQuotesQuery(),
            modelClass: Agent::class,
            nameAttribute: 'name',
            nameLabel: 'Agente',
            typeRelation: 'typeAgent',
            searchPlaceholder: 'Buscar agente o código…',
            emptyHeading: $this->filteredAgencyCode
                ? 'Sin cotizaciones para esta agencia'
                : 'Sin cotizaciones por agente',
            emptyDescription: $this->filteredAgencyCode
                ? 'No hay agentes de la agencia seleccionada con cotizaciones corporativas.'
                : 'Las cotizaciones con agente asignado aparecerán aquí agrupadas por agente.',
            heading: $heading,
        )
            ->recordActionsColumnLabel('')
            ->recordActions([
                Action::make('viewQuotes')
                    ->label('Ver cotizaciones')
                    ->icon(Heroicon::Bars3)
                    ->color(fn (Agent $record): string => $this->selectedAgentIdForQuotes === $record->id ? 'primary' : 'gray')
                    ->extraAttributes(['class' => 'iq-ranking-quotes-btn'])
                    ->action(fn (Agent $record): mixed => $this->viewAgentQuotes($record)),
            ])
            ->recordClasses(fn (Agent $record): array => ($this->selectedAgentIdForQuotes === $record->id)
                ? ['iq-ranking-row--selected']
                : []);

        if ($this->filteredAgencyCode !== null) {
            $table->headerActions([
                Action::make('clearAgencyFilter')
                    ->label('Ver todos los agentes')
                    ->icon(Heroicon::XMark)
                    ->color('gray')
                    ->action(function (): void {
                        $this->dispatch('corporate-quotes-agent-filter-start');
                        $this->clearAgencyFilter();
                        $this->dispatch('corporate-quotes-agency-filter-cleared')
                            ->to(CorporateQuotesByAgencyTable::class);
                    }),
            ]);
        }

        return $table;
    }

    protected function agentQuotesQuery(): Builder
    {
        return CorporateQuotesRankingQuery::agents(
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
