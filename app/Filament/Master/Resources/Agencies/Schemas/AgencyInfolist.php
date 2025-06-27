<?php

namespace App\Filament\Master\Resources\Agencies\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class AgencyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('owner_code'),
                TextEntry::make('code'),
                TextEntry::make('agency_type_id'),
                TextEntry::make('rif'),
                TextEntry::make('name_corporative'),
                TextEntry::make('ci_responsable'),
                TextEntry::make('address'),
                TextEntry::make('email'),
                TextEntry::make('phone'),
                TextEntry::make('user_instagram'),
                TextEntry::make('country.name')
                    ->numeric(),
                TextEntry::make('state.id')
                    ->numeric(),
                TextEntry::make('city.id')
                    ->numeric(),
                TextEntry::make('region'),
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
                TextEntry::make('file_acuerdo'),
                TextEntry::make('file_planilla'),
                TextEntry::make('status'),
                TextEntry::make('created_by'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
                TextEntry::make('fir_dig_agent'),
                TextEntry::make('fir_dig_agency'),
                TextEntry::make('date_register'),
                IconEntry::make('is_accepted')
                    ->boolean(),
                TextEntry::make('file_ci_rif'),
                TextEntry::make('file_w8_w9'),
                TextEntry::make('file_account_usd'),
                TextEntry::make('file_account_bsd'),
                TextEntry::make('file_account_zelle'),
                TextEntry::make('owner_master'),
                TextEntry::make('owner_general'),
                TextEntry::make('owner_agent'),
            ]);
    }
}
