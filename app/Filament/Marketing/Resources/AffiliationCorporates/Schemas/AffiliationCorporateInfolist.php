<?php

namespace App\Filament\Marketing\Resources\AffiliationCorporates\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class AffiliationCorporateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('corporate_quote_id')
                    ->numeric(),
                TextEntry::make('owner_code'),
                TextEntry::make('code'),
                TextEntry::make('code_agency'),
                TextEntry::make('agent_id'),
                TextEntry::make('name_corporate'),
                TextEntry::make('rif'),
                TextEntry::make('address'),
                TextEntry::make('city_id')
                    ->numeric(),
                TextEntry::make('country_id')
                    ->numeric(),
                TextEntry::make('region_id'),
                TextEntry::make('phone'),
                TextEntry::make('email')
                    ->label('Email address'),
                TextEntry::make('full_name_contact'),
                TextEntry::make('nro_identificacion_contact'),
                TextEntry::make('phone_contact'),
                TextEntry::make('email_contact'),
                TextEntry::make('date_affiliation'),
                TextEntry::make('created_by'),
                TextEntry::make('status'),
                TextEntry::make('document'),
                TextEntry::make('payment_frequency'),
                TextEntry::make('fee_anual'),
                TextEntry::make('total_amount')
                    ->numeric(),
                TextEntry::make('vaucher_ils'),
                TextEntry::make('date_payment_initial_ils'),
                TextEntry::make('date_payment_final_ils'),
                TextEntry::make('document_ils'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
                TextEntry::make('state_id')
                    ->numeric(),
                TextEntry::make('poblation')
                    ->numeric(),
                TextEntry::make('activated_at'),
            ]);
    }
}
