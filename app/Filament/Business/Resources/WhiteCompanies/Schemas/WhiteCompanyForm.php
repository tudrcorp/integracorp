<?php

namespace App\Filament\Business\Resources\WhiteCompanies\Schemas;

use App\Models\City;
use App\Models\User;
use App\Models\State;
use App\Models\Agency;
use App\Models\Region;
use App\Models\Country;
use App\Models\WhiteCompany;
use Filament\Schemas\Schema;
use App\Models\Configuration;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class WhiteCompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                
                Section::make('Información General')
                    ->icon('heroicon-o-document-text')
                    ->description('Información General para la entidad.')
                    ->schema([
                        Grid::make()
                        ->schema([
                            FileUpload::make('logo')
                                ->directory('logo-empresa')
                                ->visibility('public'),
                        ])->columnSpanFull()->columns(4),
                        TextInput::make('name'),
                        TextInput::make('rif')
                            ->Label('RIF')
                            ->numeric(), 
                        TextInput::make('email')
                            ->label('Email address')
                            ->email()
                            ->live()
                            ->required()
                            ->unique(
                                table: WhiteCompany::class,
                                column: 'email',
                            )
                            ->validationMessages([
                                'unique'  => 'El correo electrónico ya está en uso.',
                            ])->hiddenOn('edit'),
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
                            ])->hiddenOn('edit'),

                        TextInput::make('phone')
                            ->prefixIcon('heroicon-s-phone')
                            ->tel()
                            ->telRegex('/^[+]*[(]{0,1}[0-9]{1,4}[)]{0,1}[-\s\.\/0-9]*$/')
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
                        TextInput::make('address'),
                    ])->columnSpanFull()->columns(4),

                Section::make('COMISIONES')
                    ->hiddenOn('edit')
                    ->collapsed()
                    ->description('Fomulario. Campo Requerido(*)')
                    ->icon('heroicon-m-chart-pie')
                    ->schema([
                        TextInput::make('commission_tdec')
                            ->label('Comisión TDEC US$')
                            ->helperText('Valor expresado en porcentaje. Utilice separador decimal(.)')
                            ->prefix('%')
                            ->numeric()
                            ->default(null)
                            ->validationMessages([
                                'numeric'   => 'Campo tipo numerico.',
                            ]),
                        TextInput::make('commission_tdec_renewal')
                            ->label('Comisión Renovacion TDEC US$')
                            ->helperText('Valor expresado en porcentaje. Utilice separador decimal(.)')
                            ->prefix('%')
                            ->numeric()
                            ->default(null)
                            ->validationMessages([
                                'numeric'   => 'Campo tipo numerico.',
                            ]),
                        TextInput::make('commission_tdev')
                            ->label('Comisión TDEV US$')
                            ->helperText('Valor expresado en porcentaje. Utilice separador decimal(.)')
                            ->prefix('%')
                            ->numeric()
                            ->default(null)
                            ->validationMessages([
                                'numeric'   => 'Campo tipo numerico.',
                            ]),
                        TextInput::make('commission_tdev_renewal')
                            ->label('Comisión Renovacion TDEV US$')
                            ->helperText('Valor expresado en porcentaje. Utilice separador decimal(.)')
                            ->prefix('%')
                            ->numeric()
                            ->default(null)
                            ->validationMessages([
                                'numeric'   => 'Campo tipo numerico.',
                            ]),
                    ])->columnSpanFull()->columns(2),
                    
                Section::make('Registro de Configuración')
                    ->hiddenOn('edit')
                    ->collapsed()
                    ->icon('heroicon-o-document-text')
                    ->description('Información General para preconfigurar la entidad.')
                    ->schema([
                        TextInput::make('name'),
                        TextInput::make('rif')
                            ->Label('RIF')
                            ->numeric(),
                        TextInput::make('email')
                            ->label('Correo Electrónico')
                            ->email()
                            ->required()
                            ->unique(
                                table: Configuration::class,
                                column: 'email',
                            )
                            ->validationMessages([
                                'unique'  => 'El correo electrónico ya está en uso.',
                            ])->hiddenOn('edit'),
                    ])->columnSpanFull()->columns(3),
                    
                Section::make('Registro de Usuario Administrador')
                    ->hiddenOn('edit')
                    ->icon('heroicon-o-document-text')
                    ->description('Información General del usuario administrador de la entidad.')
                    ->schema([
                        TextInput::make('code_agency')
                            ->label('Código')
                            ->prefixIcon('heroicon-m-clipboard-document-check')
                            ->default(function () {
                                if (Agency::max('id') == null) {
                                    $parte_entera = 100;
                                } else {
                                    $parte_entera = 100 + Agency::max('id');
                                }
                                return 'TDG-' . $parte_entera + 1;
                            })
                            ->required()
                            ->disabled()
                            ->dehydrated()
                            ->maxLength(255),
                        TextInput::make('email_administrador')
                            ->label('Correo Electrónico')
                            ->required()
                            ->email(),
                        TextInput::make('password')
                            ->label('Contraseña')
                            ->password(),
                        Select::make('agency_type')
                            ->label('Roles dentro de la Estructura')
                            ->options([
                                'MASTER'    => 'MASTER',
                                'GENERAL'   => 'GENERAL',
                            ]),
                        Hidden::make('is_whiteCompanyAdmin')->default(true),
                    ])->columnSpanFull()->columns(4),

                Hidden::make('created_by')->default(fn () => Auth::user()->name),
                Hidden::make('updated_by'),
            ]);
    }
}