<?php

namespace App\Filament\Business\Resources\IndividualQuotes\Widgets;

use App\Filament\Business\Resources\IndividualQuotes\Widgets\Concerns\InteractsWithIndividualQuotesRankingTable;
use App\Models\Agent;
use App\Support\Filament\IndividualQuotesRankingTableUi;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Reactive;

class IndividualQuotesByAgentTable extends TableWidget
{
    use InteractsWithIndividualQuotesRankingTable;

    protected string $view = 'filament.widgets.individual-quotes-ranking-table-widget';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 1;

    #[Reactive]
    public ?string $filteredAgencyCode = null;

    #[Reactive]
    public ?string $filteredAgencyName = null;

    protected function rankingTableVariant(): string
    {
        return 'agent';
    }

    public function updatedFilteredAgencyCode(): void
    {
        $this->resetPage();
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
                    ->action(fn (): mixed => $this->dispatch('individual-quotes-agency-filter-cleared')),
            ]);
        }

        return $table;
    }

    protected function agentQuotesQuery(): Builder
    {
        return Agent::query()
            ->select([
                'agents.id',
                'agents.name',
                'agents.code_agent',
                'agents.owner_code',
                'agents.agent_type_id',
                DB::raw('COUNT(individual_quotes.id) as total_quotes'),
            ])
            ->join('individual_quotes', 'individual_quotes.agent_id', '=', 'agents.id')
            ->whereNotNull('individual_quotes.agent_id')
            ->where('individual_quotes.agent_id', '!=', '')
            ->when(
                filled($this->filteredAgencyCode),
                fn (Builder $query): Builder => $query->where('individual_quotes.owner_code', $this->filteredAgencyCode),
            )
            ->with('typeAgent')
            ->groupBy(
                'agents.id',
                'agents.name',
                'agents.code_agent',
                'agents.owner_code',
                'agents.agent_type_id',
            )
            ->having('total_quotes', '>', 0);
    }
}
