<?php

namespace App\Filament\Business\Resources\IndividualQuotes\Widgets;

use App\Filament\Business\Resources\IndividualQuotes\Widgets\Concerns\InteractsWithIndividualQuotesRankingTable;
use App\Models\Agency;
use App\Support\Filament\IndividualQuotesRankingTableUi;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Reactive;

class IndividualQuotesByAgencyTable extends TableWidget
{
    use InteractsWithIndividualQuotesRankingTable;

    protected string $view = 'filament.widgets.individual-quotes-ranking-table-widget';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    #[Reactive]
    public ?int $selectedAgencyId = null;

    protected function rankingTableVariant(): string
    {
        return 'agency';
    }

    public function selectAgencyByKey(string $recordKey): void
    {
        $agency = Agency::query()->find($recordKey);

        if (! $agency instanceof Agency) {
            return;
        }

        $this->dispatch(
            'individual-quotes-agency-selected',
            agencyId: $agency->id,
            agencyCode: $agency->code,
            agencyName: $agency->name_corporative,
        );
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
        return Agency::query()
            ->select([
                'agencies.id',
                'agencies.code',
                'agencies.name_corporative',
                'agencies.agency_type_id',
                DB::raw('COUNT(individual_quotes.id) as total_quotes'),
            ])
            ->join('individual_quotes', 'individual_quotes.code_agency', '=', 'agencies.code')
            ->where(function (Builder $query): void {
                $query->whereNull('individual_quotes.agent_id')
                    ->orWhere('individual_quotes.agent_id', '');
            })
            ->with('typeAgency')
            ->groupBy(
                'agencies.id',
                'agencies.code',
                'agencies.name_corporative',
                'agencies.agency_type_id',
            )
            ->having('total_quotes', '>', 0);
    }
}
