<?php

namespace App\Filament\Operations\Resources\OperationInventoryMovements\Tables;

use App\Models\OperationInventoryMovement;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OperationInventoryMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Movimientos de inventario')
            ->description('Despachos asociados a telemedicina, pacientes y unidades de negocio.')
            ->defaultSort('created_at', 'desc')
            ->searchable()
            ->searchPlaceholder('Buscar por código, producto, caso, paciente o tipo…')
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->deferFilters(false)
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Sin movimientos de inventario')
            ->emptyStateDescription('Cuando se registre un despacho desde telemedicina aparecerá aquí.')
            ->emptyStateIcon(Heroicon::OutlinedArrowsRightLeft)
            ->striped()
            ->columns([
                TextColumn::make('operationInventory.product.code')
                    ->label('Código')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable()
                    ->getStateUsing(fn (OperationInventoryMovement $record): string => $record->operationInventory?->product?->code
                        ?? $record->operationInventory?->barcode
                        ?? '—'),
                TextColumn::make('operationInventory.name')
                    ->label('Producto')
                    ->icon(Heroicon::OutlinedCube)
                    ->weight(FontWeight::SemiBold)
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->lineClamp(2)
                    ->placeholder('—'),
                TextColumn::make('operationInventory.ubicationRelation.name')
                    ->label('Almacén')
                    ->badge()
                    ->color('info')
                    ->icon(Heroicon::OutlinedBuildingStorefront)
                    ->getStateUsing(fn (OperationInventoryMovement $record): string => $record->operationInventory?->ubicationRelation?->name
                        ?? $record->operationInventory?->ubication
                        ?? '—'),
                TextColumn::make('quantity')
                    ->label('Cantidad')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn (mixed $state, OperationInventoryMovement $record): string => number_format((float) $state).' '.(filled($record->unit) ? $record->unit : 'und.')),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (?string $state): string => match (mb_strtoupper(trim((string) ($state ?? '')))) {
                        'SALIDA TELEMEDICINA' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn (?string $state): string => match (mb_strtoupper(trim((string) ($state ?? '')))) {
                        'SALIDA TELEMEDICINA' => 'heroicon-o-heart',
                        default => 'heroicon-o-arrows-right-left',
                    })
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (?string $state): string => match (mb_strtoupper(trim((string) ($state ?? '')))) {
                        'ACTIVO', 'COMPLETADO', 'FINALIZADO', 'DESPACHADO' => 'success',
                        'PENDIENTE' => 'warning',
                        'ANULADO', 'CANCELADO' => 'danger',
                        default => 'gray',
                    })
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('telemedicineCase.code')
                    ->label('Nº caso')
                    ->badge()
                    ->color('primary')
                    ->icon('healthicons-f-health-literacy')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? mb_strtoupper((string) $state) : '—'),
                TextColumn::make('telemedicinePatient.full_name')
                    ->label('Paciente')
                    ->searchable()
                    ->toggleable()
                    ->placeholder('—'),
                TextColumn::make('telemedicineDoctor.full_name')
                    ->label('Doctor')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
                TextColumn::make('created_by')
                    ->label('Registrado por')
                    ->icon(Heroicon::User)
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (OperationInventoryMovement $record): string => $record->created_at?->diffForHumans() ?? '')
                    ->sortable()
                    ->icon(Heroicon::CalendarDays),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(fn (): array => OperationInventoryMovement::query()
                        ->whereNotNull('type')
                        ->where('type', '!=', '')
                        ->distinct()
                        ->orderBy('type')
                        ->pluck('type', 'type')
                        ->all()),
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(fn (): array => OperationInventoryMovement::query()
                        ->whereNotNull('status')
                        ->where('status', '!=', '')
                        ->distinct()
                        ->orderBy('status')
                        ->pluck('status', 'status')
                        ->all()),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('info')
                    ->extraAttributes([
                        'class' => FilamentIosButton::extraClassForFilamentColor('info'),
                    ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Eliminar seleccionados'),
                ]),
            ]);
    }
}
