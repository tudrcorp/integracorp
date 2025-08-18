<?php

namespace App\Filament\Resources\TelemedicinePatients\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class TelemedicinePatientInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('plan_id')
                    ->numeric(),
                TextEntry::make('afilliation_id')
                    ->numeric(),
                TextEntry::make('afilliation_corporate_id')
                    ->numeric(),
                TextEntry::make('nro_identificacion'),
                TextEntry::make('date_birth'),
                TextEntry::make('sex'),
                TextEntry::make('phone'),
                TextEntry::make('email')
                    ->label('Email address'),
                TextEntry::make('address'),
                TextEntry::make('city_id'),
                TextEntry::make('country_id'),
                TextEntry::make('region'),
                TextEntry::make('state_id'),
                TextEntry::make('phone_contact'),
                TextEntry::make('email_contact'),
                TextEntry::make('type_affiliation'),
                TextEntry::make('date_affiliation'),
                TextEntry::make('status_affiliation'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
                TextEntry::make('code'),
                TextEntry::make('full_name'),
                TextEntry::make('age')
                    ->numeric(),
                TextEntry::make('created_by'),
            ]);
    }
}
