<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\BusinessAppointments\Schemas;

use App\Filament\Business\Resources\BusinessAppointments\BusinessAppointmentLabels;
use App\Models\City;
use App\Models\Country;
use App\Models\State;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class BusinessAppointmentsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos de la cita')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->description('Nombre de quien solicita la cita y canales de contacto.')
                    ->schema([
                        TextInput::make('legal_name')
                            ->label('Nombre o razón social')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ej. María Pérez o Agencia XYZ C.A.')
                            ->columnSpanFull(),
                        Grid::make()
                            ->columns(['default' => 1, 'lg' => 2])
                            ->schema([
                                TextInput::make('phone')
                                    ->label('Teléfono')
                                    ->tel()
                                    ->required()
                                    ->regex('/^[0-9+()\s-]+$/')
                                    ->validationMessages([
                                        'regex' => 'Introduce un teléfono válido (números; opcional +, espacios o guiones).',
                                    ])
                                    ->placeholder('04141234567')
                                    ->autocomplete('tel-national'),
                                TextInput::make('email')
                                    ->label('Correo electrónico')
                                    ->email()
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(
                                        table: 'business_appointments',
                                        column: 'email',
                                        ignoreRecord: true,
                                    )
                                    ->placeholder('correo@ejemplo.com')
                                    ->autocomplete('email'),
                            ]),
                    ]),
                Section::make('Ubicación')
                    ->icon(Heroicon::OutlinedMapPin)
                    ->description('País, estado y ciudad asociados a la cita.')
                    ->schema([
                        Grid::make()
                            ->columns(['default' => 1, 'md' => 3])
                            ->schema([
                                Select::make('country_id')
                                    ->label('País')
                                    ->live()
                                    ->options(
                                        Country::query()
                                            ->orderBy('name')
                                            ->pluck('name', 'id')
                                    )
                                    ->searchable()
                                    ->prefixIcon(Heroicon::OutlinedGlobeAmericas)
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Selecciona un país.',
                                    ])
                                    ->default(189)
                                    ->preload()
                                    ->native(false),
                                Select::make('state_id')
                                    ->label('Estado')
                                    ->options(function (Get $get): array {
                                        $countryId = $get('country_id');

                                        if (blank($countryId)) {
                                            return [];
                                        }

                                        return State::query()
                                            ->where('country_id', $countryId)
                                            ->orderBy('definition')
                                            ->pluck('definition', 'id')
                                            ->all();
                                    })
                                    ->live()
                                    ->searchable()
                                    ->prefixIcon(Heroicon::OutlinedMap)
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Selecciona un estado.',
                                    ])
                                    ->preload()
                                    ->native(false),
                                Select::make('city_id')
                                    ->label('Ciudad')
                                    ->options(function (Get $get): array {
                                        $countryId = $get('country_id');
                                        $stateId = $get('state_id');

                                        if (blank($countryId) || blank($stateId)) {
                                            return [];
                                        }

                                        return City::query()
                                            ->where('country_id', $countryId)
                                            ->where('state_id', $stateId)
                                            ->orderBy('definition')
                                            ->pluck('definition', 'id')
                                            ->all();
                                    })
                                    ->searchable()
                                    ->prefixIcon(Heroicon::OutlinedBuildingOffice2)
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Selecciona una ciudad.',
                                    ])
                                    ->preload()
                                    ->native(false),
                            ]),
                    ]),
                Section::make('Estado de la cita')
                    ->icon(Heroicon::OutlinedFlag)
                    ->description('Situación actual en el flujo de atención.')
                    ->schema([
                        Select::make('status')
                            ->label('Estado')
                            ->options(BusinessAppointmentLabels::statusOptions())
                            ->required()
                            ->default('PENDIENTE')
                            ->preload()
                            ->searchable()
                            ->native(false)
                            ->columnSpanFull(),
                    ]),
                Hidden::make('created_by')
                    ->default(fn (): ?string => auth()->user()?->name)
                    ->hiddenOn('edit')
                    ->dehydrated()
                    ->required(),
            ]);
    }
}
