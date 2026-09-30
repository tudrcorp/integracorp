<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\Affiliates\Schemas;

use App\Filament\Operations\Support\AffiliatePersonalDataFields;
use App\Models\Affiliate;
use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\State;
use App\Support\Operations\AffiliatePersonalDataUpdater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Formulario de Operaciones para corregir los datos personales de un afiliado
 * individual. Plan, cobertura, tarifa y montos no aparecen: los administra
 * Negocios. Guardar pasa por {@see AffiliatePersonalDataUpdater}.
 */
final class AffiliatePersonalDataForm
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
                        TextInput::make('full_name')
                            ->label('Nombre completo')
                            ->placeholder('Ej.: MARÍA JOSÉ PÉREZ GÓMEZ')
                            ->required()
                            ->minLength(3)
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->validationMessages([
                                'required' => 'Indique el nombre completo del afiliado.',
                                'min' => 'El nombre es demasiado corto.',
                            ]),
                        AffiliatePersonalDataFields::document(),
                        AffiliatePersonalDataFields::sex(),
                        AffiliatePersonalDataFields::birthDate(),
                        AffiliatePersonalDataFields::age(),
                        Callout::make('La edad quedó fuera del rango tarifario')
                            ->description(fn (Get $get, ?Affiliate $record): ?string => self::ageRangeDescription($record, $get('age')))
                            ->warning()
                            ->columnSpanFull()
                            ->visible(fn (Get $get, ?Affiliate $record): bool => self::ageRangeDescription($record, $get('age')) !== null),
                    ]),

                Section::make('Contacto')
                    ->icon(Heroicon::OutlinedPhone)
                    ->columns(2)
                    ->schema([
                        AffiliatePersonalDataFields::phone(),
                        AffiliatePersonalDataFields::email(),
                    ]),

                Section::make('Ubicación')
                    ->icon(Heroicon::OutlinedMapPin)
                    ->columns(2)
                    ->schema([
                        Select::make('country_id')
                            ->label('País')
                            ->options(fn (): array => Country::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('state_id', null);
                                $set('city_id', null);
                                $set('region', null);
                            }),
                        Select::make('state_id')
                            ->label('Estado')
                            ->options(fn (Get $get): array => filled($get('country_id'))
                                ? State::query()->where('country_id', $get('country_id'))->orderBy('definition')->pluck('definition', 'id')->all()
                                : [])
                            ->searchable()
                            ->live()
                            ->disabled(fn (Get $get): bool => blank($get('country_id')))
                            ->afterStateUpdated(function (Set $set, $state): void {
                                $set('city_id', null);
                                $set('region', self::regionForState($state));
                            }),
                        Select::make('city_id')
                            ->label('Ciudad')
                            ->options(fn (Get $get): array => filled($get('state_id'))
                                ? City::query()->where('state_id', $get('state_id'))->orderBy('definition')->pluck('definition', 'id')->all()
                                : [])
                            ->searchable()
                            ->disabled(fn (Get $get): bool => blank($get('state_id'))),
                        TextInput::make('region')
                            ->label('Región')
                            ->helperText('Se completa sola según el estado.')
                            ->disabled()
                            ->dehydrated(),
                        Textarea::make('address')
                            ->label('Dirección')
                            ->placeholder('Urbanización, calle, edificio o casa, punto de referencia')
                            ->rows(3)
                            ->autosize()
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ]),

                Section::make('Datos físicos')
                    ->icon(Heroicon::OutlinedScale)
                    ->columns(2)
                    ->collapsible()
                    ->schema([
                        TextInput::make('stature')
                            ->label('Estatura')
                            ->numeric()
                            ->minValue(0.3)
                            ->maxValue(2.5)
                            ->step(0.01)
                            ->suffix('m')
                            ->placeholder('Ej.: 1.68'),
                        TextInput::make('weight')
                            ->label('Peso')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(400)
                            ->step(0.1)
                            ->suffix('kg')
                            ->placeholder('Ej.: 70'),
                    ]),
            ]);
    }

    public static function regionForState(mixed $stateId): ?string
    {
        if (blank($stateId)) {
            return null;
        }

        $regionId = State::query()->whereKey($stateId)->value('region_id');

        return $regionId === null ? null : Region::query()->whereKey($regionId)->value('definition');
    }

    /**
     * Aviso en vivo cuando la edad calculada no cae en el rango tarifario del
     * afiliado. El guardado no recalcula la tarifa.
     */
    public static function ageRangeDescription(?Affiliate $record, mixed $age): ?string
    {
        if ($record === null || ! is_numeric($age) || $record->age_range_id === null) {
            return null;
        }

        $probe = $record->replicate();
        $probe->age_range_id = $record->age_range_id;
        $probe->age = (string) $age;

        $warning = AffiliatePersonalDataUpdater::ageRangeWarning($probe);

        return $warning === null ? null : $warning.' Al guardar, Afiliaciones recibirá este aviso.';
    }
}
