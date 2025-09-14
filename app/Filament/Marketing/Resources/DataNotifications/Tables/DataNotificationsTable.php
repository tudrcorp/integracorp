<?php

namespace App\Filament\Marketing\Resources\DataNotifications\Tables;

use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;

class DataNotificationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
        ->heading('Destinatarios asociada a las notificaciones')
        ->description('Listado de destinatarios asociada a las notificaciones, desde aquí puedes ver, editar o eliminar los destinatarios asociada a las notificaciones')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('dataNotifications.title')
                    ->label('Notificación Asociada'),
                TextColumn::make('fullName')
                    ->label('Nombre y Apellido')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Correo Electrónico')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Numero de Teléfono')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Fecha de Registro')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    DeleteAction::make()
                    ->label('Eliminar'),
                ]),
                // ...
            ]);
    }
}