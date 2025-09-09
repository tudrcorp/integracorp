<?php

namespace App\Filament\Marketing\Resources\Agents\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class AgentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('agency.id'),
                TextEntry::make('code_agent'),
                TextEntry::make('code_agency'),
                TextEntry::make('owner_code'),
                TextEntry::make('agent_type_id'),
                TextEntry::make('name'),
                TextEntry::make('ci'),
                TextEntry::make('rif'),
                TextEntry::make('birth_date'),
                TextEntry::make('address'),
                TextEntry::make('email')
                    ->label('Email address'),
                TextEntry::make('phone'),
                TextEntry::make('user_instagram'),
                TextEntry::make('country.name'),
                TextEntry::make('state.id'),
                TextEntry::make('city.id'),
                TextEntry::make('region'),
                TextEntry::make('sex'),
                TextEntry::make('marital_status'),
                TextEntry::make('name_contact_2'),
                TextEntry::make('email_contact_2'),
                TextEntry::make('phone_contact_2'),
                TextEntry::make('local_beneficiary_name'),
                TextEntry::make('local_beneficiary_rif'),
                TextEntry::make('local_beneficiary_account_number'),
                TextEntry::make('local_beneficiary_account_bank'),
                TextEntry::make('local_beneficiary_account_type'),
                TextEntry::make('local_beneficiary_phone_pm'),
                TextEntry::make('extra_beneficiary_name'),
                TextEntry::make('extra_beneficiary_ci_rif'),
                TextEntry::make('extra_beneficiary_account_number'),
                TextEntry::make('extra_beneficiary_account_bank'),
                TextEntry::make('extra_beneficiary_account_type'),
                TextEntry::make('extra_beneficiary_route'),
                TextEntry::make('extra_beneficiary_zelle'),
                TextEntry::make('extra_beneficiary_ach'),
                TextEntry::make('extra_beneficiary_swift'),
                TextEntry::make('extra_beneficiary_aba'),
                TextEntry::make('extra_beneficiary_address'),
                IconEntry::make('tdec')
                    ->boolean(),
                IconEntry::make('tdev')
                    ->boolean(),
                TextEntry::make('commission_tdec')
                    ->numeric(),
                TextEntry::make('commission_tdec_renewal')
                    ->numeric(),
                TextEntry::make('commission_tdev')
                    ->numeric(),
                TextEntry::make('commission_tdev_renewal')
                    ->numeric(),
                TextEntry::make('status'),
                TextEntry::make('created_by'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
                TextEntry::make('date_register'),
                TextEntry::make('fir_dig_agent'),
                TextEntry::make('fir_dig_agency'),
                IconEntry::make('is_accepted_conditions')
                    ->boolean(),
                TextEntry::make('file_ci_rif'),
                TextEntry::make('file_w8_w9'),
                TextEntry::make('file_account_usd'),
                TextEntry::make('file_account_bsd'),
                TextEntry::make('file_account_zelle'),
                TextEntry::make('owner_agent'),
                TextEntry::make('user_tdev'),
                IconEntry::make('conf_position_menu')
                    ->boolean(),
                TextEntry::make('type_chart'),
            ]);
    }
}
