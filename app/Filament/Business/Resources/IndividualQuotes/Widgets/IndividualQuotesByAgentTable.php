<?php

namespace App\Filament\Business\Resources\IndividualQuotes\Widgets;

use App\Filament\Business\Resources\IndividualQuotes\Widgets\Concerns\InteractsWithIndividualQuotesRankingTable;
use App\Models\Agent;
use App\Support\Filament\IndividualQuotesRankingTableUi;
use App\Support\IndividualQuotes\IndividualQuotesRankingQuery;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class IndividualQuotesByAgentTable extends TableWidget
{
    use InteractsWithIndividualQuotesRankingTable;

    protected string $view = 'filament.widgets.individual-quotes-ranking-table-widget';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 1;

    public ?string $filteredAgencyCode = null;

    public ?string $filteredAgencyName = null;

    protected function rankingTableVariant(): string
    {
        return 'agent';
    }

    #[On('individual-quotes-agency-selected')]
    public function filterAgentsByAgency(string $agencyCode, string $agencyName): void
    {
        $this->filteredAgencyCode = $agencyCode;
        $this->filteredAgencyName = $agencyName;
        $this->resetPage();
        $this->flushCachedTableRecords();
    }

    #[On('individual-quotes-agency-filter-cleared')]
    public function clearAgencyFilter(): void
    {
        $this->filteredAgencyCode = null;
        $this->filteredAgencyName = null;
        $this->resetPage();
        $this->flushCachedTableRecords();
    }

    public function table(Table $table): Table
    {
        $heading = $this->filteredAgencyName
            ? 'Agentes · '.$this->filteredAgencyName
            : IndividualQuotesRankingTableUi::heading('agent');

        $table = IndividualQuotesRankingTableUi::apply(
            table: $table,
            variant: 'agent',
            query: $this->agentQuotesQuery(),
            modelClass: Agent::class,
            nameAttribute: 'name',
            nameLabel: 'Agente',
            typeRelation: 'typeAgent',
            searchPlaceholder: 'Buscar agente o código…',
            emptyHeading: $this->filteredAgencyCode
                ? 'Sin cotizaciones para esta agencia'
                : 'Sin cotizaciones por agente',
            emptyDescription: $this->filteredAgencyCode
                ? 'No hay agentes de la agencia seleccionada con cotizaciones registradas.'
                : 'Las cotizaciones con agente asignado aparecerán aquí agrupadas por agente.',
            heading: $heading,
        );

        if ($this->filteredAgencyCode !== null) {
            $table->headerActions([
                Action::make('clearAgencyFilter')
                    ->label('Ver todos los agentes')
                    ->icon(Heroicon::XMark)
                    ->color('gray')
                    ->action(function (): void {
                        $this->clearAgencyFilter();
                        $this->dispatch('individual-quotes-agency-filter-cleared')
                            ->to(IndividualQuotesByAgencyTable::class);
                    }),
            ]);
        }

        return $table;
    }

    protected function agentQuotesQuery(): Builder
    {
        return IndividualQuotesRankingQuery::agents($this->filteredAgencyCode)->with('typeAgent');
    }
}
