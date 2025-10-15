<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineCases\RelationManagers;

use BackedEnum;
use Filament\Tables\Table;
use App\Models\TelemedicineCase;
use Filament\Actions\CreateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Telemedicina\Resources\TelemedicineCases\TelemedicineCaseResource;

class ObservationsRelationManager extends RelationManager
{
    protected static string $relationship = 'observations';

    protected static ?string $title = 'Observaciones';

    protected static string|BackedEnum|null $icon = 'heroicon-c-hand-raised';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Observaciones del Caso')
            ->description('Histórico de observaciones asociadas al caso durante su tratamiento y seguimiento')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Ultima Actualización')
                    ->dateTime()
                    ->description(fn($record): string => $record->updated_at->diffForHumans())
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Observación')
                    ->searchable(),
                TextColumn::make('createdBy.name')
                    ->label('Creado por')
                    ->badge()
                    ->color('primary')
                    ->icon('heroicon-s-user')
                    ->searchable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}