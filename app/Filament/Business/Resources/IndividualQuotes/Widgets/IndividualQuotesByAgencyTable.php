<?php

namespace App\Filament\Business\Resources\IndividualQuotes\Widgets;

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

    protected function rankingTableVariant(): string
    {
        return 'agency';
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

    public function table(Table $table): Table
    {
        return IndividualQuotesRankingTableUi::apply(
            table: $table,
            variant: 'agency',
            query: fn (): Builder => IndividualQuotesRankingQuery::agencies()->with('typeAgency'),
            modelClass: Agency::class,
            nameAttribute: 'name_corporative',
            nameLabel: 'Agencia',
            typeRelation: 'typeAgency',
            searchPlaceholder: 'Buscar agencia o código…',
            emptyHeading: 'Sin cotizaciones por agencia',
            emptyDescription: 'Las cotizaciones sin agente asignado aparecerán aquí agrupadas por agencia.',
        )
            ->recordActionsColumnLabel('')
            ->recordActions([
                Action::make('filterAgents')
                    ->label('Filtrar')
                    ->icon(Heroicon::Funnel)
                    ->color(fn (Agency $record): string => $this->selectedAgencyId === $record->id ? 'info' : 'gray')
                    ->extraAttributes(['class' => 'iq-ranking-filter-btn'])
                    ->action(fn (Agency $record): mixed => $this->selectAgency($record)),
            ])
            ->recordClasses(fn (Agency $record): array => ($this->selectedAgencyId === $record->id)
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
