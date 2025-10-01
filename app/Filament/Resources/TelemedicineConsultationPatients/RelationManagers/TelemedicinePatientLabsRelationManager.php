<?php

namespace App\Filament\Resources\TelemedicineConsultationPatients\RelationManagers;

use BackedEnum;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Resources\TelemedicineConsultationPatients\TelemedicineConsultationPatientResource;

class TelemedicinePatientLabsRelationManager extends RelationManager
{
    protected static string $relationship = 'telemedicinePatientLabs';

    protected static ?string $title = 'Laboratorios';

    protected static string|BackedEnum|null $icon = 'healthicons-f-biochemistry-laboratory';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Laboratorios solicitados')
            ->description(fn(RelationManager $livewire): string => 'Indicador por el Dr(a): ' . $livewire->ownerRecord->telemedicineDoctor->full_name)
            ->columns([
                TextColumn::make('laboratory')
                    ->label('Laboratorio')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn($record): string => $record->type == 'CUBIERTO' ? 'success' : 'danger')
                    ->icon(fn($record): string => $record->type == 'CUBIERTO' ? 'heroicon-m-check' : 'heroicon-o-x-mark')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Fecha de Solicitud')
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