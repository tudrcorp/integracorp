<?php

namespace App\Filament\Business\Resources\IndividualQuotes\Widgets;

use App\Filament\Business\Resources\IndividualQuotes\Widgets\Concerns\InteractsWithIndividualQuotesRankingTable;
use App\Models\Agency;
use App\Support\Filament\IndividualQuotesRankingTableUi;
use App\Support\IndividualQuotes\IndividualQuotesRankingQuery;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
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

    public function selectAgencyByKey(string $recordKey): void
    {
        $agency = $this->getTableRecord($recordKey);

        if (! $agency instanceof Agency) {
            return;
        }

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
            query: $this->agencyQuotesQuery(),
            modelClass: Agency::class,
            nameAttribute: 'name_corporative',
            nameLabel: 'Agencia',
            typeRelation: 'typeAgency',
            searchPlaceholder: 'Buscar agencia o código…',
            emptyHeading: 'Sin cotizaciones por agencia',
            emptyDescription: 'Las cotizaciones sin agente asignado aparecerán aquí agrupadas por agencia.',
        )
            ->recordAction('selectAgencyByKey')
            ->recordClasses(fn (Agency $record): array => ($this->selectedAgencyId === $record->id)
                ? ['iq-ranking-row--selected']
                : []);
    }

    protected function agencyQuotesQuery(): Builder
    {
        return once(fn (): Builder => IndividualQuotesRankingQuery::agencies()->with('typeAgency'));
    }

    /**
     * La query agregada no es compatible con find(); resolvemos por PK directo.
     *
     * @return \Illuminate\Database\Eloquent\Model|array<string, mixed>|null
     */
    public function getTableRecord(?string $key): \Illuminate\Database\Eloquent\Model|array|null
    {
        if ($key === null) {
            return null;
        }

        return Agency::query()->find($key);
    }
}
