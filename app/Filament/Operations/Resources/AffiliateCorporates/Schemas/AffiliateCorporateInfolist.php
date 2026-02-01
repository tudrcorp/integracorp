<?php

namespace App\Filament\Operations\Resources\AffiliateCorporates\Schemas;

use App\Models\AffiliateCorporate;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
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

                    Grid::make(2)->schema([
                        Fieldset::make('INFORMACIÓN DE BENEFICIOS')
                            ->schema([
                                TextEntry::make('plan.benefitPlans.description')
                                    ->label('Beneficios del Plan:')
                                    ->badge()
                                    ->color('success')
                                    ->listWithLineBreaks(),
                            ]),
                        Fieldset::make('INFORMACIÓN DE SUS LIMITES')
                            ->schema([
                                TextEntry::make('plan.benefitPlans.limit.description')
                                    ->label('Limite por Beneficios:')
                                    ->badge()
                                    ->color('gray')
                                    ->listWithLineBreaks(),
                            ]),
                    ])
                ])->columnSpanFull(),
            ]);
    }
}
