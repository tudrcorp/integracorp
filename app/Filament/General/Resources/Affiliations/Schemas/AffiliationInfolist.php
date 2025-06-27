<?php

namespace App\Filament\General\Resources\Affiliations\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class AffiliationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('owner_code'),
                TextEntry::make('code'),
                TextEntry::make('code_agency'),
                TextEntry::make('agent_id')
                    ->numeric(),
                TextEntry::make('individual_quote_id')
                    ->numeric(),
                TextEntry::make('plan_id')
                    ->numeric(),
                TextEntry::make('coverage_id')
                    ->numeric(),
                TextEntry::make('payment_frequency'),
                TextEntry::make('code_individual_quote'),
                TextEntry::make('full_name_con'),
                TextEntry::make('nro_identificacion_con'),
                TextEntry::make('sex_con'),
                TextEntry::make('birth_date_con'),
                TextEntry::make('adress_con'),
                TextEntry::make('city_id_con')
                    ->numeric(),
                TextEntry::make('state_id_con')
                    ->numeric(),
                TextEntry::make('country_id_con')
                    ->numeric(),
                TextEntry::make('region_con'),
                TextEntry::make('phone_con'),
                TextEntry::make('email_con'),
                TextEntry::make('full_name_ti'),
                TextEntry::make('nro_identificacion_ti'),
                TextEntry::make('sex_ti'),
                TextEntry::make('birth_date_ti'),
                TextEntry::make('adress_ti'),
                TextEntry::make('city_id_ti'),
                TextEntry::make('state_id_ti'),
                TextEntry::make('country_id_ti'),
                TextEntry::make('region_ti'),
                TextEntry::make('phone_ti'),
                TextEntry::make('email_ti'),
                IconEntry::make('cuestion_1')
                    ->boolean(),
                IconEntry::make('cuestion_2')
                    ->boolean(),
                IconEntry::make('cuestion_3')
                    ->boolean(),
                IconEntry::make('cuestion_4')
                    ->boolean(),
                IconEntry::make('cuestion_5')
                    ->boolean(),
                IconEntry::make('cuestion_6')
                    ->boolean(),
                IconEntry::make('cuestion_7')
                    ->boolean(),
                IconEntry::make('cuestion_8')
                    ->boolean(),
                IconEntry::make('cuestion_9')
                    ->boolean(),
                IconEntry::make('cuestion_10')
                    ->boolean(),
                IconEntry::make('cuestion_11')
                    ->boolean(),
                IconEntry::make('cuestion_12')
                    ->boolean(),
                IconEntry::make('cuestion_13')
                    ->boolean(),
                IconEntry::make('cuestion_14')
                    ->boolean(),
                TextEntry::make('full_name_agent'),
                TextEntry::make('code_agent'),
                TextEntry::make('date_today'),
                TextEntry::make('created_by'),
                TextEntry::make('status'),
                TextEntry::make('document'),
                TextEntry::make('activated_at'),
                TextEntry::make('family_members'),
                TextEntry::make('vaucher_ils'),
                TextEntry::make('date_payment_initial_ils'),
                TextEntry::make('date_payment_final_ils'),
                TextEntry::make('document_ils'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
                TextEntry::make('fee_anual')
                    ->numeric(),
                TextEntry::make('total_amount')
                    ->numeric(),
                TextEntry::make('signature_agent'),
                TextEntry::make('signature_ti'),
                TextEntry::make('owner_agent'),
                TextEntry::make('activation_date'),
            ]);
    }
}
