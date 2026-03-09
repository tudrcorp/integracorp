<?php

namespace App\Filament\Operations\Resources\OperationCoordinationServices\RelationManagers;

use App\Filament\Operations\Resources\OperationCoordinationServices\OperationCoordinationServiceResource;
use App\Models\TelemedicinePatientMedications;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TelemedicinePatientMedicationsRelationManager extends RelationManager
{
    protected static string $relationship = 'telemedicinePatientMedications';

    protected static ?string $title = 'Medicamentos e Indicaciones';

    protected static string|BackedEnum|null $icon = 'healthicons-f-blister-pills-oval-x14';

    // protected static ?string $relatedResource = OperationCoordinationServiceResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Medicamentos Solicitados')
            ->description(fn (RelationManager $livewire): string => 'Indicador por el Dr(a): '.$livewire->ownerRecord->telemedicineDoctor->full_name)
            ->columns([
                TextColumn::make('medicine')
                    ->label('Medicamento')
                    ->searchable(),
                TextColumn::make('indications')
                    ->label('Indicaciones')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Fecha de Solicitud')
                    ->description(fn (TelemedicinePatientMedications $record): string => $record->created_at->diffForHumans())
                    ->sortable()
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
