<?php

namespace App\Filament\Business\Resources\Helpdesks\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HelpdesksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('description')
                ->label('Descripción')
                    ->searchable(),
                ImageColumn::make('image')
                ->label('Imagen'),
                TextColumn::make('category')
                ->label('Categoría')
                    ->searchable(),
                TextColumn::make('module')
                ->label('Módulo')
                    ->searchable(),
                TextColumn::make('status')
                ->label('Estado')
                    ->searchable(),
                TextColumn::make('created_by')
                ->label('Creado por')
                    ->searchable(),
                TextColumn::make('updated_by')
                ->label('Actualizado por')
                    ->searchable(),
                TextColumn::make('observations')
                ->label('Observaciones')
                    ->searchable(),
                TextColumn::make('created_at')
                ->label('Creado el')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                ->label('Actualizado el')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
