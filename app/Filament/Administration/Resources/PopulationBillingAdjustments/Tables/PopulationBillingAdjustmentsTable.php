<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\PopulationBillingAdjustments\Tables;

use App\Filament\Administration\Resources\PopulationBillingAdjustments\Actions\PopulationBillingAdjustmentActions;
use App\Models\CorporateBillingAdjustment;
use App\Models\CorporatePopulationUpdate;
use App\Support\AffiliationCorporates\BillingAdjustments\PopulationBillingAdjuster;
use App\Support\AffiliationCorporates\BillingAdjustments\PopulationBillingCalculator;
use App\Support\AffiliationCorporates\BillingAdjustments\PopulationBillingTray;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Bandeja de cargas de población: cuáles faltan por facturar la diferencia y cómo quedó cada
 * ajuste aplicado.
 */
class PopulationBillingAdjustmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Cargas de población corporativa')
            ->description('Una fila por carga de población aplicada en Negocios. «Por ajustar» = la diferencia todavía no se ha facturado. Pulse «Calcular y aplicar ajuste» para ver el cálculo paso a paso antes de guardar.')
            ->defaultSort('applied_at', 'desc')
            ->searchPlaceholder('Buscar por afiliación o empresa')
            ->searchDebounce('350ms')
            ->striped()
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->recordUrl(fn (CorporatePopulationUpdate $record): string => \App\Filament\Administration\Resources\PopulationBillingAdjustments\PopulationBillingAdjustmentResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('affiliationCorporate.code')
                    ->label('Afiliación')
                    ->weight('semibold')
                    ->copyable()
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('affiliationCorporate', fn (Builder $owner): Builder => $owner
                        ->where('code', 'like', '%'.$search.'%')
                        ->orWhere('name_corporate', 'like', '%'.$search.'%')))
                    ->description(fn (CorporatePopulationUpdate $record): string => (string) ($record->affiliationCorporate?->name_corporate ?? '')),
                TextColumn::make('applied_at')
                    ->label('Población aplicada')
                    ->date('d/m/Y')
                    ->sortable()
                    ->description(fn (CorporatePopulationUpdate $record): string => 'Por '.($record->approved_by_name ?: '—')),
                TextColumn::make('movements')
                    ->label('Movimientos')
                    ->state(fn (CorporatePopulationUpdate $record): string => (int) $record->persons_added_count.' '.((int) $record->persons_added_count === 1 ? 'alta' : 'altas')
                        .' · '.(int) $record->persons_removed_count.' '.((int) $record->persons_removed_count === 1 ? 'baja' : 'bajas'))
                    ->tooltip('Altas = altas nuevas y reactivaciones. Bajas = bajas y duplicados depurados.'),
                TextColumn::make('billing_state')
                    ->label('Facturación')
                    ->badge()
                    ->state(fn (CorporatePopulationUpdate $record): string => PopulationBillingTray::stateOptions()[PopulationBillingTray::state($record)])
                    ->color(fn (CorporatePopulationUpdate $record): string => match (PopulationBillingTray::state($record)) {
                        PopulationBillingTray::STATE_PENDING => 'warning',
                        PopulationBillingTray::STATE_ADJUSTED => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('effective_date')
                    ->label('Fecha efectiva')
                    ->placeholder('—')
                    ->state(fn (CorporatePopulationUpdate $record): ?string => PopulationBillingTray::appliedAdjustment($record)?->effective_date?->format('d/m/Y')),
                TextColumn::make('charge')
                    ->label('Aviso por ajuste')
                    ->alignEnd()
                    ->placeholder('—')
                    ->state(fn (CorporatePopulationUpdate $record): ?string => ($adjustment = PopulationBillingTray::appliedAdjustment($record)) instanceof CorporateBillingAdjustment
                        ? PopulationBillingCalculator::money(PopulationBillingAdjuster::cents($adjustment->charge_amount))
                        : null)
                    ->description(fn (CorporatePopulationUpdate $record): ?string => ($charge = PopulationBillingTray::appliedAdjustment($record)?->chargeCollection) !== null
                        ? $charge->collection_invoice_number.' · '.$charge->status
                        : null),
                TextColumn::make('installment_delta')
                    ->label('Cambio por cuota')
                    ->alignEnd()
                    ->placeholder('—')
                    ->state(fn (CorporatePopulationUpdate $record): ?string => ($adjustment = PopulationBillingTray::appliedAdjustment($record)) instanceof CorporateBillingAdjustment
                        ? PopulationBillingCalculator::money(PopulationBillingAdjuster::cents($adjustment->installment_delta))
                        : null)
                    ->description(fn (CorporatePopulationUpdate $record): ?string => ($adjustment = PopulationBillingTray::appliedAdjustment($record)) instanceof CorporateBillingAdjustment
                        ? 'en '.$adjustment->installments_adjusted.' '.($adjustment->installments_adjusted === 1 ? 'cuota' : 'cuotas')
                        : null),
            ])
            ->filters([
                SelectFilter::make('billing_state')
                    ->label('Facturación')
                    ->options(PopulationBillingTray::stateOptions())
                    ->placeholder('Todas')
                    ->query(fn (Builder $query, array $data): Builder => PopulationBillingTray::applyStateFilter($query, $data['value'] ?? null)),
            ])
            ->recordActions([
                PopulationBillingAdjustmentActions::apply(),
                ActionGroup::make([
                    ViewAction::make()->label('Ver cálculo'),
                    PopulationBillingAdjustmentActions::previewPdf(),
                    PopulationBillingAdjustmentActions::downloadPdf(),
                    PopulationBillingAdjustmentActions::revert(),
                ])
                    ->label('Acciones')
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->button()
                    ->color('gray'),
            ])
            ->emptyStateHeading('No hay cargas de población')
            ->emptyStateDescription('Cuando Negocios aplique una actualización de población corporativa, aparecerá aquí para facturar la diferencia.')
            ->emptyStateIcon(Heroicon::OutlinedUsers);
    }
}
