<?php

namespace App\Filament\Marketing\Resources\Events\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Nomre del Evento')
                    ->badge()
                    ->color('success')
                    ->icon('fontisto-ticket-alt')
                    ->searchable(),
                ImageColumn::make('image')
                    ->imageWidth(200)
                    ->imageHeight('auto')
                    ->label('Flayer'),
                TextColumn::make('dateInit')
                    ->label('Fecha de inicio')
                    ->icon('heroicon-s-calendar')
                    ->searchable(),
                TextColumn::make('dateEnd')
                    ->label('Fecha de finalización')
                    ->icon('heroicon-s-calendar')
                    ->searchable(),
                TextColumn::make('status')
                    ->icon('heroicon-s-check-circle')
                    ->label('Estado')
                    ->badge()
                    ->color('success')
                    ->searchable(),
                TextColumn::make('created_by')
                    ->icon('heroicon-s-user')
                    ->label('Creado por:')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Creado el:')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                //ViewAction::make(),
                // EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}