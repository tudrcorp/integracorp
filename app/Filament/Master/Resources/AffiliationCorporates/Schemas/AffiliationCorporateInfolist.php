<?php

namespace App\Filament\Master\Resources\AffiliationCorporates\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class AffiliationCorporateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('code'),
                TextEntry::make('code_corporate_quote'),
                TextEntry::make('code_agent'),
                TextEntry::make('code_agency'),
                TextEntry::make('corporate_quote_id')
                    ->numeric(),
                TextEntry::make('plan_id')
                    ->numeric(),
                TextEntry::make('agent_id')
                    ->numeric(),
                TextEntry::make('coverage_id')
                    ->numeric(),
                TextEntry::make('full_name_con'),
                TextEntry::make('rif'),
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
                TextEntry::make('vaucher_ils'),
                TextEntry::make('date_payment_initial_ils'),
                TextEntry::make('date_payment_final_ils'),
                TextEntry::make('document_ils'),
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
                IconEntry::make('cuestion_15')
                    ->boolean(),
                TextEntry::make('full_name_applicant'),
                TextEntry::make('signature_applicant'),
                TextEntry::make('nro_identificacion_applicant'),
                TextEntry::make('date_applicant'),
                TextEntry::make('full_name_agent'),
                TextEntry::make('signature_agent'),
                TextEntry::make('payment_frequency'),
                TextEntry::make('activated_at'),
                TextEntry::make('corporate_members'),
                TextEntry::make('document'),
                TextEntry::make('date_today'),
                TextEntry::make('created_by'),
                TextEntry::make('status'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
                TextEntry::make('owner_code'),
                TextEntry::make('type'),
            ]);
    }
}
