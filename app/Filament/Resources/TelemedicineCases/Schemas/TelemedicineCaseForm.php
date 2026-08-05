<?php

namespace App\Filament\Resources\TelemedicineCases\Schemas;

use App\Models\TelemedicinePatient;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
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
                    ->numeric()
                    ->live()
                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                        if (! filled($state)) {
                            return;
                        }

                        $patient = TelemedicinePatient::query()->find($state);

                        if ($patient === null) {
                            return;
                        }

                        $set('patient_name', trim((string) $patient->full_name));
                        $set('patient_age', $patient->age);
                        $set('patient_sex', $patient->sex);
                    }),
                TextInput::make('telemedicine_doctor_id')
                    ->tel()
                    ->required()
                    ->numeric(),
                TextInput::make('patient_name')
                    ->disabled()
                    ->dehydrated()
                    ->helperText('Se sincroniza automáticamente con el paciente vinculado.'),
                TextInput::make('patient_age')
                    ->disabled()
                    ->dehydrated(),
                TextInput::make('patient_sex')
                    ->disabled()
                    ->dehydrated(),
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
            ]);
    }
}
