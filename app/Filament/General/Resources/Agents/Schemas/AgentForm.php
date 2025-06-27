<?php

namespace App\Filament\General\Resources\Agents\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AgentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('agency_id')
                    ->relationship('agency', 'id'),
                TextInput::make('code_agent'),
                TextInput::make('code_agency'),
                TextInput::make('owner_code'),
                TextInput::make('agent_type_id'),
                TextInput::make('name'),
                TextInput::make('ci'),
                TextInput::make('rif'),
                TextInput::make('birth_date'),
                TextInput::make('address'),
                TextInput::make('email')
                    ->email(),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('user_instagram'),
                Select::make('country_id')
                    ->relationship('country', 'name'),
                Select::make('state_id')
                    ->relationship('state', 'id'),
                Select::make('city_id')
                    ->relationship('city', 'id'),
                TextInput::make('region'),
                TextInput::make('sex'),
                TextInput::make('marital_status'),
                TextInput::make('name_contact_2'),
                TextInput::make('email_contact_2')
                    ->email(),
                TextInput::make('phone_contact_2')
                    ->tel(),
                TextInput::make('local_beneficiary_name'),
                TextInput::make('local_beneficiary_rif'),
                TextInput::make('local_beneficiary_account_number'),
                TextInput::make('local_beneficiary_account_bank'),
                TextInput::make('local_beneficiary_account_type'),
                TextInput::make('local_beneficiary_phone_pm')
                    ->tel(),
                TextInput::make('extra_beneficiary_name'),
                TextInput::make('extra_beneficiary_ci_rif'),
                TextInput::make('extra_beneficiary_account_number'),
                TextInput::make('extra_beneficiary_account_bank'),
                TextInput::make('extra_beneficiary_account_type'),
                TextInput::make('extra_beneficiary_route'),
                TextInput::make('extra_beneficiary_zelle'),
                TextInput::make('extra_beneficiary_ach'),
                TextInput::make('extra_beneficiary_swift'),
                TextInput::make('extra_beneficiary_aba'),
                TextInput::make('extra_beneficiary_address'),
                Toggle::make('tdec'),
                Toggle::make('tdev'),
                TextInput::make('commission_tdec')
                    ->numeric()
                    ->default(0.0),
                TextInput::make('commission_tdec_renewal')
                    ->numeric()
                    ->default(0.0),
                TextInput::make('commission_tdev')
                    ->numeric()
                    ->default(0.0),
                TextInput::make('commission_tdev_renewal')
                    ->numeric()
                    ->default(0.0),
                TextInput::make('status')
                    ->default('POR REVISION'),
                TextInput::make('created_by'),
                TextInput::make('date_register'),
                TextInput::make('fir_dig_agent'),
                TextInput::make('fir_dig_agency'),
                Toggle::make('is_accepted')
                    ->required(),
                TextInput::make('file_ci_rif'),
                TextInput::make('file_w8_w9'),
                TextInput::make('file_account_usd'),
                TextInput::make('file_account_bsd'),
                TextInput::make('file_account_zelle'),
                Textarea::make('comments')
                    ->columnSpanFull(),
                TextInput::make('owner_agent'),
            ]);
    }
}
