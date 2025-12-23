<?php

namespace App\Filament\Operations\Resources\TelemedicineCases\RelationManagers;

use BackedEnum;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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