<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\AnnualCollections\Tables;

use App\Filament\Administration\Resources\AnnualCollections\Actions\CollectionObservationsAction;
use App\Models\Agency;
use App\Models\Collection;
use App\Support\Collections\CollectionNotice;
use App\Support\Collections\CollectionReceivableReport;
use App\Support\Exports\CollectionReceivableCsvExportService;
use App\Support\SecurityAudit;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reporte de cuentas por cobrar: una fila por afiliación con su próxima cuota
 * pendiente (cuotas reales de `collections`), con las columnas y el orden del
 * «Reporte global de cuentas por cobrar» que usa Administración.
 */
class AnnualCollectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Reporte global de cuentas por cobrar · Planes Tu Doctor en Casa')
            ->description('Fecha actual: '.CarbonImmutable::today()->format('d/m/Y').'. Una fila por afiliación con su próxima cuota pendiente. Use el filtro «Vencimiento» para ver o descargar en CSV por días de atraso.')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(CollectionReceivableReport::eagerLoads())
                ->withCount('receivableObservations'))
            ->defaultSort('filter_next_payment_date', 'asc')
            ->striped()
            ->columns(self::columns())
            ->filters(self::filters(), layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->headerActions([
                self::exportAction(),
            ])
            ->recordActions([
                CollectionObservationsAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::exportSelectedAction(),
                ]),
            ])
            ->emptyStateIcon(Heroicon::OutlinedCheckBadge)
            ->emptyStateHeading('No hay cuotas pendientes')
            ->emptyStateDescription('No hay afiliaciones con cuotas por cobrar con los filtros aplicados. Pruebe quitando filtros o cambiando el rango de vencimiento.');
    }

    /**
     * @return array<int, TextColumn>
     */
    private static function columns(): array
    {
        return [
            TextColumn::make('include_date')
                ->label('Fecha de inclusión o emisión')
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                    ->orderByRaw("STR_TO_DATE(collections.include_date, '%d/%m/%Y') ".($direction === 'desc' ? 'desc' : 'asc'))),
            TextColumn::make('affiliate_full_name')
                ->label('Afiliado o titular')
                ->weight('semibold')
                ->description(fn (Collection $record): ?string => $record->affiliation_code)
                ->wrap()
                ->searchable(['affiliate_full_name', 'affiliation_code']),
            TextColumn::make('affiliate_ci_rif')
                ->label('C.I./R.I.F.')
                ->searchable(),
            TextColumn::make('payer_name')
                ->label('Tomador')
                ->state(fn (Collection $record): ?string => CollectionReceivableReport::payerName($record))
                ->wrap()
                ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->whereHas('affiliationByCode', fn (Builder $affiliation): Builder => $affiliation->where('full_name_payer', 'like', "%{$search}%"))
                        ->orWhereHas('affiliationCorporateByCode', fn (Builder $affiliation): Builder => $affiliation->where('name_corporate', 'like', "%{$search}%"));
                })),
            TextColumn::make('payer_document')
                ->label('C.I./R.I.F. tomador')
                ->state(fn (Collection $record): ?string => CollectionReceivableReport::payerDocument($record)),
            TextColumn::make('affiliate_phone')
                ->label('Teléfono')
                ->searchable()
                ->toggleable(),
            TextColumn::make('affiliate_email')
                ->label('Email')
                ->searchable()
                ->toggleable(),
            TextColumn::make('plan_label')
                ->label('Plan')
                ->state(fn (Collection $record): ?string => CollectionReceivableReport::planLabel($record))
                ->badge()
                ->color('info'),
            TextColumn::make('persons')
                ->label('Población')
                ->alignCenter(),
            TextColumn::make('agency_label')
                ->label('Agencia')
                ->state(fn (Collection $record): ?string => CollectionReceivableReport::agencyLabel($record))
                ->wrap(),
            TextColumn::make('agent.name')
                ->label('Agente')
                ->placeholder('Sin agente')
                ->wrap(),
            TextColumn::make('annual_fee')
                ->label('Tarifa anual')
                ->state(fn (Collection $record): ?float => CollectionReceivableReport::annualFee($record))
                ->money('USD')
                ->alignEnd(),
            TextColumn::make('effective_date')
                ->label('Fecha de vigencia')
                ->state(fn (Collection $record): ?string => CollectionReceivableReport::effectiveDate($record)),
            TextColumn::make('payment_frequency')
                ->label('Fraccionamiento de cuotas')
                ->state(fn (Collection $record): ?string => CollectionReceivableReport::paymentFrequency($record))
                ->badge()
                ->color('gray'),
            TextColumn::make('installment')
                ->label('Periodos de pago')
                ->state(fn (Collection $record): ?string => CollectionReceivableReport::installmentLabel($record))
                ->alignCenter(),
            TextColumn::make('installment_amount')
                ->label('Monto de la cuota')
                ->state(fn (Collection $record): ?float => CollectionReceivableReport::installmentAmount($record))
                ->money('USD')
                ->alignEnd()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('due_date')
                ->label('Fecha de vencimiento')
                ->state(fn (Collection $record): ?string => CollectionReceivableReport::dueDate($record)?->format('d/m/Y'))
                ->weight('semibold')
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                    ->orderBy('filter_next_payment_date', $direction === 'desc' ? 'desc' : 'asc')),
            TextColumn::make('collection_status')
                ->label('Estatus de cobro')
                ->state(fn (Collection $record): string => CollectionReceivableReport::collectionStatus($record))
                ->badge()
                ->color(fn (string $state): string => $state === CollectionReceivableReport::STATUS_OVERDUE ? 'danger' : 'warning'),
            TextColumn::make('days')
                ->label('Días')
                ->state(fn (Collection $record): string => CollectionReceivableReport::daysLabel($record))
                ->color(fn (Collection $record): string => CollectionReceivableReport::collectionStatus($record) === CollectionReceivableReport::STATUS_OVERDUE ? 'danger' : 'gray')
                ->weight('semibold'),
            TextColumn::make('affiliate_status_label')
                ->label('Estatus del afiliado o titular')
                ->state(fn (Collection $record): ?string => CollectionReceivableReport::affiliateStatus($record))
                ->badge()
                ->color(fn (?string $state): string => match ($state) {
                    'ACTIVA', 'ACTIVO' => 'success',
                    'EXCLUIDA', 'EXCLUIDO', 'INACTIVA', 'INACTIVO', 'ANULADA' => 'danger',
                    default => 'warning',
                }),
        ];
    }

    /**
     * @return array<int, Filter|SelectFilter>
     */
    private static function filters(): array
    {
        return [
            SelectFilter::make('aging')
                ->label('Vencimiento')
                ->placeholder('Todas las cuotas pendientes')
                ->options(CollectionReceivableReport::agingOptions())
                ->query(fn (Builder $query, array $data): Builder => CollectionReceivableReport::applyAging($query, $data['value'] ?? null)),
            SelectFilter::make('collection_status')
                ->label('Estatus de cobro')
                ->placeholder('Por pagar y vencidas')
                ->options([
                    CollectionReceivableReport::STATUS_PENDING => 'Por pagar (aún no vence)',
                    CollectionReceivableReport::STATUS_OVERDUE => 'Vencido',
                ])
                ->query(fn (Builder $query, array $data): Builder => CollectionReceivableReport::applyCollectionStatus($query, $data['value'] ?? null)),
            SelectFilter::make('affiliate_status')
                ->label('Estatus del afiliado o titular')
                ->placeholder('Todos los estatus')
                ->multiple()
                ->options([
                    'ACTIVA' => 'Activa',
                    'PRE-APROBADA' => 'Pre-aprobada',
                ])
                ->query(fn (Builder $query, array $data): Builder => CollectionReceivableReport::applyAffiliateStatus($query, $data['values'] ?? [])),
            SelectFilter::make('code_agency')
                ->label('Agencia')
                ->relationship('agencyByCode', 'name_corporative')
                ->getOptionLabelFromRecordUsing(fn (Agency $record): string => trim($record->code.' · '.$record->name_corporative, ' ·'))
                ->searchable()
                ->preload(),
            SelectFilter::make('agent_id')
                ->label('Agente')
                ->relationship('agent', 'name')
                ->searchable()
                ->preload(),
            SelectFilter::make('plan_id')
                ->label('Plan')
                ->relationship('plan', 'description')
                ->preload(),
            Filter::make('due_between')
                ->label('Fecha de vencimiento')
                ->schema([
                    DatePicker::make('from')->label('Vence desde'),
                    DatePicker::make('until')->label('Vence hasta'),
                ])
                ->query(fn (Builder $query, array $data): Builder => CollectionReceivableReport::applyDueBetween($query, $data['from'] ?? null, $data['until'] ?? null))
                ->indicateUsing(function (array $data): array {
                    $indicators = [];

                    if ($data['from'] ?? null) {
                        $indicators['from'] = 'Vence desde '.CarbonImmutable::parse($data['from'])->format('d/m/Y');
                    }

                    if ($data['until'] ?? null) {
                        $indicators['until'] = 'Vence hasta '.CarbonImmutable::parse($data['until'])->format('d/m/Y');
                    }

                    return $indicators;
                }),
        ];
    }

    /**
     * Descarga directa en CSV de las filas de la tabla, con filtros, búsqueda y
     * orden aplicados ({@see CollectionReceivableCsvExportService}). Sin modal ni cola.
     */
    private static function exportAction(): Action
    {
        return Action::make('exportReceivables')
            ->label('Descargar CSV')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('success')
            ->action(function (HasTable $livewire): StreamedResponse {
                SecurityAudit::log('AUDIT_ADMIN_RECEIVABLES_EXPORT_REQUESTED', 'administration.annual-collections.export', [
                    'panel' => 'administration',
                    'format' => 'csv',
                ], Auth::user());

                return app(CollectionReceivableCsvExportService::class)->streamCsv($livewire->getFilteredSortedTableQuery());
            });
    }

    /**
     * CSV de las cuentas por cobrar seleccionadas, con las mismas columnas que «Descargar CSV».
     * La consulta no carga los registros en memoria: el servicio los recorre por lotes.
     */
    private static function exportSelectedAction(): BulkAction
    {
        return BulkAction::make('exportSelectedReceivables')
            ->label('Exportar CSV')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('success')
            ->fetchSelectedRecords(false)
            ->action(function (HasTable $livewire): StreamedResponse {
                SecurityAudit::log('AUDIT_ADMIN_RECEIVABLES_EXPORT_REQUESTED', 'administration.annual-collections.export-selected', [
                    'panel' => 'administration',
                    'format' => 'csv',
                    'scope' => 'selected',
                ], Auth::user());

                return app(CollectionReceivableCsvExportService::class)->streamCsv(
                    $livewire->getSelectedTableRecordsQuery(shouldFetchSelectedRecords: false),
                );
            });
    }

    /**
     * Regenera el PDF del aviso de cobro de una cuota con los datos por defecto (emisión hoy y período
     * según su frecuencia). Lo usan la ruta de regeneración y `AvisoCobroController`.
     */
    public static function runRegeneratePdf(Collection $record): bool
    {
        try {
            app(CollectionNotice::class)->regenerate($record, null, Auth::user());

            return true;
        } catch (\Throwable $exception) {
            report($exception);

            return false;
        }
    }
}
