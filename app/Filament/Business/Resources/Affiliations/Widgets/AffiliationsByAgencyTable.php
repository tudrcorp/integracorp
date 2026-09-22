<?php

namespace App\Filament\Business\Resources\Affiliations\Widgets;

use App\Filament\Business\Resources\Affiliations\Pages\ListAffiliations;
use App\Filament\Business\Resources\Affiliations\Widgets\Concerns\InteractsWithAffiliationsRankingTable;
use App\Models\Agency;
use App\Support\Affiliations\AffiliationsRankingQuery;
use App\Support\Filament\AffiliationCorporatesRankingTableUi;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;

class AffiliationsByAgencyTable extends TableWidget
{
    use InteractsWithAffiliationsRankingTable;

    protected string $view = 'filament.widgets.affiliations-ranking-table-widget';

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 1;

    public ?int $selectedAgencyId = null;

    public ?int $selectedAgencyIdForUnassignedAffiliations = null;

    protected function rankingTableVariant(): string
    {
        return 'agency';
    }

    public function mount(): void
    {
        $this->bootInteractsWithAffiliationsRankingTable();
        $this->dispatchPeriodToAgentTable();
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

        $this->dispatch('affiliations-agent-filter-start');
        $this->dispatchPeriodToAgentTable();
    }

    protected function dispatchPeriodToAgentTable(): void
    {
        $this->dispatch(
            'affiliations-period-changed',
            year: $this->normalizedFilterYear(),
            month: $this->normalizedFilterMonth(),
        )->to(AffiliationsByAgentTable::class);
    }

    public function selectAgency(Agency $agency): void
    {
        $this->dispatch('affiliations-agent-filter-start');

        if ($this->selectedAgencyId === $agency->id) {
            $this->selectedAgencyId = null;
            $this->dispatch('affiliations-agency-filter-cleared')
                ->to(AffiliationsByAgentTable::class);

            return;
        }

        $this->selectedAgencyId = $agency->id;

        $this->dispatch(
            'affiliations-agency-selected',
            agencyCode: $agency->code,
            agencyName: $agency->name_corporative,
        )->to(AffiliationsByAgentTable::class);
    }

    #[On('affiliations-agency-filter-cleared')]
    public function clearAgencySelectionHighlight(): void
    {
        $this->selectedAgencyId = null;
    }

    public function viewAgencyAffiliationsWithoutAgent(Agency $agency): void
    {
        $this->selectedAgencyIdForUnassignedAffiliations = $agency->id;

        $this->dispatch(
            'affiliations-filter-by-agency-without-agent',
            agencyCode: $agency->code,
            agencyName: $agency->name_corporative,
        )->to(ListAffiliations::class);
    }

    public function table(Table $table): Table
    {
        return AffiliationCorporatesRankingTableUi::apply(
            table: $table,
            variant: 'agency',
            query: fn (): Builder => AffiliationsRankingQuery::agencies(
                $this->resolvedRankingFilterYear(),
                $this->resolvedRankingFilterMonth(),
            )->with('typeAgency'),
            modelClass: Agency::class,
            nameAttribute: 'name_corporative',
            nameLabel: 'Agencia',
            typeRelation: 'typeAgency',
            searchPlaceholder: 'Buscar agencia o código…',
            emptyHeading: 'Sin afiliaciones por agencia',
            emptyDescription: 'Las afiliaciones individuales aparecerán aquí agrupadas por agencia.',
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
                Action::make('viewAffiliationsWithoutAgent')
                    ->label('Venta Directa')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color(fn (Agency $record): string => $this->selectedAgencyIdForUnassignedAffiliations === $record->id ? 'warning' : 'gray')
                    ->extraAttributes(fn (Agency $record): array => [
                        'class' => $this->selectedAgencyIdForUnassignedAffiliations === $record->id
                            ? 'iq-ranking-unassigned-btn iq-ranking-unassigned-btn--active'
                            : 'iq-ranking-unassigned-btn',
                    ])
                    ->action(fn (Agency $record): mixed => $this->viewAgencyAffiliationsWithoutAgent($record)),
            ])
            ->recordClasses(fn (Agency $record): array => (
                $this->selectedAgencyId === $record->id
                || $this->selectedAgencyIdForUnassignedAffiliations === $record->id
            )
                ? ['iq-ranking-row--selected']
                : []);
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

        if ($cachedRecord instanceof Agency) {
            return $cachedRecord;
        }

        return Agency::query()->find($key);
    }
}
