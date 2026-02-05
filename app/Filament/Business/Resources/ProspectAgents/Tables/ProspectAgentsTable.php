<?php

namespace App\Filament\Business\Resources\ProspectAgents\Tables;

use App\Models\ProspectAgent;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProspectAgentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('PROSPECTOS')
            ->description('Lista de prospectos para agentes de TuDrGroup')
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre y Apellido')
                    ->icon('heroicon-o-user')
                    ->badge()
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->searchable(),
                TextColumn::make('phone_1')
                    ->label('Telefono Principal')
                    ->searchable(),
                TextColumn::make('phone_2')
                    ->label('Telefono Alternativo')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Correo Electronico')
                    ->searchable(),
                TextColumn::make('state.definition')
                    ->label('Estado')
                    ->searchable(),
                TextColumn::make('city.definition')
                    ->label('Ciudad')
                    ->searchable(),
                TextColumn::make('country.name')
                    ->label('Pais')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->searchable(),
                TextColumn::make('created_by')
                    ->label('Creado por')
                    ->searchable(),
                TextColumn::make('updated_by')
                    ->label('Actualizado por')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reference_by')
                    ->label('Referido por')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Fecha de Registro')
                    ->description(fn(ProspectAgent $record): string => $record->created_at->diffForHumans())
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Ultima Actualizacion')
                    ->description(fn(ProspectAgent $record): string => $record->updated_at->diffForHumans())
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                // EditAction::make(),
                    
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
