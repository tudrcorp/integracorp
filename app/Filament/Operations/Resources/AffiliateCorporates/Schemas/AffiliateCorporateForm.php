<?php

namespace App\Filament\Operations\Resources\AffiliateCorporates\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class AffiliateCorporateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('affiliation_corporate_id')
                    ->numeric(),
                TextInput::make('first_name'),
                TextInput::make('last_name'),
                TextInput::make('nro_identificacion'),
                TextInput::make('birth_date'),
                TextInput::make('age'),
                TextInput::make('sex'),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                Textarea::make('condition_medical')
                    ->columnSpanFull(),
                TextInput::make('initial_date'),
                TextInput::make('position_company'),
                Textarea::make('address')
                    ->columnSpanFull(),
                TextInput::make('full_name_emergency'),
                TextInput::make('phone_emergency')
                    ->tel(),
                TextInput::make('plan_id')
                    ->numeric(),
                TextInput::make('coverage_id')
                    ->numeric(),
                TextInput::make('payment_frequency'),
                TextInput::make('fee')
                    ->numeric(),
                TextInput::make('subtotal_anual')
                    ->numeric(),
                TextInput::make('subtotal_payment_frequency')
                    ->numeric(),
                TextInput::make('subtotal_daily')
                    ->numeric(),
                TextInput::make('status'),
                TextInput::make('created_by')
                    ->numeric(),
                TextInput::make('vaucherIls'),
                TextInput::make('dateInit'),
                TextInput::make('dateEnd'),
                TextInput::make('numberDays')
                    ->numeric(),
                TextInput::make('document_ils'),
                TextInput::make('corporate_quote_id')
                    ->numeric(),
            ]);
    }
}
