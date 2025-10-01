<?php

namespace App\Filament\Resources\TelemedicineConsultationPatients\RelationManagers;

use BackedEnum;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Resources\TelemedicineConsultationPatients\TelemedicineConsultationPatientResource;

class TelemedicinePatientSpecialistsRelationManager extends RelationManager
{
    protected static string $relationship = 'telemedicinePatientSpecialists';

    protected static ?string $title = 'Consultas con Especialistas';

    protected static string|BackedEnum|null $icon = 'healthicons-f-doctor-male';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Consultas con Especialistas Solicitadas')
            ->description(fn(RelationManager $livewire): string => 'Indicador por el Dr(a): ' . $livewire->ownerRecord->telemedicineDoctor->full_name)
            ->columns([
                TextColumn::make('specialty')
                    ->label('Especialidad')
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