<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\AnnualCollections\Tables;

use App\Filament\Exports\CollectionReceivableExporter;
use App\Http\Controllers\CollectionController;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\Agency;
use App\Models\Collection;
use App\Support\Affiliation\AffiliationDocumentAffiliatesCount;
use App\Support\Collections\CollectionReceivableReport;
use App\Support\SecurityAudit;
use Carbon\CarbonImmutable;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

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
            ->description('Fecha actual: '.CarbonImmutable::today()->format('d/m/Y').'. Una fila por afiliación con su próxima cuota pendiente. Use el filtro «Vencimiento» para ver o descargar por días de atraso.')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(CollectionReceivableReport::eagerLoads()))
            ->defaultSort('filter_next_payment_date', 'asc')
            ->striped()
            ->columns(self::columns())
            ->filters(self::filters(), layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->headerActions([
                self::exportAction(),
            ])
            ->recordActions([])
            ->toolbarActions([])
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
                    'EXCLUIDO' => 'Excluido',
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

    private static function exportAction(): ExportAction
    {
        return ExportAction::make('exportReceivables')
            ->label('Descargar reporte')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('success')
            ->exporter(CollectionReceivableExporter::class)
            ->formats([ExportFormat::Xlsx, ExportFormat::Csv])
            ->fileName(fn (): string => 'reporte-cxc-'.CarbonImmutable::today()->format('Y-m-d'))
            ->modalHeading('Descargar reporte de cuentas por cobrar')
            ->modalDescription('Se descargan las filas que ve en la tabla, con los filtros y la búsqueda aplicados. Recibirá una notificación cuando el archivo esté listo.')
            ->before(function (): void {
                SecurityAudit::log('AUDIT_ADMIN_RECEIVABLES_EXPORT_REQUESTED', 'administration.annual-collections.export', [
                    'panel' => 'administration',
                ], Auth::user());
            });
    }

    /**
     * Regenera el PDF del aviso de cobro de una cuota. Lo usan `AvisoCobroController`
     * y la ruta de regeneración en `routes/web.php`.
     */
    public static function runRegeneratePdf(Collection $record): bool
    {
        try {
            if ($record->type === 'AFILIACION INDIVIDUAL') {
                $address = Affiliation::where('code', $record->affiliation_code)->first();
                $array_data = [
                    'invoice_number' => $record->collection_invoice_number,
                    'emission_date' => $record->next_payment_date,
                    'full_name_ti' => $record->affiliate_full_name,
                    'ci_rif_ti' => $record->affiliate_ci_rif,
                    'address_ti' => $address?->adress_ti ?? '',
                    'phone_ti' => $record->affiliate_phone,
                    'email_ti' => $record->affiliate_email,
                    'total_amount' => $record->total_amount,
                    'plan' => $record->plan?->description,
                    'coverage' => $record->coverage?->price ?? null,
                    'frequency' => $record->payment_frequency,
                    'affiliates_count' => AffiliationDocumentAffiliatesCount::forAffiliationCode($record->affiliation_code, false),
                ];

                return CollectionController::regenerateAvisoDeCobro($array_data);
            }
            if ($record->type === 'AFILIACION CORPORATIVA') {
                $address = AffiliationCorporate::where('code', $record->affiliation_code)->first();
                $planes = AffiliationCorporate::where('code', $record->affiliation_code)->with('affiliationCorporatePlans')->first()?->toArray();
                $array_data = [
                    'invoice_number' => $record->collection_invoice_number,
                    'emission_date' => $record->next_payment_date,
                    'full_name_ti' => $record->affiliate_full_name,
                    'ci_rif_ti' => $record->affiliate_ci_rif,
                    'address_ti' => $address?->adress_ti ?? '',
                    'phone_ti' => $record->affiliate_phone,
                    'email_ti' => $record->affiliate_email,
                    'total_amount' => $record->total_amount,
                    'plan' => $planes['affiliation_corporate_plans'] ?? [],
                    'coverage' => $record->coverage?->price ?? null,
                    'frequency' => $record->payment_frequency,
                    'affiliates_count' => AffiliationDocumentAffiliatesCount::forAffiliationCode($record->affiliation_code, true),
                ];

                return CollectionController::regenerateAvisoDeCobroCorporate($array_data);
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }
}
