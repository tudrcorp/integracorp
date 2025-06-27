<?php

namespace App\Filament\Resources\Collections\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CollectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('sale_id')
                    ->numeric(),
                TextInput::make('include_date')
                    ->required(),
                TextInput::make('owner_code')
                    ->required(),
                TextInput::make('code_agency')
                    ->required(),
                TextInput::make('agent_id')
                    ->numeric(),
                TextInput::make('collection_invoice_number')
                    ->required(),
                TextInput::make('quote_number')
                    ->required(),
                TextInput::make('affiliation_code'),
                TextInput::make('affiliate_full_name'),
                TextInput::make('affiliate_contact'),
                TextInput::make('affiliate_ci_rif'),
                TextInput::make('affiliate_phone')
                    ->tel(),
                TextInput::make('affiliate_email')
                    ->email(),
                TextInput::make('affiliate_status'),
                TextInput::make('plan_id')
                    ->required()
                    ->numeric(),
                TextInput::make('coverage_id')
                    ->numeric(),
                TextInput::make('service'),
                TextInput::make('persons')
                    ->required(),
                TextInput::make('type')
                    ->required(),
                TextInput::make('reference'),
                TextInput::make('payment_method'),
                TextInput::make('payment_frequency'),
                TextInput::make('next_payment_date'),
                TextInput::make('total_amount')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('expiration_date'),
                TextInput::make('status')
                    ->required()
                    ->default('POR PAGAR'),
                TextInput::make('days')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('created_by'),
                TextInput::make('pay_amount_usd')
                    ->numeric()
                    ->default(0.0),
                TextInput::make('pay_amount_ves')
                    ->numeric()
                    ->default(0.0),
                TextInput::make('bank_usd'),
                TextInput::make('bank_ves'),
            ]);
    }
}
