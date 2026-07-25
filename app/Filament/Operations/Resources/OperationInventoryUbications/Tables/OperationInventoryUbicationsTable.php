<?php

namespace App\Filament\Operations\Resources\OperationInventoryUbications\Tables;

use App\Models\OperationInventoryUbication;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class OperationInventoryUbicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Almacenes')
            ->description('Catálogo de ubicaciones para el manejo de inventario Diagnomovil.')
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->icon('heroicon-o-building-storefront')
                    ->weight(FontWeight::SemiBold)
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->lineClamp(2)
                    ->tooltip(fn (OperationInventoryUbication $record): string => trim((string) $record->name)),
                TextColumn::make('state.definition')
                    ->label('Estado')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('address')
                    ->label('Dirección')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->lineClamp(2)
                    ->placeholder('—')
                    ->tooltip(fn (OperationInventoryUbication $record): ?string => filled($record->address)
                        ? trim((string) $record->address)
                        : null),
                TextColumn::make('inventories_count')
                    ->label('Ítems')
                    ->counts('inventories')
                    ->sortable()
                    ->badge()
                    ->color('gray'),
                TextColumn::make('is_active')
                    ->label('Estatus')
                    ->badge()
                    ->formatStateUsing(fn (?bool $state): string => $state ? 'Activo' : 'Inactivo')
                    ->color(fn (?bool $state): string => $state ? 'success' : 'danger')
                    ->sortable(),
                TextColumn::make('created_by')
                    ->label('Creado por')
                    ->searchable()
                    ->icon('heroicon-m-user')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (OperationInventoryUbication $record): string => $record->created_at?->diffForHumans() ?? '')
                    ->sortable()
                    ->icon('heroicon-m-calendar-days')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (OperationInventoryUbication $record): string => $record->updated_at?->diffForHumans() ?? '')
                    ->sortable()
                    ->icon('heroicon-m-calendar-days')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('state_id')
                    ->label('Estado')
                    ->relationship('state', 'definition')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('is_active')
                    ->label('Estatus')
                    ->trueLabel('Activos')
                    ->falseLabel('Inactivos')
                    ->placeholder('Todos'),
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
