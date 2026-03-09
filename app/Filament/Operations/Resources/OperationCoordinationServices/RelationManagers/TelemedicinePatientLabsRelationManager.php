<?php

namespace App\Filament\Operations\Resources\OperationCoordinationServices\RelationManagers;

use App\Models\TelemedicinePatientLab;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TelemedicinePatientLabsRelationManager extends RelationManager
{
    protected static string $relationship = 'telemedicinePatientLabs';

    protected static ?string $title = 'Laboratorios';

    protected static string|BackedEnum|null $icon = 'healthicons-f-biochemistry-laboratory';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Estudios de Imagenologia Solicitados')
            ->description(fn (RelationManager $livewire): string => 'Indicador por el Dr(a): '.$livewire->ownerRecord->telemedicineDoctor->full_name)
            ->columns([
                TextColumn::make('laboratory')
                    ->label('Laboratorio')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn ($record): string => $record->type == 'CUBIERTO' ? 'success' : 'danger')
                    ->icon(fn ($record): string => $record->type == 'CUBIERTO' ? 'heroicon-m-check' : 'heroicon-o-x-mark')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Fecha de Solicitud')
                    ->description(fn (TelemedicinePatientLab $record): string => $record->created_at->diffForHumans())
                    ->badge()
                    ->date('d/m/Y')
                    ->color('primary')
                    ->icon('heroicon-s-calendar')
                    ->searchable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
