<?php

namespace App\Filament\Operations\Resources\OperationOnCallUsers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OperationOnCallUsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre y Apellido')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Correo Electrónico')
                    ->icon('heroicon-o-envelope')
                    ->badge()
                    ->color('primary')
                    ->searchable(),
                TextColumn::make('phone')
                    ->icon('heroicon-o-phone')
                    ->badge()
                    ->color('primary')
                    ->label('Teléfono')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(function (string $state): string {
                        return match ($state) {
                            'DE GUARDIA' => 'success',
                            'PROGRAMADA' => 'warning',
                        };
                    })
                    ->icon(function (string $state): string {
                        return match ($state) {
                            'DE GUARDIA' => 'heroicon-s-check-circle',
                            'PROGRAMADA' => 'heroicon-s-clock',
                        };
                    })
                    ->searchable(),
                TextColumn::make('created_by')
                    ->label('Creado por')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Fecha de Creación')
                    ->description(fn ($record) => $record->created_at->diffForHumans())
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
