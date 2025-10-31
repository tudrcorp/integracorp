<?php

namespace App\Filament\Administration\Resources\Sales\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class SaleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('date_activation')
                    ->required(),
                TextInput::make('type_roll'),
                TextInput::make('owner_code')
                    ->required(),
                TextInput::make('code_agency')
                    ->required(),
                TextInput::make('agent_id')
                    ->numeric(),
                TextInput::make('invoice_number')
                    ->required(),
                TextInput::make('affiliation_code'),
                TextInput::make('affiliate_full_name'),
                TextInput::make('affiliate_contact'),
                TextInput::make('affiliate_ci_rif'),
                TextInput::make('affiliate_phone')
                    ->tel(),
                TextInput::make('affiliate_email')
                    ->email(),
                TextInput::make('plan_id')
                    ->numeric(),
                TextInput::make('coverage_id')
                    ->numeric(),
                TextInput::make('service'),
                TextInput::make('persons')
                    ->required(),
                TextInput::make('created_by'),
                TextInput::make('total_amount')
                    ->numeric()
                    ->default(0.0),
                TextInput::make('type'),
                TextInput::make('payment_method'),
                TextInput::make('payment_frequency'),
                TextInput::make('status_payment_commission')
                    ->default('POR PAGAR'),
                TextInput::make('pay_amount_usd')
                    ->numeric()
                    ->default(0.0),
                TextInput::make('pay_amount_ves')
                    ->numeric()
                    ->default(0.0),
                TextInput::make('bank_usd'),
                TextInput::make('bank_ves'),
                TextInput::make('payment_date'),
                TextInput::make('payment_method_usd'),
                TextInput::make('payment_method_ves'),
                Textarea::make('observations')
                    ->columnSpanFull(),
            ]);
    }
}
