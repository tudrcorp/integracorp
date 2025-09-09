<?php

namespace App\Filament\Marketing\Resources\AffiliationCorporates\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class AffiliationCorporateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('corporate_quote_id')
                    ->required()
                    ->numeric(),
                TextInput::make('owner_code')
                    ->required(),
                TextInput::make('code'),
                TextInput::make('code_agency')
                    ->required(),
                TextInput::make('agent_id'),
                TextInput::make('name_corporate')
                    ->required(),
                TextInput::make('rif')
                    ->required(),
                TextInput::make('address')
                    ->required(),
                TextInput::make('city_id')
                    ->required()
                    ->numeric(),
                TextInput::make('country_id')
                    ->required()
                    ->numeric(),
                TextInput::make('region_id')
                    ->required(),
                TextInput::make('phone')
                    ->tel()
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                TextInput::make('full_name_contact')
                    ->required(),
                TextInput::make('nro_identificacion_contact')
                    ->required(),
                TextInput::make('phone_contact')
                    ->tel()
                    ->required(),
                TextInput::make('email_contact')
                    ->email()
                    ->required(),
                TextInput::make('date_affiliation'),
                TextInput::make('created_by')
                    ->required(),
                TextInput::make('status')
                    ->required(),
                TextInput::make('document'),
                Textarea::make('observations')
                    ->columnSpanFull(),
                TextInput::make('payment_frequency')
                    ->required(),
                TextInput::make('fee_anual')
                    ->required(),
                TextInput::make('total_amount')
                    ->numeric(),
                TextInput::make('vaucher_ils'),
                TextInput::make('date_payment_initial_ils'),
                TextInput::make('date_payment_final_ils'),
                TextInput::make('document_ils')
                    ->default('CORPORATIVO'),
                TextInput::make('state_id')
                    ->numeric(),
                TextInput::make('poblation')
                    ->numeric(),
                TextInput::make('activated_at'),
            ]);
    }
}
