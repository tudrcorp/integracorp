<?php

namespace App\Filament\Operations\Resources\AffiliateCorporates\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AffiliateCorporatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('first_name')
                    ->label('Nombre y Apellido')
                    ->searchable(),
                TextColumn::make('nro_identificacion')
                    ->label('Nro Identificacion')
                    ->searchable(),
                TextColumn::make('birth_date')
                    ->label('Fecha Nacimiento')
                    ->searchable(),
                TextColumn::make('age')
                    ->label('Edad')
                    ->searchable(),
                TextColumn::make('sex')
                    ->label('Sexo')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Telefono')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('full_name_emergency')
                    ->label('Nombre Emergencia')
                    ->searchable(),
                TextColumn::make('phone_emergency')
                    ->label('Telefono Emergencia')
                    ->searchable(),
                TextColumn::make('plan.description')
                    ->label('Plan')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('coverage.price')
                    ->label('Cobertura')
                    ->prefix('$')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make()
                ->icon('heroicon-o-eye')
                ->color('info')
                ->label('Ver Detalles'),
                // EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // DeleteBulkAction::make(),
                ]),
            ]);
    }
}
