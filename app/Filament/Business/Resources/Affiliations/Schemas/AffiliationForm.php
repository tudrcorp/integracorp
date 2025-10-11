<?php

namespace App\Filament\Business\Resources\Affiliations\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AffiliationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('individual_quote_id')
                    ->numeric(),
                TextInput::make('owner_code'),
                TextInput::make('code')
                    ->required(),
                TextInput::make('code_agency')
                    ->required(),
                TextInput::make('agent_id')
                    ->numeric(),
                TextInput::make('plan_id')
                    ->numeric(),
                TextInput::make('coverage_id')
                    ->numeric(),
                TextInput::make('payment_frequency'),
                TextInput::make('code_individual_quote'),
                TextInput::make('full_name_payer'),
                TextInput::make('nro_identificacion_payer'),
                TextInput::make('phone_payer')
                    ->tel(),
                TextInput::make('email_payer')
                    ->email(),
                TextInput::make('relationship_payer'),
                TextInput::make('full_name_ti'),
                TextInput::make('nro_identificacion_ti'),
                TextInput::make('sex_ti'),
                TextInput::make('birth_date_ti'),
                TextInput::make('adress_ti'),
                TextInput::make('city_id_ti'),
                TextInput::make('state_id_ti'),
                TextInput::make('country_id_ti'),
                TextInput::make('region_ti'),
                TextInput::make('phone_ti')
                    ->tel(),
                TextInput::make('email_ti')
                    ->email(),
                Toggle::make('cuestion_1'),
                Toggle::make('cuestion_2'),
                Toggle::make('cuestion_3'),
                Toggle::make('cuestion_4'),
                Toggle::make('cuestion_5'),
                Toggle::make('cuestion_6'),
                Toggle::make('cuestion_7'),
                Toggle::make('cuestion_8'),
                Toggle::make('cuestion_9'),
                Toggle::make('cuestion_10'),
                Toggle::make('cuestion_11'),
                Toggle::make('cuestion_12'),
                Toggle::make('cuestion_13'),
                Toggle::make('cuestion_14'),
                Toggle::make('cuestion_15'),
                Toggle::make('cuestion_16'),
                Textarea::make('observations_cuestions')
                    ->columnSpanFull(),
                TextInput::make('full_name_agent'),
                TextInput::make('code_agent'),
                TextInput::make('date_today'),
                TextInput::make('created_by'),
                TextInput::make('status')
                    ->required(),
                TextInput::make('document')
                    ->default('0'),
                TextInput::make('activated_at'),
                TextInput::make('family_members'),
                TextInput::make('vaucher_ils'),
                TextInput::make('date_payment_initial_ils'),
                TextInput::make('date_payment_final_ils'),
                TextInput::make('document_ils'),
                Textarea::make('observations_payment')
                    ->columnSpanFull(),
                TextInput::make('fee_anual')
                    ->numeric()
                    ->default(0.0),
                TextInput::make('total_amount')
                    ->numeric()
                    ->default(0.0),
                TextInput::make('signature_agent'),
                Textarea::make('upload_documents')
                    ->columnSpanFull(),
                TextInput::make('signature_ti'),
                Textarea::make('observations')
                    ->columnSpanFull(),
                TextInput::make('owner_agent'),
                TextInput::make('activation_date'),
                Toggle::make('feedback'),
                Toggle::make('feedback_dos'),
                TextInput::make('age')
                    ->numeric(),
            ]);
    }
}
