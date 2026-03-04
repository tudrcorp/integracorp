<?php

namespace App\Filament\Operations\Resources\OperationInventoryEntries\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OperationInventoryEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('ENTRADAS DE INVENTARIO')
            ->description('Lista de entradas de inventario')
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
                TextColumn::make('type_entry')
                    ->label('Tipo de entrada')
                    ->badge()
                    ->color(fn ($record) => $record->type_entry == 'PRIMERA CARGA' ? 'success' : 'warning')
                    ->icon(fn ($record) => $record->type_entry == 'PRIMERA CARGA' ? 'heroicon-o-clipboard-document-check' : 'heroicon-o-truck')
                    ->iconColor(fn ($record) => $record->type_entry == 'PRIMERA CARGA' ? 'success' : 'warning')
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
            ]);
    }
}
