<?php

namespace App\Filament\Marketing\Resources\RrhhColaboradors\Tables;

use App\Models\RrhhCargo;
use App\Models\RrhhDepartamento;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;

class RrhhColaboradorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fullName')
                    ->searchable(),
                SelectColumn::make('departamento_id')
                    ->options(RrhhDepartamento::all()->pluck('description', 'id')->toArray()),
                SelectColumn::make('cargo_id')
                    ->options(RrhhCargo::all()->pluck('description', 'id')->toArray()),
                TextInputColumn::make('fechaNacimiento')
                    ->searchable(),
                TextInputColumn::make('fechaIngreso')
                    ->searchable(),
                TextInputColumn::make('telefono')
                    ->searchable(),
                TextInputColumn::make('telefonoCorporativo')
                    ->searchable(),
                TextInputColumn::make('emailCorporativo')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // DeleteBulkAction::make(),
                ]),
            ]);
    }
}
