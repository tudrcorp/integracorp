<?php

namespace App\Filament\Business\Resources\AccountManagers\Tables;

use App\Models\AccountManager;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AccountManagersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->heading('ACCOUNT MANAGERS')
            ->description('Lista de account managers registrados en el Sistema')
            ->columns([
                TextColumn::make('user_id')
                    ->label('INTEGRACORP ID')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('full_name')
                    ->label('Nombre y Apellido')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Telefono')
                    ->searchable(),
                TextColumn::make('address')
                    ->label('Direccion')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Correo Electronico')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Fecha de Creacion')
                    ->description(fn (AccountManager $record) => $record->created_at->diffForHumans())
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_by')
                    ->label('Creado por')
                    ->searchable(),
                TextColumn::make('updated_at')
                    ->label('Fecha de Actualizacion')
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
            ])
            ->striped();
    }
}