<?php

namespace App\Filament\Resources\Events\RelationManagers;

use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Tables\Columns\TextColumn;
use App\Filament\Resources\Events\EventResource;
use Filament\Resources\RelationManagers\RelationManager;

class GuestsRelationManager extends RelationManager
{
    protected static string $relationship = 'guests';

    // protected static ?string $relatedResource = EventResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Lista de Invitados')
            ->description('Se muestra la lista de personas que han realizado su inscripcion y confirmacion por medio Sitio Web tudrgroup.com ')
            ->columns([
                TextColumn::make('firstName')
                    ->label('Nombre')
                    ->searchable(),
                TextColumn::make('lastName')
                    ->label('Apellido')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable(),
                TextColumn::make('agency')
                    ->label('Agencia')
                    ->searchable(),
                TextColumn::make('companion')
                    ->label('Numero de Acompañantes')
                    ->searchable(),
                TextColumn::make('webBrowser')
                    ->label('Navegador')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Fecha de Registro')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}