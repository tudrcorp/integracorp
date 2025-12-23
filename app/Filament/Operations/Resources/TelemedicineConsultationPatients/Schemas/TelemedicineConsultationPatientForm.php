<?php

namespace App\Filament\Operations\Resources\TelemedicineConsultationPatients\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
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
                TextInput::make('telemedicine_priority_id')
                    ->tel()
                    ->numeric(),
                TextInput::make('telemedicine_service_list_id')
                    ->tel()
                    ->numeric(),
                TextInput::make('code_reference'),
                TextInput::make('full_name'),
                TextInput::make('nro_identificacion'),
                Textarea::make('reason_consultation')
                    ->columnSpanFull(),
                Textarea::make('actual_phatology')
                    ->columnSpanFull(),
                Textarea::make('background')
                    ->columnSpanFull(),
                Textarea::make('diagnostic_impression')
                    ->columnSpanFull(),
                Textarea::make('labs')
                    ->columnSpanFull(),
                Textarea::make('studies')
                    ->columnSpanFull(),
                Textarea::make('consult_specialist')
                    ->columnSpanFull(),
                Textarea::make('other_labs')
                    ->columnSpanFull(),
                Textarea::make('other_studies')
                    ->columnSpanFull(),
                Textarea::make('other_specialist')
                    ->columnSpanFull(),
                TextInput::make('status'),
                TextInput::make('assigned_by')
                    ->numeric(),
                Textarea::make('cuestion_1')
                    ->columnSpanFull(),
                Textarea::make('cuestion_2')
                    ->columnSpanFull(),
                Textarea::make('cuestion_3')
                    ->columnSpanFull(),
                Textarea::make('cuestion_4')
                    ->columnSpanFull(),
                Textarea::make('cuestion_5')
                    ->columnSpanFull(),
                Toggle::make('feedbackOne'),
                TextInput::make('duration')
                    ->numeric(),
                TextInput::make('priorityMonitoring')
                    ->numeric(),
                Textarea::make('observations')
                    ->columnSpanFull(),
                TextInput::make('pa')
                    ->numeric(),
                TextInput::make('fc')
                    ->numeric(),
                TextInput::make('fr')
                    ->numeric(),
                TextInput::make('temp')
                    ->numeric(),
                TextInput::make('saturacion')
                    ->numeric(),
                TextInput::make('peso')
                    ->numeric(),
                TextInput::make('estatura')
                    ->numeric(),
                TextInput::make('imc')
                    ->numeric(),
            ]);
    }
}
