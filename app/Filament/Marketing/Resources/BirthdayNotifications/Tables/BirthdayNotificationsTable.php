<?php

namespace App\Filament\Marketing\Resources\BirthdayNotifications\Tables;

use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\Width;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Schemas\Components\Actions;
use Filament\Tables\Columns\ImageColumn;

class BirthdayNotificationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('content')
                    ->label('Contenido')
                    ->searchable(),
                TextColumn::make('data_type')
                    ->label('Destinatarios')
                    ->badge()
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'INACTIVA' => 'danger',
                        'ACTIVA' => 'success',
                    })
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Fecha de Creación')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('change_status')
                    ->label('Activar')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Activar Notificación')
                    ->modalDescription('Esta seguro de activar la notificación?. Después de activada el sistema iniciara un proceso interno para enviar la notificación de acuerdo a la fecha de nacimiento de los destinatarios.')
                    ->modalSubmitActionLabel('Si, activar notificación')
                    ->icon('heroicon-m-pencil-square')
                    ->button()
                    ->action(function ($record) {
                        $record->status = 'ACTIVA';
                        $record->save();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}