<?php

namespace App\Filament\Business\Resources\TravelAgencies\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TravelAgenciesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo')
                    ->disk('public')
                    ->width(60),
                TextColumn::make('status')
                    ->label('Estado')
                    ->searchable(),
                TextColumn::make('fechaIngreso')
                    ->label('Fecha de Ingreso')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('representante')
                    ->label('Representante')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('idRepresentante')
                    ->label('ID Representante')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('FechaNacimientoRepresentante')
                    ->label('Fecha de Nacimiento del Representante')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),
                TextColumn::make('typeIdentification')
                    ->label('Tipo de Identificación')
                    ->searchable(),
                TextColumn::make('numberIdentification')
                    ->label('Número de Identificación')
                    ->searchable(),
                TextColumn::make('userPortalWeb')
                    ->label('Usuario Portal Web')
                    ->searchable(),
                TextColumn::make('aniversary')
                    ->label('Aniversario')
                    ->searchable(),
                TextColumn::make('country.name')
                    ->label('País')
                    ->searchable(),
                TextColumn::make('state.definition')
                    ->label('Estado')
                    ->searchable(),
                TextColumn::make('city.definition')
                    ->label('Ciudad')
                    ->searchable(),
                TextColumn::make('address')
                    ->label('Dirección')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable(),
                TextColumn::make('phoneAdditional')
                    ->label('Teléfono Adicional')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('userInstagram')
                    ->label('Usuario Instagram')
                    ->searchable(),
                TextColumn::make('classification')
                    ->label('Clasificación')
                    ->searchable(),
                TextColumn::make('comision')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('montoCreditoAprobado')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('nivel')
                    ->label('Nivel')
                    ->searchable(),
                TextColumn::make('agenteSuperiorNivel3')
                    ->label('Agente Superior Nivel 3')
                    ->searchable(),
                TextColumn::make('agenciaSuperiorNivel2')
                    ->label('Agencia Superior Nivel 2')
                    ->searchable(),
                TextColumn::make('agenciaPpalNivel1')
                    ->label('Agencia Principal Nivel 1')
                    ->searchable(),
                TextColumn::make('createdBy')
                    ->label('Creado por')
                    ->searchable(),
                TextColumn::make('updatedBy')
                    ->label('Actualizado por')
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
