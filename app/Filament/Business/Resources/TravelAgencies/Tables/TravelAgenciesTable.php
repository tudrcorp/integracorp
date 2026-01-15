<?php

namespace App\Filament\Business\Resources\TravelAgencies\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TravelAgenciesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('fechaIngreso')
                    ->searchable(),
                TextColumn::make('representante')
                    ->searchable(),
                TextColumn::make('idRepresentante')
                    ->searchable(),
                TextColumn::make('FechaNacimientoRepresentante')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('typeIdentification')
                    ->searchable(),
                TextColumn::make('numberIdentification')
                    ->searchable(),
                TextColumn::make('userPortalWeb')
                    ->searchable(),
                TextColumn::make('aniversary')
                    ->searchable(),
                TextColumn::make('country')
                    ->searchable(),
                TextColumn::make('state')
                    ->searchable(),
                TextColumn::make('city')
                    ->searchable(),
                TextColumn::make('address')
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('phoneAdditional')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('userInstagram')
                    ->searchable(),
                TextColumn::make('classification')
                    ->searchable(),
                TextColumn::make('comision')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('montoCreditoAprobado')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('nivel')
                    ->searchable(),
                TextColumn::make('agenteSuperiorNivel3')
                    ->searchable(),
                TextColumn::make('agenciaSuperiorNivel2')
                    ->searchable(),
                TextColumn::make('agenciaPpalNivel1')
                    ->searchable(),
                TextColumn::make('createdBy')
                    ->searchable(),
                TextColumn::make('updatedBy')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
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
