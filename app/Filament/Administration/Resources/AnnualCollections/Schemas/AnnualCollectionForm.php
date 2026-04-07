<?php

namespace App\Filament\Administration\Resources\AnnualCollections\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AnnualCollectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('sale_id')
                    ->relationship('sale', 'id'),
                TextInput::make('collection_invoice_number')
                    ->required(),
                TextInput::make('include_date')
                    ->required(),
                TextInput::make('owner_code')
                    ->required(),
                TextInput::make('code_agency')
                    ->required(),
                Select::make('agent_id')
                    ->relationship('agent', 'name'),
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
                Select::make('plan_id')
                    ->relationship('plan', 'id'),
                Select::make('coverage_id')
                    ->relationship('coverage', 'id'),
                TextInput::make('persons')
                    ->required(),
                TextInput::make('type')
                    ->required(),
                TextInput::make('service'),
                TextInput::make('next_payment_date'),
                TextInput::make('expiration_date'),
                TextInput::make('status')
                    ->required()
                    ->default('POR PAGAR'),
                TextInput::make('created_by'),
                Toggle::make('month_1')
                    ->required(),
                Toggle::make('month_2')
                    ->required(),
                Toggle::make('month_3')
                    ->required(),
                Toggle::make('month_4')
                    ->required(),
                Toggle::make('month_5')
                    ->required(),
                Toggle::make('month_6')
                    ->required(),
                Toggle::make('month_7')
                    ->required(),
                Toggle::make('month_8')
                    ->required(),
                Toggle::make('month_9')
                    ->required(),
                Toggle::make('month_10')
                    ->required(),
                Toggle::make('month_11')
                    ->required(),
                Toggle::make('month_12')
                    ->required(),
            ]);
    }
}
