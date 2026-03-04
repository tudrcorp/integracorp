<?php

namespace App\Filament\Operations\Resources\OperationInventoryOutflows\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OperationInventoryOutflowsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('SALIDAS DE INVENTARIO')
            ->description('Lista de salidas de inventario')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('operation_inventory_id')
                    ->prefix('INV-000')
                    ->label('Inventario')
                    ->numeric()
                    ->badge()
                    ->color('azulOscuro')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type_outflow')
                    ->label('Tipo de salida')
                    ->badge()
                    ->color(fn($record) => $record->type_entry == 'PRIMERA CARGA' ? 'success' : 'warning')
                    ->icon(fn($record) => $record->type_entry == 'PRIMERA CARGA' ? 'heroicon-o-clipboard-document-check' : 'heroicon-o-truck')
                    ->iconColor(fn($record) => $record->type_entry == 'PRIMERA CARGA' ? 'success' : 'warning')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->label('Cantidad')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('operationInventory.operationInventoryType.name')
                    ->label('Tipo')
                    ->badge()
                    ->color('azulOscuro')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('created_by')
                    ->label('Registrado por')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Fecha de registro')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
