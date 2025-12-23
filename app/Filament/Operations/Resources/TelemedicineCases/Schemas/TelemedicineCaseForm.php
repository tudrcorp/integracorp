<?php

namespace App\Filament\Operations\Resources\TelemedicineCases\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TelemedicineCaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code'),
                TextInput::make('telemedicine_patient_id')
                    ->tel()
                    ->required()
                    ->numeric(),
                TextInput::make('telemedicine_doctor_id')
                    ->tel()
                    ->required()
                    ->numeric(),
                TextInput::make('patient_name'),
                TextInput::make('patient_age'),
                TextInput::make('patient_sex'),
                TextInput::make('patient_phone')
                    ->tel(),
                TextInput::make('patient_address'),
                TextInput::make('patient_country_id')
                    ->numeric(),
                TextInput::make('patient_state_id')
                    ->numeric(),
                TextInput::make('patient_city_id')
                    ->numeric(),
                TextInput::make('assigned_by'),
                TextInput::make('status')
                    ->required()
                    ->default('ASIGNADO'),
                Textarea::make('reason')
                    ->columnSpanFull(),
                TextInput::make('telemedicine_priority_id')
                    ->tel()
                    ->numeric(),
                TextInput::make('patient_phone_2')
                    ->tel(),
                Toggle::make('ambulanceParking'),
                Textarea::make('directionAmbulance')
                    ->columnSpanFull(),
            ]);
    }
}
