<?php

namespace App\Filament\Operations\Resources\Affiliates\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class AffiliateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('affiliation_id')
                    ->required()
                    ->numeric(),
                TextInput::make('full_name')
                    ->required(),
                TextInput::make('nro_identificacion')
                    ->required(),
                TextInput::make('sex')
                    ->required(),
                TextInput::make('stature'),
                TextInput::make('birth_date'),
                TextInput::make('age'),
                TextInput::make('weight'),
                TextInput::make('relationship')
                    ->required(),
                TextInput::make('document'),
                TextInput::make('status'),
                Textarea::make('address')
                    ->columnSpanFull(),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('country_id')
                    ->numeric(),
                TextInput::make('state_id')
                    ->numeric(),
                TextInput::make('city_id')
                    ->numeric(),
                TextInput::make('region'),
                TextInput::make('plan_id')
                    ->numeric(),
                TextInput::make('coverage_id')
                    ->numeric(),
                TextInput::make('age_range_id')
                    ->numeric(),
                TextInput::make('vaucherIls'),
                TextInput::make('dateInit'),
                TextInput::make('dateEnd'),
                TextInput::make('numberDays'),
                TextInput::make('document_ils'),
                TextInput::make('fee')
                    ->numeric(),
                TextInput::make('total_amount')
                    ->numeric(),
                TextInput::make('created_by'),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
            ]);
    }
}
