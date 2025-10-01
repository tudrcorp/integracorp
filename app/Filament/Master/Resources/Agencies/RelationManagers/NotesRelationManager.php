<?php

namespace App\Filament\Master\Resources\Agencies\RelationManagers;

use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Master\Resources\Agencies\AgencyResource;
use BackedEnum;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;

class NotesRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';

    protected static ?string $title = 'Notas';

    protected static string|BackedEnum|null $icon = 'heroicon-o-square-3-stack-3d';

    public function table(Table $table): Table
    {
        return $table
        ->heading('Notas y/o Observaciones')
        ->description('Listas de Notas y/o registradas en la Agencia, ordenas de forma cronológica.')
        ->defaultSort('created_at', 'desc')
        ->columns([
            TextColumn::make('created_at')
                ->label('Fecha de Registro')
                ->dateTime()
                ->description(fn($record): string => $record->created_at->diffForHumans())
                ->sortable(),
            TextColumn::make('note')
                ->label('Nota')
                ->searchable()
                ->wrap(),
        ])
        ->headerActions([
            // CreateAction::make(),
        ]);
    }
}