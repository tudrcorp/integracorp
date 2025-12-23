<?php

namespace App\Filament\Operations\Resources\TelemedicineDoctors\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TelemedicineDoctorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Tabla principal de Doctores')   
            ->description('...')
            ->columns([
                TextColumn::make('full_name')
                    ->Label('Nombre y Apellido')
                    ->searchable(),
                TextColumn::make('nro_identificacion')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('code_cm')
                    ->label('Código CM')
                    ->searchable(),
                TextColumn::make('code_mpps')
                    ->label('Código MPPS')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable(),
                TextColumn::make('specialty')
                    ->label('Especialidad')
                    ->searchable(),
                TextColumn::make('address')
                    ->label('Dirección')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Fecha de Creado')
                    ->dateTime()
                    ->sortable(),            
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