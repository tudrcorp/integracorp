<?php

namespace App\Filament\Marketing\Resources\Agencies\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AgencyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('owner_code'),
                TextInput::make('code'),
                TextInput::make('agency_type_id')
                    ->required(),
                TextInput::make('rif'),
                TextInput::make('name_corporative'),
                TextInput::make('ci_responsable'),
                TextInput::make('address'),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
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
                TextInput::make('file_acuerdo'),
                TextInput::make('file_planilla'),
                TextInput::make('status')
                    ->required()
                    ->default('POR REVISION'),
                TextInput::make('created_by'),
                TextInput::make('fir_dig_agent'),
                TextInput::make('fir_dig_agency'),
                TextInput::make('date_register'),
                Toggle::make('is_accepted_conditions'),
                TextInput::make('file_ci_rif'),
                TextInput::make('file_w8_w9'),
                TextInput::make('file_account_usd'),
                TextInput::make('file_account_bsd'),
                TextInput::make('file_account_zelle'),
                Textarea::make('comments')
                    ->columnSpanFull(),
                TextInput::make('owner_master'),
                TextInput::make('owner_general'),
                TextInput::make('owner_agent'),
                TextInput::make('user_tdev'),
                Toggle::make('conf_position_menu'),
                TextInput::make('type_chart')
                    ->default('bar'),
            ]);
    }
}
