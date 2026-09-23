<?php

namespace App\Filament\Marketing\Resources\TravelAgencies\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;

class TravelAgenciesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),
                TextColumn::make('id_agencia')
                    ->label('ID agencia')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('id_de_agente')
                    ->label('ID de agente')
                    ->searchable()
                    ->toggleable(),
                TextInputColumn::make('aniversary')
                    ->prefixIcon('heroicon-m-calendar')
                    ->label('Aniversario(dd/mm/yyyy)')
                    ->searchable(),
                TextInputColumn::make('phone')
                    ->prefixIcon('heroicon-m-phone')
                    ->label('Teléfono')
                    ->searchable(),
                TextInputColumn::make('phoneAdditional')
                    ->prefixIcon('heroicon-m-phone')
                    ->label('Teléfono Adicional')
                    ->searchable(),
                TextInputColumn::make('email')
                    ->prefixIcon('fontisto-email')
                    ->label('Email address')
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
