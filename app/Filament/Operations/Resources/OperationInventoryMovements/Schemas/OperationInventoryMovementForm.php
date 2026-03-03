<?php

namespace App\Filament\Operations\Resources\OperationInventoryMovements\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OperationInventoryMovementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('operation_inventory_id')
                    ->required()
                    ->numeric(),
                TextInput::make('telemedicine_patient_id')
                    ->tel()
                    ->required()
                    ->numeric(),
                TextInput::make('telemedicine_case_id')
                    ->tel()
                    ->required()
                    ->numeric(),
                TextInput::make('telemedicine_consultation_id')
                    ->tel()
                    ->required()
                    ->numeric(),
                TextInput::make('telemedicine_doctor_id')
                    ->tel()
                    ->required()
                    ->numeric(),
                TextInput::make('business_unit_id')
                    ->required()
                    ->numeric(),
                TextInput::make('business_line_id')
                    ->required()
                    ->numeric(),
                TextInput::make('quantity')
                    ->required()
                    ->numeric(),
                TextInput::make('unit')
                    ->required(),
                TextInput::make('type')
                    ->required(),
                TextInput::make('created_by')
                    ->required(),
                TextInput::make('status')
                    ->required()
                    ->default('DESPACHADO'),
            ]);
    }
}
