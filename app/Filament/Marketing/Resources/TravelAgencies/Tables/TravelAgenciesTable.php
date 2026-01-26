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
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),
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
