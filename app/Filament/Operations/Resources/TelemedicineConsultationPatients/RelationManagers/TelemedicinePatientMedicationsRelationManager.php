<?php

namespace App\Filament\Operations\Resources\TelemedicineConsultationPatients\RelationManagers;

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