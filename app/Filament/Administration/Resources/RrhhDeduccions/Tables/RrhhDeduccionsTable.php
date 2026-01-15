<?php

namespace App\Filament\Administration\Resources\RrhhDeduccions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RrhhDeduccionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading("DEDUCCIONES")
            ->description("Gestion de Deducciones asociadas al cargo del empleado o del colaborador")
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),
                TextColumn::make('description')
                    ->label('Descripción')
                    ->searchable(),
                TextColumn::make('monto')
                    ->label('Monto US$')
                    ->color('warning')
                    ->searchable(),
                TextColumn::make('cargo.description')
                    ->label('Cargo')
                    ->badge()
                    ->icon('heroicon-o-clipboard-document')
                    ->searchable(),
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
