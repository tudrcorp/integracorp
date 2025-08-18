<?php

namespace App\Filament\Resources\TelemedicinePatients\Schemas;

use App\Models\City;
use App\Models\State;
use App\Models\Region;
use App\Models\Country;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use App\Models\TelemedicinePatient;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Facades\Blade;
use Filament\Schemas\Components\Wizard;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class TelemedicinePatientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make('Informacion Principal')
                        ->schema([
                            Section::make()
                                ->schema([
                                    Grid::make(4)
                                        ->schema([
                                            TextInput::make('code')
                                                ->label('Nro. de Paciente')
                                                ->prefixIcon('heroicon-m-clipboard-document-check')
                                                ->default(function () {
                                                    if (TelemedicinePatient::max('id') == null) {
                                                        $parte_entera = 0;
                                                    } else {
                                                        $parte_entera = TelemedicinePatient::max('id');
                                                    }
                                                    return 'TEL-PAC-000' . $parte_entera + 1;
                                                })
                                                ->disabled()
                                                ->dehydrated()
                                                ->maxLength(255),

                                        ])->columnSpanFull(),
                                    TextInput::make('full_name')
                                        ->label('Nombre y Apellido')
                                        ->required(),
                                    TextInput::make('nro_identificacion')
                                        ->label('Numero de Identificación')
                                        ->prefixIcon('heroicon-m-identification')
                                        ->helperText('Requerido si el paciente es mayor de 18 años')
                                        ->mask('999999999'),
                                    DatePicker::make('date_birth')
                                        ->label('Fecha de Nacimiento')
                                        ->prefixIcon('heroicon-m-calendar-days')
                                        ->displayFormat('d/m/Y')
                                        ->format('d/m/Y')
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(function (Set $set, $state) {
                                            //calculo de edad
                                            $edad = \Carbon\Carbon::parse($state)->age;
                                            $set('age', $edad);
                                        })
                                        ->validationMessages([
                                            'required'  => 'Campo Requerido',
                                        ]),
                                    TextInput::make('age')
                                        ->prefixIcon('heroicon-m-identification')
                                        ->label('Edad')
                                        ->live()
                                        ->disabled()
                                        ->dehydrated()
                                        ->required(),
                                    Select::make('sex')
                                        ->label('Sexo')
                                        ->live()
                                        ->options([
                                            'MASCULINO' => 'MASCULINO',
                                            'FEMENINO' => 'FEMENINO',
                                        ])
                                        ->searchable()
                                        ->prefixIcon('heroicon-s-globe-europe-africa')
                                        ->required()
                                        ->validationMessages([
                                            'required'  => 'Campo Requerido',
                                        ])
                                        ->preload(),
                                    Select::make('country_code')
                                        ->label('Código de país')
                                        ->options([
                                            '+1'   => '🇺🇸 +1 (Estados Unidos)',
                                            '+44'  => '🇬🇧 +44 (Reino Unido)',
                                            '+49'  => '🇩🇪 +49 (Alemania)',
                                            '+33'  => '🇫🇷 +33 (Francia)',
                                            '+34'  => '🇪🇸 +34 (España)',
                                            '+39'  => '🇮🇹 +39 (Italia)',
                                            '+7'   => '🇷🇺 +7 (Rusia)',
                                            '+55'  => '🇧🇷 +55 (Brasil)',
                                            '+91'  => '🇮🇳 +91 (India)',
                                            '+86'  => '🇨🇳 +86 (China)',
                                            '+81'  => '🇯🇵 +81 (Japón)',
                                            '+82'  => '🇰🇷 +82 (Corea del Sur)',
                                            '+52'  => '🇲🇽 +52 (México)',
                                            '+58'  => '🇻🇪 +58 (Venezuela)',
                                            '+57'  => '🇨🇴 +57 (Colombia)',
                                            '+54'  => '🇦🇷 +54 (Argentina)',
                                            '+56'  => '🇨🇱 +56 (Chile)',
                                            '+51'  => '🇵🇪 +51 (Perú)',
                                            '+502' => '🇬🇹 +502 (Guatemala)',
                                            '+503' => '🇸🇻 +503 (El Salvador)',
                                            '+504' => '🇭🇳 +504 (Honduras)',
                                            '+505' => '🇳🇮 +505 (Nicaragua)',
                                            '+506' => '🇨🇷 +506 (Costa Rica)',
                                            '+507' => '🇵🇦 +507 (Panamá)',
                                            '+593' => '🇪🇨 +593 (Ecuador)',
                                            '+592' => '🇬🇾 +592 (Guyana)',
                                            '+591' => '🇧🇴 +591 (Bolivia)',
                                            '+598' => '🇺🇾 +598 (Uruguay)',
                                            '+20'  => '🇪🇬 +20 (Egipto)',
                                            '+27'  => '🇿🇦 +27 (Sudáfrica)',
                                            '+234' => '🇳🇬 +234 (Nigeria)',
                                            '+212' => '🇲🇦 +212 (Marruecos)',
                                            '+971' => '🇦🇪 +971 (Emiratos Árabes)',
                                            '+92'  => '🇵🇰 +92 (Pakistán)',
                                            '+880' => '🇧🇩 +880 (Bangladesh)',
                                            '+62'  => '🇮🇩 +62 (Indonesia)',
                                            '+63'  => '🇵🇭 +63 (Filipinas)',
                                            '+66'  => '🇹🇭 +66 (Tailandia)',
                                            '+60'  => '🇲🇾 +60 (Malasia)',
                                            '+65'  => '🇸🇬 +65 (Singapur)',
                                            '+61'  => '🇦🇺 +61 (Australia)',
                                            '+64'  => '🇳🇿 +64 (Nueva Zelanda)',
                                            '+90'  => '🇹🇷 +90 (Turquía)',
                                            '+375' => '🇧🇾 +375 (Bielorrusia)',
                                            '+372' => '🇪🇪 +372 (Estonia)',
                                            '+371' => '🇱🇻 +371 (Letonia)',
                                            '+370' => '🇱🇹 +370 (Lituania)',
                                            '+48'  => '🇵🇱 +48 (Polonia)',
                                            '+40'  => '🇷🇴 +40 (Rumania)',
                                            '+46'  => '🇸🇪 +46 (Suecia)',
                                            '+47'  => '🇳🇴 +47 (Noruega)',
                                            '+45'  => '🇩🇰 +45 (Dinamarca)',
                                            '+41'  => '🇨🇭 +41 (Suiza)',
                                            '+43'  => '🇦🇹 +43 (Austria)',
                                            '+31'  => '🇳🇱 +31 (Países Bajos)',
                                            '+32'  => '🇧🇪 +32 (Bélgica)',
                                            '+353' => '🇮🇪 +353 (Irlanda)',
                                            '+375' => '🇧🇾 +375 (Bielorrusia)',
                                            '+380' => '🇺🇦 +380 (Ucrania)',
                                            '+994' => '🇦🇿 +994 (Azerbaiyán)',
                                            '+995' => '🇬🇪 +995 (Georgia)',
                                            '+976' => '🇲🇳 +976 (Mongolia)',
                                            '+998' => '🇺🇿 +998 (Uzbekistán)',
                                            '+84'  => '🇻🇳 +84 (Vietnam)',
                                            '+856' => '🇱🇦 +856 (Laos)',
                                            '+374' => '🇦🇲 +374 (Armenia)',
                                            '+965' => '🇰🇼 +965 (Kuwait)',
                                            '+966' => '🇸🇦 +966 (Arabia Saudita)',
                                            '+972' => '🇮🇱 +972 (Israel)',
                                            '+963' => '🇸🇾 +963 (Siria)',
                                            '+961' => '🇱🇧 +961 (Líbano)',
                                            '+960' => '🇲🇻 +960 (Maldivas)',
                                            '+992' => '🇹🇯 +992 (Tayikistán)',
                                        ])
                                        ->searchable()
                                        ->default('+58')
                                        ->live(onBlur: true)
                                        ->validationMessages([
                                            'required'  => 'Campo Requerido',
                                        ])
                                        ->hiddenOn('edit'),
                                    TextInput::make('phone')
                                        ->prefixIcon('heroicon-s-phone')
                                        ->tel()
                                        ->label('Número de teléfono')
                                        ->validationMessages([
                                            'required'  => 'Campo Requerido',
                                        ])
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                            $countryCode = $get('country_code');
                                            if ($countryCode) {
                                                $cleanNumber = ltrim(preg_replace('/[^0-9]/', '', $state), '0');
                                                $set('phone', $countryCode . $cleanNumber);
                                            }
                                        }),
                                    TextInput::make('email')
                                        ->email()
                                        ->label('Correo Electrónico')
                                        ->prefixIcon('heroicon-m-user')
                                        ->validationMessages([
                                            'required' => 'Campo requerido',
                                        ])
                                        ->maxLength(255),
                                    TextInput::make('address')
                                        ->label('Dirección')
                                        ->required(),
                                    Select::make('country_id')
                                        ->label('País')
                                        ->live()
                                        ->options(Country::all()->pluck('name', 'id'))
                                        ->searchable()
                                        ->prefixIcon('heroicon-s-globe-europe-africa')
                                        ->required()
                                        ->validationMessages([
                                            'required'  => 'Campo Requerido',
                                        ])
                                        ->default(189)
                                        ->preload(),
                                    Select::make('state_id')
                                        ->label('Estado')
                                        ->options(function (Get $get) {
                                            return State::where('country_id', $get('country_id'))->pluck('definition', 'id');
                                        })
                                        ->afterStateUpdated(function (Set $set, $state) {
                                            $region_id = State::where('id', $state)->value('region_id');
                                            $region = Region::where('id', $region_id)->value('definition');
                                            $set('region', $region);
                                        })
                                        ->live()
                                        ->searchable()
                                        ->prefixIcon('heroicon-s-globe-europe-africa')
                                        ->required()
                                        ->validationMessages([
                                            'required'  => 'Campo Requerido',
                                        ])
                                        ->preload(),
                                    TextInput::make('region')
                                        ->label('Región')
                                        ->prefixIcon('heroicon-m-map')
                                        ->disabled()
                                        ->dehydrated()
                                        ->maxLength(255),
                                    Select::make('city_id')
                                        ->label('Ciudad')
                                        ->options(function (Get $get) {
                                            return City::where('country_id', $get('country_id'))->where('state_id', $get('state_id'))->pluck('definition', 'id');
                                        })
                                        ->searchable()
                                        ->prefixIcon('heroicon-s-globe-europe-africa')
                                        ->required()
                                        ->validationMessages([
                                            'required'  => 'Campo Requerido',
                                        ])
                                        ->preload(),
                                    Hidden::make('created_by')->default(Auth::user()->id),
                                ])->columns(3),
                        ]),
                    Step::make('Representante o Contacto')
                        ->hiddenOn('edit')
                        // ->hidden(fn(Get $get) => $get('age') >= 18)
                        ->schema([
                            Section::make()
                                ->schema([
                                    // ...
                                    TextInput::make('re_full_name')
                                        ->label('Nombre y Apellido')
                                        ->required(),
                                    // ...
                                    TextInput::make('re_nro_identificacion')
                                        ->label('Cedula de Identidad')
                                        ->mask('99999999')
                                        ->required(),
                                    // ...
                                    TextInput::make('re_phone')
                                        ->tel()
                                        ->label('Número de Teléfono')
                                        ->required(),
                                    // ...
                                    TextInput::make('re_email')
                                        ->email()
                                        ->label('Correo Electrónico')
                                        ->required(),
                                    Select::make('re_relationship')
                                        ->label('Parentesco')
                                        ->options([
                                            'TUTOR LEGAL' => 'TUTOR LEGAL',
                                            'MADRE'       => 'MADRE',
                                            'PADRE'       => 'PADRE',
                                            'ABUELO'      => 'ABUELO',
                                            'ABUELA'      => 'ABUELA',
                                            'OTRO'        => 'OTRO',
                                        ])
                                        ->required()
                                        ->validationMessages([
                                            'required'  => 'Campo Requerido',
                                        ]),

                                ])->columns(2),
                        ]),
                ])
                    ->submitAction(new HtmlString(Blade::render(<<<BLADE
                    <x-filament::button
                        type="submit"
                        size="sm"
                    >
                        Registrar Paciente
                    </x-filament::button>
                BLADE)))
                    ->columnSpanFull()
            ]);
    }
}