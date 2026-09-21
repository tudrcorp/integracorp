<?php

namespace App\Filament\Business\Resources\IndividualQuotes\Widgets;

use App\Filament\Business\Resources\IndividualQuotes\Pages\ListIndividualQuotes;
use App\Filament\Business\Resources\IndividualQuotes\Widgets\Concerns\InteractsWithIndividualQuotesRankingTable;
use App\Models\Agency;
use App\Support\Filament\IndividualQuotesRankingTableUi;
use App\Support\IndividualQuotes\IndividualQuotesRankingQuery;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;

class IndividualQuotesByAgencyTable extends TableWidget
{
    use InteractsWithIndividualQuotesRankingTable;

    protected string $view = 'filament.widgets.individual-quotes-ranking-table-widget';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    public ?int $selectedAgencyId = null;

    public ?int $selectedAgencyIdForUnassignedQuotes = null;

    protected function rankingTableVariant(): string
    {
        return 'agency';
    }

    public function mount(): void
    {
        $this->bootInteractsWithIndividualQuotesRankingTable();
    }

    public function updatedFilterYear(): void
    {
        $this->syncPeriodToAgentTable();
    }

    public function updatedFilterMonth(): void
    {
        $this->syncPeriodToAgentTable();
    }

    protected function syncPeriodToAgentTable(): void
    {
        $this->resetPage();
        $this->flushCachedTableRecords();

        $this->dispatch('individual-quotes-agent-filter-start');
        $this->dispatch(
            'individual-quotes-period-changed',
            year: (string) $this->resolvedRankingFilterYear(),
            month: (string) ((int) $this->filterMonth),
        )->to(IndividualQuotesByAgentTable::class);
    }

    public function selectAgency(Agency $agency): void
    {
        $this->dispatch('individual-quotes-agent-filter-start');

        if ($this->selectedAgencyId === $agency->id) {
            $this->selectedAgencyId = null;
            $this->dispatch('individual-quotes-agency-filter-cleared')
                ->to(IndividualQuotesByAgentTable::class);

            return;
        }

        $this->selectedAgencyId = $agency->id;

        $this->dispatch(
            'individual-quotes-agency-selected',
            agencyCode: $agency->code,
            agencyName: $agency->name_corporative,
        )->to(IndividualQuotesByAgentTable::class);
    }

    #[On('individual-quotes-agency-filter-cleared')]
    public function clearAgencySelectionHighlight(): void
    {
        $this->selectedAgencyId = null;
    }

    public function viewAgencyQuotesWithoutAgent(Agency $agency): void
    {
        $this->selectedAgencyIdForUnassignedQuotes = $agency->id;

        $this->dispatch(
            'individual-quotes-filter-by-agency-without-agent',
            agencyCode: $agency->code,
            agencyName: $agency->name_corporative,
        )->to(ListIndividualQuotes::class);
    }

    public function table(Table $table): Table
    {
        return IndividualQuotesRankingTableUi::apply(
            table: $table,
            variant: 'agency',
            query: fn (): Builder => IndividualQuotesRankingQuery::agencies(
                $this->resolvedRankingFilterYear(),
                $this->resolvedRankingFilterMonth(),
            )->with('typeAgency'),
            modelClass: Agency::class,
            nameAttribute: 'name_corporative',
            nameLabel: 'Agencia',
            typeRelation: 'typeAgency',
            searchPlaceholder: 'Buscar agencia o código…',
            emptyHeading: 'Sin cotizaciones por agencia',
            emptyDescription: 'Las cotizaciones sin agente asignado aparecerán aquí agrupadas por agencia.',
            heading: false,
        )
            ->recordActionsColumnLabel('')
            ->recordActions([
                Action::make('filterAgents')
                    ->label('Filtrar')
                    ->icon(Heroicon::Funnel)
                    ->color(fn (Agency $record): string => $this->selectedAgencyId === $record->id ? 'info' : 'gray')
                    ->extraAttributes(['class' => 'iq-ranking-filter-btn'])
                    ->action(fn (Agency $record): mixed => $this->selectAgency($record)),
                Action::make('viewQuotesWithoutAgent')
                    ->label('Ver cotizaciones sin agente')
                    ->tooltip('Cotizaciones de esta agencia que no tienen un agente asignado')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color(fn (Agency $record): string => $this->selectedAgencyIdForUnassignedQuotes === $record->id ? 'warning' : 'gray')
                    ->extraAttributes(fn (Agency $record): array => [
                        'class' => $this->selectedAgencyIdForUnassignedQuotes === $record->id
                            ? 'iq-ranking-unassigned-btn iq-ranking-unassigned-btn--active'
                            : 'iq-ranking-unassigned-btn',
                    ])
                    ->action(fn (Agency $record): mixed => $this->viewAgencyQuotesWithoutAgent($record)),
            ])
            ->recordClasses(fn (Agency $record): array => (
                $this->selectedAgencyId === $record->id
                || $this->selectedAgencyIdForUnassignedQuotes === $record->id
            )
                ? ['iq-ranking-row--selected']
                : []);
    }

    /**
     * Prioriza el registro ya cargado en la página. No usar ->get($key) directo
     * sobre el paginador: sus claves son índices 0..n y colisionan con PKs.
     *
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

        if ($cachedRecord instanceof Agency) {
            return $cachedRecord;
        }

        return Agency::query()->find($key);
    }
}
