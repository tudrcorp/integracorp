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
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class BusinessAppointmentsForm
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const IOS_SECTION_CLASS = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('businessAppointmentsFormTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Datos de la cita')
                            ->icon(Heroicon::OutlinedCalendarDays)
                            ->schema([
                                Section::make('Datos de la cita')
                                    ->icon(Heroicon::OutlinedCalendarDays)
                                    ->description('Nombre de quien solicita la cita y canales de contacto.')
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
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
                                    ]),
                            ]),
                        Tab::make('Ubicación')
                            ->icon(Heroicon::OutlinedMapPin)
                            ->schema([
                                Section::make('Ubicación')
                                    ->icon(Heroicon::OutlinedMapPin)
                                    ->description('País, estado y ciudad asociados a la cita.')
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
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
                                    ]),
                            ]),
                        Tab::make('Estado de la cita')
                            ->icon(Heroicon::OutlinedFlag)
                            ->schema([
                                Section::make('Estado de la cita')
                                    ->icon(Heroicon::OutlinedFlag)
                                    ->description('Situación actual en el flujo de atención.')
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
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
                                    ]),
                            ]),
                    ]),

                Hidden::make('created_by')
                    ->default(fn (): ?string => auth()->user()?->name)
                    ->hiddenOn('edit')
                    ->dehydrated()
                    ->required(),
            ]);
    }
}
