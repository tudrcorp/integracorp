<?php

namespace App\Filament\Business\Resources\AffiliationCorporates\Schemas;

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
                TextEntry::make('code')
                    ->placeholder('-'),
                TextEntry::make('code_agency'),
                TextEntry::make('agent_id')
                    ->placeholder('-'),
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
                TextEntry::make('date_affiliation')
                    ->placeholder('-'),
                TextEntry::make('created_by'),
                TextEntry::make('status'),
                TextEntry::make('document')
                    ->placeholder('-'),
                TextEntry::make('observations')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('payment_frequency'),
                TextEntry::make('fee_anual'),
                TextEntry::make('total_amount')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('vaucher_ils')
                    ->placeholder('-'),
                TextEntry::make('date_payment_initial_ils')
                    ->placeholder('-'),
                TextEntry::make('date_payment_final_ils')
                    ->placeholder('-'),
                TextEntry::make('document_ils')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('state_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('poblation')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('activated_at')
                    ->placeholder('-'),
            ]);
    }
}
