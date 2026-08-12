<?php

namespace App\Filament\Business\Resources\AffiliationCorporates\Widgets;

use App\Filament\Business\Resources\AffiliationCorporates\Widgets\Concerns\InteractsWithAffiliationCorporatesRankingTable;
use App\Models\Agency;
use App\Support\AffiliationCorporates\AffiliationCorporatesRankingQuery;
use App\Support\Filament\AffiliationCorporatesRankingTableUi;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;

class AffiliationCorporatesByAgencyTable extends TableWidget
{
    use InteractsWithAffiliationCorporatesRankingTable;

    protected string $view = 'filament.widgets.affiliation-corporates-ranking-table-widget';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    public ?int $selectedAgencyId = null;

    protected function rankingTableVariant(): string
    {
        return 'agency';
    }

    public function mount(): void
    {
        $this->bootInteractsWithAffiliationCorporatesRankingTable();
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

        $this->dispatch('affiliation-corporates-agent-filter-start');
        $this->dispatch(
            'affiliation-corporates-period-changed',
            year: (string) $this->resolvedRankingFilterYear(),
            month: (string) ((int) $this->filterMonth),
        )->to(AffiliationCorporatesByAgentTable::class);
    }

    public function selectAgency(Agency $agency): void
    {
        $this->dispatch('affiliation-corporates-agent-filter-start');

        if ($this->selectedAgencyId === $agency->id) {
            $this->selectedAgencyId = null;
            $this->dispatch('affiliation-corporates-agency-filter-cleared')
                ->to(AffiliationCorporatesByAgentTable::class);

            return;
        }

        $this->selectedAgencyId = $agency->id;

        $this->dispatch(
            'affiliation-corporates-agency-selected',
            agencyCode: $agency->code,
            agencyName: $agency->name_corporative,
        )->to(AffiliationCorporatesByAgentTable::class);
    }

    #[On('affiliation-corporates-agency-filter-cleared')]
    public function clearAgencySelectionHighlight(): void
    {
        $this->selectedAgencyId = null;
    }

    public function table(Table $table): Table
    {
        return AffiliationCorporatesRankingTableUi::apply(
            table: $table,
            variant: 'agency',
            query: fn (): Builder => AffiliationCorporatesRankingQuery::agencies(
                $this->resolvedRankingFilterYear(),
                $this->resolvedRankingFilterMonth(),
            )->with('typeAgency'),
            modelClass: Agency::class,
            nameAttribute: 'name_corporative',
            nameLabel: 'Agencia',
            typeRelation: 'typeAgency',
            searchPlaceholder: 'Buscar agencia o código…',
            emptyHeading: 'Sin afiliaciones por agencia',
            emptyDescription: 'Las afiliaciones corporativas aparecerán aquí agrupadas por agencia.',
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
