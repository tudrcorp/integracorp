<?php

namespace App\Filament\Resources\IndividualQuotes\RelationManagers;

use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Resources\IndividualQuotes\IndividualQuoteResource;

class BitacorasRelationManager extends RelationManager
{
    protected static string $relationship = 'bitacoras';

    protected static ?string $title = 'BITACORA';


    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->recordTitleAttribute('individual_quote_id')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Registrado por:')
                    ->badge()
                    ->icon('heroicon-m-user')
                    ->searchable(),
                TextColumn::make('details')
                    ->label('Detalles')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Actualizado el:')
                    ->dateTime(),
            ])
            ->headerActions([
                // CreateAction::make(),
            ]);
    }
}