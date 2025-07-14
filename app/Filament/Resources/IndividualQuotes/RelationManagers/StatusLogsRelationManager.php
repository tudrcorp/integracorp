<?php

namespace App\Filament\Resources\IndividualQuotes\RelationManagers;

use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Resources\IndividualQuotes\IndividualQuoteResource;

class StatusLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'statusLogs';

    protected static ?string $title = 'BITACORA';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->recordTitleAttribute('individual_quote_id')
            ->columns([
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(function (string $state): string {
                        return match ($state) {
                            'PRE-APROBADA' => 'warning',
                            'APROBADA' => 'success',
                            'ANULADA' => 'warning',
                            'DECLINADA' => 'danger',
                        };
                    })
                    ->icon(function (mixed $state): ?string {
                        return match ($state) {
                            'PRE-APROBADA'  => 'heroicon-c-information-circle',
                            'APROBADA'      => 'heroicon-s-check-circle',
                            'ANULADA'       => 'heroicon-s-exclamation-circle',
                            'DECLINADA'     => 'heroicon-c-x-circle',
                        };
                    })
                    ->searchable(),
                TextColumn::make('updated_by')
                    ->label('Actualizado por')
                    ->searchable(),
                TextColumn::make('observation')
                    ->label('Observación')
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label('Actualizado el:')
                    ->dateTime(),
            ])
            ->filters([
                //
            ]);
    }
}