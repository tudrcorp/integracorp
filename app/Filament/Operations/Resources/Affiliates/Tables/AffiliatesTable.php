<?php

namespace App\Filament\Operations\Resources\Affiliates\Tables;

use App\Models\Affiliate;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AffiliatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(
                Affiliate::query()->where('status', 'ACTIVO')
            )
            ->columns([
                TextColumn::make('full_name')
                    ->label('Nombre')
                    ->searchable(),
                TextColumn::make('nro_identificacion')
                    ->label('Nro Identificacion')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Telefono')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('sex')
                    ->label('Sexo')
                    ->searchable(),
                TextColumn::make('birth_date')
                    ->label('Fecha Nacimiento')
                    ->searchable(),
                TextColumn::make('age')
                    ->label('Edad')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->searchable(), 
                TextColumn::make('country.name')
                    ->label('Pais')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('state.definition')
                    ->label('Estado')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('city.definition')
                    ->label('Ciudad')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('region')
                    ->label('Region')
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
                ->label('Ver Detalles')
                ->color('primary'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                ]),
            ]);
    }
}
