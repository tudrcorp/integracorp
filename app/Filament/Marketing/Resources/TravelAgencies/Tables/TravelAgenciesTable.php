<?php

namespace App\Filament\Marketing\Resources\TravelAgencies\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;

class TravelAgenciesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),
                TextColumn::make('typeIdentification')
                    ->label('Tipo de Identificación')
                    ->searchable(),
                TextColumn::make('numberIdentification')
                    ->label('Número de Identificación')
                    ->searchable(),
                TextInputColumn::make('aniversary')
                    ->label('Aniversario')
                    ->searchable(),
                TextInputColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable(),
                TextInputColumn::make('phoneAdditional')
                    ->label('Teléfono Adicional')
                    ->searchable(),
                TextInputColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('nameSecundario')
                    ->label('Nombre Secundario')
                    ->searchable(),
                TextInputColumn::make('emailSecundario')
                    ->label('Email Secundario')
                    ->searchable(),
                TextInputColumn::make('phoneSecundario')
                    ->label('Teléfono Secundario')
                    ->searchable(),
                TextInputColumn::make('fechaNacimientoSecundario')
                    ->label('Fecha de Nacimiento Secundario')
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
