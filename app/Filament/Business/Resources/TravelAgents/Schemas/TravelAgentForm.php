<?php

namespace App\Filament\Business\Resources\TravelAgents\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TravelAgentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                TextInput::make('phone')
                    ->tel()
                    ->required(),
                TextInput::make('cargo')
                    ->required(),
                TextInput::make('fechaNacimiento')
                    ->required(),
                TextInput::make('nameSecundario')
                    ->required(),
                TextInput::make('emailSecundario')
                    ->email()
                    ->required(),
                TextInput::make('phoneSecundario')
                    ->tel()
                    ->required(),
                TextInput::make('cargoSecundario')
                    ->required(),
                TextInput::make('fechaNacimientoSecundario')
                    ->required(),
                TextInput::make('local_beneficiary_name')
                    ->required(),
                TextInput::make('local_beneficiary_rif')
                    ->required(),
                TextInput::make('local_beneficiary_account_number')
                    ->required(),
                TextInput::make('local_beneficiary_account_bank')
                    ->required(),
                TextInput::make('local_beneficiary_account_type')
                    ->required(),
                TextInput::make('local_beneficiary_phone_pm')
                    ->tel()
                    ->required(),
                TextInput::make('local_beneficiary_account_number_mon_inter')
                    ->required(),
                TextInput::make('local_beneficiary_account_bank_mon_inter')
                    ->required(),
                TextInput::make('local_beneficiary_account_type_mon_inter')
                    ->required(),
                TextInput::make('extra_beneficiary_name')
                    ->required(),
                TextInput::make('extra_beneficiary_ci_rif')
                    ->required(),
                TextInput::make('extra_beneficiary_account_number')
                    ->required(),
                TextInput::make('extra_beneficiary_account_bank')
                    ->required(),
                TextInput::make('extra_beneficiary_account_type')
                    ->required(),
                TextInput::make('extra_beneficiary_route')
                    ->required(),
                TextInput::make('extra_beneficiary_zelle')
                    ->required(),
                TextInput::make('extra_beneficiary_ach')
                    ->required(),
                TextInput::make('extra_beneficiary_swift')
                    ->required(),
                TextInput::make('extra_beneficiary_aba')
                    ->required(),
                TextInput::make('extra_beneficiary_address')
                    ->required(),
                TextInput::make('logo')
                    ->required(),
                TextInput::make('createdBy')
                    ->required(),
                TextInput::make('updatedBy')
                    ->required(),
                TextInput::make('travel_agency_id')
                    ->required()
                    ->numeric(),
            ]);
    }
}
