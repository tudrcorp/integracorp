<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\PopulationBillingAdjustments\RelationManagers;

use App\Models\CorporateBillingAdjustmentLine;
use App\Models\CorporatePopulationUpdate;
use App\Support\AffiliationCorporates\BillingAdjustments\PopulationBillingTray;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Una fila por persona del ajuste mostrado: de dónde sale cada centavo.
 */
class AdjustmentLinesRelationManager extends RelationManager
{
    protected static string $relationship = 'billingAdjustmentLines';

    protected static ?string $title = 'Personas del ajuste';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof CorporatePopulationUpdate && PopulationBillingTray::displayedAdjustment($ownerRecord) !== null;
    }

    public function table(Table $table): Table
    {
        /** @var CorporatePopulationUpdate $owner */
        $owner = $this->getOwnerRecord();
        $adjustmentId = PopulationBillingTray::displayedAdjustment($owner)?->getKey();

        return $table
            ->heading('Personas del ajuste')
            ->description('Altas: pagan del período ya facturado la tarifa por cuota × días ÷ días del período. Bajas: sin crédito; dejan de cobrarse en las cuotas por pagar.')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('corporate_billing_adjustment_lines.corporate_billing_adjustment_id', $adjustmentId ?? 0))
            ->defaultSort('full_name')
            ->searchPlaceholder('Buscar por nombre o documento')
            ->searchDebounce('350ms')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('kind')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => $state === CorporateBillingAdjustmentLine::KIND_ADD ? 'success' : 'gray')
                    ->description(fn (CorporateBillingAdjustmentLine $record): string => (string) $record->movement),
                TextColumn::make('full_name')->label('Nombre')->searchable()->wrap(),
                TextColumn::make('document')->label('Documento')->searchable()->placeholder('—'),
                TextColumn::make('annual_fee')->label('Tarifa anual')->money('USD')->alignEnd(),
                TextColumn::make('period_fee')->label('Por cuota')->money('USD')->alignEnd(),
                TextColumn::make('days')
                    ->label('Días cobrados')
                    ->alignEnd()
                    ->state(fn (CorporateBillingAdjustmentLine $record): string => $record->kind === CorporateBillingAdjustmentLine::KIND_ADD && $record->period_days > 0
                        ? $record->days.' de '.$record->period_days
                        : '—')
                    ->description(fn (CorporateBillingAdjustmentLine $record): ?string => $record->covered_from !== null
                        ? 'del '.$record->covered_from->format('d/m/Y').' al '.$record->covered_until?->format('d/m/Y')
                        : null),
                TextColumn::make('prorated_amount')->label('Cobro aparte')->money('USD')->alignEnd(),
                TextColumn::make('installment_delta')->label('Cambio por cuota')->money('USD')->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('kind')
                    ->label('Tipo')
                    ->options([CorporateBillingAdjustmentLine::KIND_ADD => 'Altas', CorporateBillingAdjustmentLine::KIND_REMOVE => 'Bajas']),
            ]);
    }
}
