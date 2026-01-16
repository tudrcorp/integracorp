<?php

namespace App\Filament\Business\Resources\TravelAgencies\Schemas;

use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\State;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class TravelAgencyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
            Section::make("Informacion Principal")
                ->description("Formulario para la creacion de una agencia de viajes")
                ->schema([
                    FileUpload::make('logo')
                        ->label('Logo de la Agencia')
                        ->image()
                        ->required(),

                ])->columnSpanFull()->columns(4),
                Section::make("Informacion Principal")
                    ->description("Formulario para la creacion de una agencia de viajes")
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        Select::make('typeIdentification')
                            ->label('Tipo de identificacion')
                            ->options([
                                'V' => 'V',
                                'E' => 'E',
                                'J' => 'J',
                            ])
                            ->required(),
                        TextInput::make('numberIdentification')
                            ->label('Numero de identificacion')
                            ->numeric()
                            ->required(),
                        TextInput::make('userPortalWeb')
                            ->label('Usuario portal web'),
                        DatePicker::make('aniversary')
                            ->label('Fecha Aniversario de la Agencia')
                            ->format('d/m/Y'),
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
                            ->required()
                            ->live(onBlur: true)
                            ->validationMessages([
                                'required'  => 'Campo Requerido',
                            ])
                            ->hiddenOn('edit'),
                        TextInput::make('phone')
                            ->prefixIcon('heroicon-s-phone')
                            ->tel()
                            ->label('Número de teléfono')
                            ->required()
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
                            ->preload(),
                        Select::make('state_id')
                            ->label('Estado')
                            ->options(function (Get $get) {
                                if ($get('country_id')) {
                                    return State::where('country_id', $get('country_id'))->pluck('definition', 'id');
                                }
                                return [];
                            })
                            ->afterStateUpdated(function (Set $set, $state) {
                                $region_id = State::where('id', $state)->value('region_id');
                                $region = Region::where('id', $region_id)->value('definition');
                                $set('region', $region);
                            })
                            ->live()
                            ->searchable()
                            ->prefixIcon('heroicon-s-globe-europe-africa')
                            ->validationMessages([
                                'required'  => 'Campo Requerido',
                            ])
                            ->preload(),
                        Select::make('city_id')
                            ->label('Ciudad')
                            ->options(function (Get $get) {
                                if ($get('state_id')) {
                                    return City::where('state_id', $get('state_id'))->pluck('definition', 'id');
                                }
                                return [];
                            })
                            ->searchable()
                            ->prefixIcon('heroicon-s-globe-europe-africa')
                            ->validationMessages([
                                'required'  => 'Campo Requerido',
                            ])
                            ->preload(),
                        TextInput::make('phoneAdditional')
                            ->label('Número de teléfono adicional')
                            ->tel(),
                        TextInput::make('email')
                            ->label('Email address')
                            ->email(),
                        TextInput::make('userInstagram')
                            ->label('Usuario Instagram'),

                        TextInput::make('status')
                            ->label('Status')
                            ->default('Activo'),
                        DatePicker::make('fechaIngreso')
                            ->label('Fecha de Ingreso')
                            ->format('d/m/Y')
                            ->default(now()),
                        TextInput::make('representante')
                            ->label('Representante'),
                        TextInput::make('idRepresentante')
                            ->label('ID Representante'),
                        DatePicker::make('FechaNacimientoRepresentante')
                            ->label('Fecha de Nacimiento Representante')
                            ->format('d/m/Y'),

                        Hidden::make('createdBy')->default(Auth::user()->name)->hiddenOn('edit'),
                        Hidden::make('updatedBy')->default(Auth::user()->name)->hiddenOn('create'),

                    ])->columnSpanFull()->columns(4),

                Section::make('Información Jerarquica')
                    ->description("Informacion Jerarquica de la Agencia y Comiciones")
                    ->columns(2)
                    ->schema([
                        Select::make('classification')
                            ->label('Clasificación')
                            ->options([
                                'AGENCIA DE VIEAJES'=> 'AGENCIA DE VIEAJES',
                                'AGENTE'=> 'AGENTE',
                            ])
                            ->required()
                            ->validationMessages([
                                'required'  => 'Campo Requerido',
                            ]),
                        TextInput::make('comision')
                            ->label('Comisión(%)')
                            ->numeric(),
                        TextInput::make('montoCreditoAprobado')
                            ->label('Monto Credito Aprobado')
                            ->numeric(),
                        Select::make('nivel')
                            ->label('Nivel')
                            ->options([
                                '1'=> '1',
                                '2'=> '2',
                                '3'=> '3',
                                '4'=> '4',
                                '5'=> '5',
                            ])
                            ->required()
                            ->validationMessages([
                                'required'  => 'Campo Requerido',
                            ]),
                        TextInput::make('agenteSuperiorNivel3')
                            ->label('Agente Superior Nivel 3'),
                        TextInput::make('agenciaSuperiorNivel2')
                            ->label('Agencia Superior Nivel 2'),
                        TextInput::make('agenciaPpalNivel1')
                            ->label('Agencia Principal Nivel 1')
                            ->default('TDEV')
                            ->disabled()
                            ->dehydrated()
                    ])
                    ->columnSpanFull()->columns(4),
            ]);
    }
}
