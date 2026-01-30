<?php

namespace App\Filament\Operations\Resources\AffiliateCorporates\Schemas;

use App\Models\AffiliateCorporate;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class AffiliateCorporateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
            Section::make()
                ->description(fn(AffiliateCorporate $record) => 'AFILIADO: ' . $record->full_name . ' | ' . 'EDAD: ' . $record->age . ' años | ' . 'SEXO: ' . $record->sex)
                ->columnSpanFull()
                ->icon(Heroicon::Bars3BottomLeft)
                ->schema([
                    Fieldset::make('INFORMACIÓN PRINCIPAL')
                        ->schema([
                            TextEntry::make('full_name')
                                ->label('Nombre Completo:')
                                ->badge()
                                ->default(fn(AffiliateCorporate $record) => strtoupper($record->full_name))
                                ->color('success'),
                            TextEntry::make('nro_identificacion')
                                ->label('Número de Identificación:')
                                ->prefix('V-')
                                ->badge()
                                ->color('success'),
                            TextEntry::make('birth_date')
                                ->label('Fecha de Nacimiento:'),
                            TextEntry::make('age')
                                ->label('Edad:')
                                ->suffix(' años'),
                            TextEntry::make('sex')
                                ->label('Sexo:'),
                            TextEntry::make('phone')
                                ->label('Teléfono:'),
                            TextEntry::make('email')
                                ->label('Correo Electrónico:'),
                            TextEntry::make('address')
                                ->label('Dirección:'),
                            TextEntry::make('city.definition')
                                ->label('Ciudad:'),
                            TextEntry::make('country.name')
                                ->label('País:'),
                            TextEntry::make('state.definition')
                                ->label('Estado:'),
                            TextEntry::make('region')
                                ->label('Región:'),

                            TextEntry::make('created_at')
                                ->label('Fecha de Registro:')
                                ->badge()
                                ->dateTime(),


                        ])->columnSpanFull()->columns(4),

                    Fieldset::make('INFORMACIÓN DEL CORPORATIVO')
                        ->schema([
                            TextEntry::make('affiliationCorporate.name_corporate')
                                ->badge()
                                ->color('info')
                                ->icon('fluentui-money-hand-20')
                                ->label('Nombre del Corporativo:'),
                        TextEntry::make('affiliationCorporate.rif')
                            ->badge()
                            ->color('info')
                            ->icon('fluentui-money-hand-20')
                            ->label('RIF:'),
                        TextEntry::make('affiliationCorporate.address')
                            ->badge()
                            ->color('info')
                            ->icon('fluentui-money-hand-20')
                            ->label('Dirección:'),
                        TextEntry::make('affiliationCorporate.phone')
                            ->badge()
                            ->color('info')
                            ->icon('fluentui-money-hand-20')
                            ->label('Teléfono:'),
                        TextEntry::make('affiliationCorporate.email')
                            ->badge()
                            ->color('info')
                            ->icon('fluentui-money-hand-20')
                            ->label('Correo Electrónico:'),
                        ])->columnSpanFull()->columns(4),

                    Fieldset::make('INFORMACIÓN DE LA AFILIACIÓN')
                        ->schema([
                            TextEntry::make('affiliationCorporate.code')
                                ->badge()
                                ->color('info')
                                ->icon('fluentui-money-hand-20')
                                ->label('Código de Afiliación:'),
                            TextEntry::make('plan.description')
                                ->badge()
                                ->color('info')
                                ->icon('fluentui-person-available-16')
                                ->label('Plan:'),
                            TextEntry::make('plan.businessUnit.definition')
                                ->label('Unidad de Negocio:')
                                ->badge()
                                ->color('info')
                                ->icon('fluentui-person-available-16'),
                            TextEntry::make('coverage.price')
                                ->badge()
                                ->color('info')
                                ->icon('fluentui-money-hand-20')
                                ->label('Cobertura:'),
                            TextEntry::make('affiliationCorporate.service_providers')
                                ->badge()
                                ->color('info')
                                ->icon('fluentui-money-hand-20')
                                ->label('Proveedores de Servicios:'),
                            TextEntry::make('affiliationCorporate.effective_date')
                                ->badge()
                                ->color('info')
                                ->icon('fluentui-money-hand-20')
                                ->label('Fecha de Vigencia:'),
                            TextEntry::make('status')
                                ->badge()
                                ->color('info')
                                ->icon('fluentui-money-hand-20')
                                ->label('Estatus del Afiliado:'),
                        ])->columnSpanFull()->columns(4),

                    Fieldset::make('INFORMACIÓN DE CONTACTO')
                        ->schema([
                            TextEntry::make('affiliationCorporate.full_name_contact')
                                ->badge()
                                ->color('info')
                                ->icon('fluentui-money-hand-20')
                                ->label('Nombre del Contacto:'),
                            TextEntry::make('affiliationCorporate.nro_identificacion_contact')
                                ->badge()
                                ->color('info')
                                ->icon('fluentui-money-hand-20')
                                ->label('Número de Identificación:'),
                            TextEntry::make('affiliationCorporate.email_contact')
                                ->badge()
                                ->color('info')
                                ->icon('fluentui-money-hand-20')
                                ->label('Correo Electrónico:'),
                            TextEntry::make('affiliationCorporate.phone_contact')
                                ->badge()
                                ->color('info')
                                ->icon('fluentui-money-hand-20')
                                ->label('Teléfono:'),
                        ])->columnSpanFull()->columns(4),

                    Fieldset::make('INFORMACIÓN DE BENEFICIOS Y SUS LIMITES')
                        ->schema([
                            TextEntry::make('plan.benefitPlans.description')
                                // ->belowContent(Text::make('This is the user\'s full name.')->weight(FontWeight::Bold))
                                ->label('Beneficios del Plan:')
                                ->icon('heroicon-c-check')
                                ->badge()
                                ->color('success')
                                // ->bulleted()
                                ->listWithLineBreaks(),
                            TextEntry::make('plan.benefitPlans.limit.description')
                                // ->belowContent(Text::make('This is the user\'s full name.')->weight(FontWeight::Bold))
                                ->label('Limite por Beneficios:')
                                ->icon('heroicon-s-arrow-small-right')
                                ->badge()
                                ->color('gray')
                                // ->bulleted()
                                ->listWithLineBreaks(),
                        ])->columnSpanFull()->columns(2),
                ])->columnSpanFull(),



































                TextEntry::make('affiliation_corporate_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('first_name')
                    ->placeholder('-'),
                TextEntry::make('last_name')
                    ->placeholder('-'),
                TextEntry::make('nro_identificacion')
                    ->placeholder('-'),
                TextEntry::make('birth_date')
                    ->placeholder('-'),
                TextEntry::make('age')
                    ->placeholder('-'),
                TextEntry::make('sex')
                    ->placeholder('-'),
                TextEntry::make('phone')
                    ->placeholder('-'),
                TextEntry::make('email')
                    ->label('Email address')
                    ->placeholder('-'),
                TextEntry::make('condition_medical')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('initial_date')
                    ->placeholder('-'),
                TextEntry::make('position_company')
                    ->placeholder('-'),
                TextEntry::make('address')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('full_name_emergency')
                    ->placeholder('-'),
                TextEntry::make('phone_emergency')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('plan_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('coverage_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('payment_frequency')
                    ->placeholder('-'),
                TextEntry::make('fee')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('subtotal_anual')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('subtotal_payment_frequency')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('subtotal_daily')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('status')
                    ->placeholder('-'),
                TextEntry::make('created_by')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('vaucherIls')
                    ->placeholder('-'),
                TextEntry::make('dateInit')
                    ->placeholder('-'),
                TextEntry::make('dateEnd')
                    ->placeholder('-'),
                TextEntry::make('numberDays')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('document_ils')
                    ->placeholder('-'),
                TextEntry::make('corporate_quote_id')
                    ->numeric()
                    ->placeholder('-'),
            ]);
    }
}
