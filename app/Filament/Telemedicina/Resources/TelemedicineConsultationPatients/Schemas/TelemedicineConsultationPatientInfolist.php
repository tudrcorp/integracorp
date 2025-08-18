<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class TelemedicineConsultationPatientInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('telemedicine_case_id')
                    ->numeric(),
                TextEntry::make('telemedicine_case_code')
                    ->numeric(),
                TextEntry::make('telemedicine_patient_id')
                    ->numeric(),
                TextEntry::make('telemedicine_doctor_id')
                    ->numeric(),
                TextEntry::make('code_reference'),
                TextEntry::make('full_name'),
                TextEntry::make('nro_identificacion'),
                TextEntry::make('type_service'),
                TextEntry::make('reason_consultation'),
                TextEntry::make('actual_phatology'),
                TextEntry::make('vs_pa'),
                TextEntry::make('vs_fc'),
                TextEntry::make('vs_fr'),
                TextEntry::make('vs_temp'),
                TextEntry::make('vs_sat'),
                TextEntry::make('vs_weight'),
                TextEntry::make('background'),
                TextEntry::make('diagnostic_impression'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}
