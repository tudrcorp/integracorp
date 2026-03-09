<?php

namespace App\Filament\Operations\Resources\OperationCoordinationServices\RelationManagers;

use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class TelemedicinePatientSpecialtiesRelationManager extends RelationManager
{
    protected static string $relationship = 'telemedicinePatientSpecialties';

    protected static ?string $title = 'Especialidades';

    protected static string|BackedEnum|null $icon = 'healthicons-f-stethoscope';

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
