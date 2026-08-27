<?php

namespace App\Filament\General\Resources\Agencies\Schemas;

use App\Filament\Shared\CommercialStructure\ReferidorPercentageField;
use App\Models\Agency;
use App\Support\CommercialStructure\ReferidorAssignmentService;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

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
                                IconEntry::make('is_referidor')
                                    ->label('Es Referidor')
                                    ->boolean(),
                                ReferidorPercentageField::entry(),
                                TextEntry::make('accountManager.name')
                                    ->badge()
                                    ->color('success'),
                            ])->columnSpanFull()->columns(6),
                        Fieldset::make('Red de referidor')
                            ->schema([
                                TextEntry::make('referidor')
                                    ->label('Referidor')
                                    ->state(fn (Agency $record): ?string => ReferidorAssignmentService::assignedReferrerLabel($record))
                                    ->visible(fn (Agency $record): bool => ReferidorAssignmentService::hasAssignedReferrer($record))
                                    ->placeholder('—'),
                                TextEntry::make('referred_general_agencies_list')
                                    ->label('Agencias generales referidas')
                                    ->state(fn (Agency $record): string => ReferidorAssignmentService::referredGeneralAgenciesText($record))
                                    ->visible(fn (Agency $record): bool => ReferidorAssignmentService::isReferrerAgency($record))
                                    ->columnSpanFull(),
                                TextEntry::make('referred_agents_list')
                                    ->label('Agentes y subagentes referidos')
                                    ->state(fn (Agency $record): string => ReferidorAssignmentService::referredAgentsText($record))
                                    ->visible(fn (Agency $record): bool => ReferidorAssignmentService::isReferrerAgency($record))
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull()
                            ->columns(1)
                            ->visible(fn (Agency $record): bool => ReferidorAssignmentService::hasAssignedReferrer($record) || ReferidorAssignmentService::isReferrerAgency($record)),

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
