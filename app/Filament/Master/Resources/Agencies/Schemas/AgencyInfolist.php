<?php

namespace App\Filament\Master\Resources\Agencies\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Fieldset;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;

class AgencyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informacion General de la Agencia')
                    ->schema([
                        Fieldset::make('Informacion de la entidad')
                            ->schema([
                                TextEntry::make('code')
                                    ->badge()
                                    ->color('success')
                                    ->placeholder('-'),
                                TextEntry::make('typeAgency.definition')
                                    ->badge()
                                    ->color('success'),
                                TextEntry::make('accountManager.name')
                                    ->badge()
                                    ->color('success'),
                            ])->columnSpanFull()->columns(5),

                        Fieldset::make('Datos Principales de la Agencia')
                            ->schema([
                                TextEntry::make('name_corporative')
                                    ->badge()
                                    ->color('success')
                                    ->placeholder('-'),
                                TextEntry::make('rif')
                                    ->badge()
                                    ->color('success')
                                    ->placeholder('-'),
                                TextEntry::make('ci_responsable')
                                    ->placeholder('-'),
                                TextEntry::make('address')
                                    ->placeholder('-'),
                                TextEntry::make('email')
                                    ->label('Email address'),
                                TextEntry::make('phone')
                                    ->placeholder('-'),
                                TextEntry::make('user_instagram')
                                    ->placeholder('-'),
                                TextEntry::make('country.name')
                                    ->label('Country')
                                    ->placeholder('-'),
                                TextEntry::make('state.definition')
                                    ->label('State')
                                    ->placeholder('-'),
                                TextEntry::make('city.definition')
                                    ->label('City')
                                    ->placeholder('-'),
                                TextEntry::make('region')
                                    ->placeholder('-'),
                            ])->columnSpanFull()->columns(5),

                        Fieldset::make('Datos Bancarios Moneda Nacional')
                            ->schema([
                                TextEntry::make('local_beneficiary_name')
                                    ->placeholder('-'),
                                TextEntry::make('local_beneficiary_rif')
                                    ->placeholder('-'),
                                TextEntry::make('local_beneficiary_account_number')
                                    ->placeholder('-'),
                                TextEntry::make('local_beneficiary_account_bank')
                                    ->placeholder('-'),
                                TextEntry::make('local_beneficiary_account_type')
                                    ->placeholder('-'),
                                TextEntry::make('local_beneficiary_phone_pm')
                                    ->placeholder('-'),
                            ])->columnSpanFull()->columns(5),

                        Fieldset::make('Datos Bancarios Moneda Extra')
                            ->schema([
                                TextEntry::make('extra_beneficiary_name')
                                    ->placeholder('-'),
                                TextEntry::make('extra_beneficiary_ci_rif')
                                    ->placeholder('-'),
                                TextEntry::make('extra_beneficiary_account_number')
                                    ->placeholder('-'),
                                TextEntry::make('extra_beneficiary_account_bank')
                                    ->placeholder('-'),
                                TextEntry::make('extra_beneficiary_account_type')
                                    ->placeholder('-'),
                                TextEntry::make('extra_beneficiary_route')
                                    ->placeholder('-'),
                                TextEntry::make('extra_beneficiary_zelle')
                                    ->placeholder('-'),
                                TextEntry::make('extra_beneficiary_ach')
                                    ->placeholder('-'),
                                TextEntry::make('extra_beneficiary_swift')
                                    ->placeholder('-'),
                                TextEntry::make('extra_beneficiary_aba')
                                    ->placeholder('-'),
                                TextEntry::make('extra_beneficiary_address')
                                    ->placeholder('-'),
                            ])->columnSpanFull()->columns(5),

                        Fieldset::make('Comiciones')
                            ->schema([
                                TextEntry::make('commission_tdec')
                                    ->numeric()
                                    ->placeholder('-'),
                                TextEntry::make('commission_tdec_renewal')
                                    ->numeric()
                                    ->placeholder('-'),
                                TextEntry::make('commission_tdev')
                                    ->numeric()
                                    ->placeholder('-'),
                                TextEntry::make('commission_tdev_renewal')
                                    ->numeric()
                                    ->placeholder('-'),
                            ])->columnSpanFull()->columns(5),

                    ])->columnSpanFull(),

            ]);
    }
}