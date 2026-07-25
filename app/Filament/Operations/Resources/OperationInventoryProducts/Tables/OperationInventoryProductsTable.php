<?php

namespace App\Filament\Operations\Resources\OperationInventoryProducts\Tables;

use App\Enums\OperationInventoryProductPresentation;
use App\Filament\Operations\Resources\OperationInventoryProducts\Actions\BulkAdjustProductExistenceActions;
use App\Filament\Operations\Resources\OperationInventoryProducts\Actions\ExportProductsCsvBulkAction;
use App\Models\OperationInventoryProduct;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class OperationInventoryProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Productos')
            ->description('Catálogo de productos para el inventario Diagnomovil.')
            ->defaultSort('name')
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->icon('heroicon-o-cube')
                    ->weight(FontWeight::SemiBold)
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->lineClamp(2)
                    ->tooltip(fn (OperationInventoryProduct $record): string => trim((string) $record->name)),
                TextColumn::make('category.name')
                    ->label('Categoría')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('cost')
                    ->label('Costo')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('unit')
                    ->label('Unidad')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),
                TextColumn::make('presentation')
                    ->label('Presentación')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => OperationInventoryProductPresentation::labelFromMixed($state))
                    ->color(fn (mixed $state): string => OperationInventoryProductPresentation::filamentColorFromMixed($state))
                    ->sortable(),
                TextColumn::make('stocks_sum_existence')
                    ->label('Existencia total')
                    ->sum('stocks', 'existence')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color(fn (mixed $state): string => (int) $state > 0 ? 'success' : 'gray')
                    ->suffix(' und.')
                    ->placeholder('0'),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
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
                    ->description(fn (OperationInventoryProduct $record): string => $record->created_at?->diffForHumans() ?? '')
                    ->sortable()
                    ->icon('heroicon-m-calendar-days')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (OperationInventoryProduct $record): string => $record->updated_at?->diffForHumans() ?? '')
                    ->sortable()
                    ->icon('heroicon-m-calendar-days')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('operation_inventory_product_category_id')
                    ->label('Categoría')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('presentation')
                    ->label('Presentación')
                    ->options(OperationInventoryProductPresentation::options()),
                TernaryFilter::make('is_active')
                    ->label('Estado')
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
                    ExportProductsCsvBulkAction::make(),
                    BulkAdjustProductExistenceActions::increase(),
                    BulkAdjustProductExistenceActions::decrease(),
                    DeleteBulkAction::make()->label('Eliminar seleccionados'),
                ]),
            ]);
    }
}
