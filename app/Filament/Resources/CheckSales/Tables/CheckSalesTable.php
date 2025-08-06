<?php

namespace App\Filament\Resources\CheckSales\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CheckSalesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fecha')
                    ->searchable(),
                TextColumn::make('agente')
                    ->searchable(),
                TextColumn::make('contacto')
                    ->searchable(),
                TextColumn::make('rif')
                    ->searchable(),
                TextColumn::make('telefono')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('producto')
                    ->searchable()
                    ->badge(),
                TextColumn::make('servicio')
                    ->searchable(),
                TextColumn::make('cobertura')
                    ->searchable(),
                TextColumn::make('poblacion')
                    ->searchable(),
                TextColumn::make('enero')
                    ->searchable(),
                TextColumn::make('febrero')
                    ->searchable(),
                TextColumn::make('marzo')
                    ->searchable(),
                TextColumn::make('abril')
                    ->searchable(),
                TextColumn::make('mayo')
                    ->searchable(),
                TextColumn::make('junio')
                    ->searchable(),
                TextColumn::make('julio')
                    ->searchable(),
                TextColumn::make('agosto')
                    ->searchable(),
                TextColumn::make('septiembre')
                    ->searchable(),
                TextColumn::make('octubre')
                    ->searchable(),
                TextColumn::make('noviembre')
                    ->searchable(),
                TextColumn::make('diciembre')
                    ->searchable(),
                TextColumn::make('monto_pagado')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('población')
                    ->numeric()
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