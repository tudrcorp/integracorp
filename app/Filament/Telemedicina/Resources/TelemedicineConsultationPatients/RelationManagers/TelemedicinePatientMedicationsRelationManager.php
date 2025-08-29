<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\RelationManagers;

use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\TelemedicineConsultationPatientResource;
use BackedEnum;

class TelemedicinePatientMedicationsRelationManager extends RelationManager
{
    protected static string $relationship = 'telemedicinePatientMedications';

    protected static ?string $title = 'Medicamentos e Indicaciones';

    protected static string|BackedEnum|null $icon = 'healthicons-f-blister-pills-oval-x14';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Medicamentos e Indicacaciones')
            ->description(fn (RelationManager $livewire): string => 'Indicador por el Dr(a): ' . $livewire->ownerRecord->telemedicineDoctor->full_name)
            ->columns([
                TextColumn::make('medicine'),
                TextColumn::make('indications'),
            ])
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}