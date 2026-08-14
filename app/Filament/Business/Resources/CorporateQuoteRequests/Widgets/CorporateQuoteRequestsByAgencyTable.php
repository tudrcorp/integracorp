<?php

namespace App\Filament\Business\Resources\CorporateQuoteRequests\Widgets;

use App\Filament\Business\Resources\CorporateQuoteRequests\Widgets\Concerns\InteractsWithCorporateQuoteRequestsRankingTable;
use App\Models\Agency;
use App\Support\CorporateQuoteRequests\CorporateQuoteRequestsRankingQuery;
use App\Support\Filament\CorporateQuoteRequestsRankingTableUi;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;

class CorporateQuoteRequestsByAgencyTable extends TableWidget
{
    use InteractsWithCorporateQuoteRequestsRankingTable;

    protected string $view = 'filament.widgets.corporate-quote-requests-ranking-table-widget';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    public ?int $selectedAgencyId = null;

    protected function rankingTableVariant(): string
    {
        return 'agency';
    }

    public function mount(): void
    {
        $this->bootInteractsWithCorporateQuoteRequestsRankingTable();
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

        $this->dispatch('corporate-quote-requests-agent-filter-start');
        $this->dispatch(
            'corporate-quote-requests-period-changed',
            year: (string) $this->resolvedRankingFilterYear(),
            month: (string) ((int) $this->filterMonth),
        )->to(CorporateQuoteRequestsByAgentTable::class);
    }

    public function selectAgency(Agency $agency): void
    {
        $this->dispatch('corporate-quote-requests-agent-filter-start');

        if ($this->selectedAgencyId === $agency->id) {
            $this->selectedAgencyId = null;
            $this->dispatch('corporate-quote-requests-agency-filter-cleared')
                ->to(CorporateQuoteRequestsByAgentTable::class);

            return;
        }

        $this->selectedAgencyId = $agency->id;

        $this->dispatch(
            'corporate-quote-requests-agency-selected',
            agencyCode: $agency->code,
            agencyName: $agency->name_corporative,
        )->to(CorporateQuoteRequestsByAgentTable::class);
    }

    #[On('corporate-quote-requests-agency-filter-cleared')]
    public function clearAgencySelectionHighlight(): void
    {
        $this->selectedAgencyId = null;
    }

    public function table(Table $table): Table
    {
        return CorporateQuoteRequestsRankingTableUi::apply(
            table: $table,
            variant: 'agency',
            query: fn (): Builder => CorporateQuoteRequestsRankingQuery::agencies(
                $this->resolvedRankingFilterYear(),
                $this->resolvedRankingFilterMonth(),
            )->with('typeAgency'),
            modelClass: Agency::class,
            nameAttribute: 'name_corporative',
            nameLabel: 'Agencia',
            typeRelation: 'typeAgency',
            searchPlaceholder: 'Buscar agencia o código…',
            emptyHeading: 'Sin solicitudes por agencia',
            emptyDescription: 'Las solicitudes Dress Taylor aparecerán aquí agrupadas por agencia.',
            heading: false,
        )
            ->recordActionsColumnLabel('')
            ->recordActions([
                Action::make('filterAgents')
                    ->label('Detalles')
                    ->icon(Heroicon::ChevronRight)
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
