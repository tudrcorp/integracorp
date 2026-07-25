<?php

namespace App\Filament\Operations\Resources\OperationInventoryMovements\Tables;

use App\Models\OperationInventoryMovement;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OperationInventoryMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Movimientos de inventario')
            ->description('Despachos y movimientos asociados a telemedicina y unidades de negocio.')
            ->defaultSort('created_at', 'desc')
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
                    ->icon('heroicon-o-cube')
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
                    ->color('gray')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (?string $state): string => match (mb_strtoupper(trim((string) ($state ?? '')))) {
                        'ACTIVO', 'COMPLETADO', 'FINALIZADO' => 'success',
                        'PENDIENTE' => 'warning',
                        'ANULADO', 'CANCELADO' => 'danger',
                        default => 'gray',
                    })
                    ->searchable()
                    ->placeholder('—'),
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
                    ->icon('heroicon-m-user')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (OperationInventoryMovement $record): string => $record->created_at?->diffForHumans() ?? '')
                    ->sortable()
                    ->icon('heroicon-m-calendar-days'),
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
                ViewAction::make()->label('Ver'),
                EditAction::make()->label('Editar'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Eliminar seleccionados'),
                ]),
            ]);
    }
}
