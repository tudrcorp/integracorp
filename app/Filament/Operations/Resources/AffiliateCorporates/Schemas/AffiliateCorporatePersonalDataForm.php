<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\AffiliateCorporates\Schemas;

use App\Filament\Operations\Support\AffiliatePersonalDataFields;
use App\Support\Operations\AffiliatePersonalDataUpdater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Formulario de Operaciones para corregir los datos personales de un afiliado
 * corporativo. Plan, cobertura, tarifa, subtotales, frecuencia de pago, estado,
 * parentesco, fecha de ingreso, condición médica y voucher ILS no aparecen.
 * Guardar pasa por {@see AffiliatePersonalDataUpdater}.
 */
final class AffiliateCorporatePersonalDataForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Identificación')
                    ->description('Corrija los datos tal como aparecen en el documento de identidad.')
                    ->icon(Heroicon::OutlinedUser)
                    ->columns(2)
                    ->schema([
                        TextInput::make('first_name')
                            ->label('Nombres')
                            ->placeholder('Ej.: MARÍA JOSÉ')
                            ->required()
                            ->maxLength(255)
                            ->validationMessages([
                                'required' => 'Indique los nombres del afiliado.',
                            ]),
                        TextInput::make('last_name')
                            ->label('Apellidos')
                            ->placeholder('Ej.: PÉREZ GÓMEZ')
                            ->required()
                            ->maxLength(255)
                            ->validationMessages([
                                'required' => 'Indique los apellidos del afiliado.',
                            ]),
                        AffiliatePersonalDataFields::document(),
                        AffiliatePersonalDataFields::sex(),
                        AffiliatePersonalDataFields::birthDate(),
                        AffiliatePersonalDataFields::age(),
                    ]),

                Section::make('Contacto')
                    ->icon(Heroicon::OutlinedPhone)
                    ->columns(2)
                    ->schema([
                        AffiliatePersonalDataFields::phone(),
                        AffiliatePersonalDataFields::email(),
                        TextInput::make('position_company')
                            ->label('Cargo en la empresa')
                            ->placeholder('Ej.: ANALISTA DE COMPRAS')
                            ->maxLength(255),
                        Textarea::make('address')
                            ->label('Dirección')
                            ->placeholder('Urbanización, calle, edificio o casa, punto de referencia')
                            ->rows(3)
                            ->autosize()
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ]),

                Section::make('Contacto de emergencia')
                    ->description('A quién llamar si el afiliado no responde.')
                    ->icon(Heroicon::OutlinedLifebuoy)
                    ->columns(2)
                    ->collapsible()
                    ->schema([
                        TextInput::make('full_name_emergency')
                            ->label('Nombre del contacto')
                            ->placeholder('Ej.: JOSÉ PÉREZ')
                            ->maxLength(255),
                        AffiliatePersonalDataFields::phone('phone_emergency', 'Teléfono del contacto', null),
                    ]),
            ]);
    }
}
