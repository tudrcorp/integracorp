<?php

namespace App\Filament\Resources\TelemedicineConsultationPatients\RelationManagers;

use BackedEnum;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Resources\TelemedicineConsultationPatients\TelemedicineConsultationPatientResource;

class TelemedicinePatientMedicationsRelationManager extends RelationManager
{
    protected static string $relationship = 'telemedicinePatientMedications';

    protected static ?string $title = 'Medicamentos e Indicaciones';

    protected static string|BackedEnum|null $icon = 'healthicons-f-blister-pills-oval-x14';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Medicamentos e Indicacaciones')
            ->description(fn(RelationManager $livewire): string => 'Indicador por el Dr(a): ' . $livewire->ownerRecord->telemedicineDoctor->full_name)
            ->columns([
                TextColumn::make('medicine')
                    ->label('Medicamento')
                    ->searchable(),
                TextColumn::make('indications')
                    ->label('Indicaciones')
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