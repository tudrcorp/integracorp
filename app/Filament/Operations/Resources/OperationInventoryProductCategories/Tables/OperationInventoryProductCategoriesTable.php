<?php

namespace App\Filament\Operations\Resources\OperationInventoryProductCategories\Tables;

use App\Models\OperationInventoryProductCategory;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class OperationInventoryProductCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Categorías')
            ->description('Clasificación de productos del inventario Diagnomovil.')
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->icon('heroicon-o-tag')
                    ->weight(FontWeight::SemiBold)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Descripción')
                    ->searchable()
                    ->wrap()
                    ->lineClamp(2)
                    ->placeholder('—')
                    ->tooltip(fn (OperationInventoryProductCategory $record): ?string => filled($record->description)
                        ? trim((string) $record->description)
                        : null),
                TextColumn::make('products_count')
                    ->label('Productos')
                    ->counts('products')
                    ->sortable()
                    ->badge()
                    ->color('gray'),
                IconColumn::make('is_active')
                    ->label('Activa')
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
                    ->description(fn (OperationInventoryProductCategory $record): string => $record->created_at?->diffForHumans() ?? '')
                    ->sortable()
                    ->icon('heroicon-m-calendar-days')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (OperationInventoryProductCategory $record): string => $record->updated_at?->diffForHumans() ?? '')
                    ->sortable()
                    ->icon('heroicon-m-calendar-days')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->trueLabel('Activas')
                    ->falseLabel('Inactivas')
                    ->placeholder('Todas'),
            ])
            ->recordActions([
                ViewAction::make()->label('Ver'),
                EditAction::make()->label('Editar'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Eliminar seleccionadas'),
                ]),
            ]);
    }
}
