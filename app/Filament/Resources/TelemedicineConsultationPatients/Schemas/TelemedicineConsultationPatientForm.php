<?php

namespace App\Filament\Resources\TelemedicineConsultationPatients\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class TelemedicineConsultationPatientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('telemedicine_case_id')
                    ->tel()
                    ->required()
                    ->numeric(),
                TextInput::make('telemedicine_case_code')
                    ->tel()
                    ->required(),
                TextInput::make('telemedicine_patient_id')
                    ->tel()
                    ->required()
                    ->numeric(),
                TextInput::make('telemedicine_doctor_id')
                    ->tel()
                    ->required()
                    ->numeric(),
                TextInput::make('code_reference')
                    ->required(),
                TextInput::make('full_name')
                    ->required(),
                TextInput::make('nro_identificacion')
                    ->required(),
                TextInput::make('type_service')
                    ->required(),
                Textarea::make('reason_consultation')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('actual_phatology')
                    ->columnSpanFull(),
                TextInput::make('vs_pa'),
                TextInput::make('vs_fc'),
                TextInput::make('vs_fr'),
                TextInput::make('vs_temp'),
                TextInput::make('vs_sat'),
                TextInput::make('vs_weight'),
                Textarea::make('background')
                    ->columnSpanFull(),
                Textarea::make('diagnostic_impression')
                    ->columnSpanFull(),
                TextInput::make('labs'),
                TextInput::make('studies'),
                TextInput::make('consult_specialist'),
                TextInput::make('part_body'),
                TextInput::make('other_labs'),
                TextInput::make('other_studies'),
                TextInput::make('other_specialist'),
            ]);
    }
}
