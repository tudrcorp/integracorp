<?php

namespace App\Filament\Master\Resources\Agencies\Schemas;

use App\Models\City;
use App\Models\State;
use App\Models\Agency;
use App\Models\Region;
use App\Models\Country;
use App\Models\AgencyType;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Facades\Blade;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Wizard;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use App\Http\Controllers\UtilsController;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class AgencyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                /**
                 * Formulario Wizar
                 * ------------------------------------------------------
                 */
                Wizard::make([
                    Step::make('Información Principal')
                        ->schema([
                            TextInput::make('code')
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
                            Select::make('agency_type_id')
                                ->label('Tipo de agencia')
                                ->options(AgencyType::where('id', 3)->get()->pluck('definition', 'id'))
                                ->searchable()
                                ->preload(),

                            /**JERARQUÍA */
                            /*---------------------------------------------------------- */
                            Hidden::make('owner_code')->default(function (Get $get) {
                                return Auth::user()->code_agency;
                            }),

                            TextInput::make('name_corporative')
                                ->label('Nombre de la Agencia')
                                ->prefixIcon('heroicon-s-identification')
                                ->required()
                                ->validationMessages([
                                    'required' => 'Campo requerido',
                                ])
                                ->maxLength(255)
                                ->afterStateUpdatedJs(<<<'JS'
                                    $set('name_corporative', $state.toUpperCase());
                                JS),
                            TextInput::make('rif')
                                ->label('Rif')
                                ->prefix('J-')
                                ->numeric(),
                            TextInput::make('email')
                                ->label('Correo Electrónico')
                                ->prefixIcon('heroicon-s-at-symbol')
                                ->email()
                                ->required()
                                ->unique(
                                    ignoreRecord: true,
                                    table: 'agencies',
                                    column: 'email',
                                )
                                ->validationMessages([
                                    'unique'    => 'El Correo Electrónico ya se encuentra registrado.',
                                    'required'  => 'Campo requerido',
                                    'email'     => 'El campo es un email',
                                ])
                                ->maxLength(255),
                            TextInput::make('name_representative')
                                ->label('Nombre del Representante')
                                ->prefixIcon('heroicon-s-identification')
                                ->required()
                                ->validationMessages([
                                    'required'  => 'Campo Requerido',
                                ])
                                ->maxLength(255)
                                ->afterStateUpdatedJs(<<<'JS'
                                    $set('name_representative', $state.toUpperCase());
                                JS),
                    

                            TextInput::make('ci_responsable')
                                ->label('Cédula del Representante')
                                ->prefix('V-')
                                ->numeric()
                                ->unique(
                                    ignoreRecord: true,
                                    table: 'agencies',
                                    column: 'ci_responsable',
                                )
                                ->required()
                                ->validationMessages([
                                    'unique'    => 'La cedula del responsable ya se encuentra registrado.',
                                    'required'  => 'Campo requerido',
                                    'numeric'   => 'El campo es numerico',
                                ])
                                ->required(),
                            DatePicker::make('brithday_date')
                                ->label('Fecha de Nacimiento')
                                ->format('d/m/Y')
                                ->required()
                                ->validationMessages([
                                    'required'  => 'Campo Requerido',
                                ]),
                            TextInput::make('address')
                                ->label('Dirección')
                                ->afterStateUpdated(function (Set $set, $state) {
                                    $set('address', strtoupper($state));
                                })
                                ->prefixIcon('heroicon-s-identification')
                                ->required()
                                ->validationMessages([
                                    'required'  => 'Campo Requerido',
                                ])
                                ->maxLength(255),
                            Select::make('country_code')
                                ->label('Código de país')
                                ->options(UtilsController::getCountries())
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
                                    $countryCode = $get('country_code_ti');
                                    if ($countryCode) {
                                        $cleanNumber = ltrim(preg_replace('/[^0-9]/', '', $state), '0');
                                        $set('phone_ti', $countryCode . $cleanNumber);
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
                                ->default(187)
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
                            TextInput::make('user_tdev')
                                ->label('Usuario de Tu Doctor en Viajes (TDEV)')
                                ->prefixIcon('heroicon-s-identification')
                                ->maxLength(255),
                            TextInput::make('user_instagram')
                                ->label('Usuario de Instagram')
                                ->prefixIcon('heroicon-s-user')
                                ->maxLength(255),
                            TextInput::make('status')
                                ->label('Estatus')
                                ->prefixIcon('heroicon-m-shield-check')
                                ->disabled()
                                ->dehydrated()
                                ->maxLength(255)
                                ->default('ACTIVO')
                                ->hiddenOn('edit'),
                            TextInput::make('created_by')
                                ->label('Creado Por:')
                                ->prefixIcon('heroicon-s-user-circle')
                                ->disabled()
                                ->dehydrated()
                                ->default('sistema')
                                ->maxLength(255)
                                ->hiddenOn('edit'),
                        ]),
                    Step::make('Contacto Secuendario')
                        ->schema([
                            TextInput::make('name_contact_2')
                                ->label('Nombre y Apellido')
                                ->afterStateUpdated(function (Set $set, $state) {
                                    $set('name_contact_2', strtoupper($state));
                                })
                                ->live(onBlur: true)
                                ->prefixIcon('heroicon-s-identification')
                                ->validationMessages([
                                    'required'  => 'Campo Requerido',
                                ])
                                ->maxLength(255),

                            Select::make('country_code_2')
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
                                ->live()
                                ->searchable()
                                ->default('+58')
                                ->validationMessages([
                                    'require'  => 'Campo Requerido',
                                ]),
                            TextInput::make('phone_contact_2')
                                ->prefixIcon('heroicon-s-phone')
                                ->tel()
                                ->label('Número de teléfono')
                                ->live(onBlur: true)
                                ->validationMessages([
                                    'required'  => 'Campo Requerido',
                                    'numeric'   => 'El campo es numerico',
                                ])
                                ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                    $countryCode = $get('country_code_2');
                                    if ($countryCode) {
                                        $cleanNumber = ltrim(preg_replace('/[^0-9]/', '', $state), '0');
                                        $set('phone_contact_2', $countryCode . $cleanNumber);
                                    }
                                }),
                            TextInput::make('email_contact_2')
                                ->label('Email')
                                ->prefixIcon('heroicon-s-at-symbol')
                                ->email()
                                ->validationMessages([
                                    'email'  => 'Campo formato email',
                                ])
                                ->maxLength(255),
                        ]),
                    Step::make('Comisiones')
                        ->schema([
                            Grid::make(2)
                                ->schema([
                                    Toggle::make('tdec')
                                        ->label('TDEC'),
                                    Toggle::make('tdev')
                                        ->label('TDEV'),

                                ])->columnSpanFull(),
                            Grid::make(2)
                                ->schema([
                                    TextInput::make('commission_tdec')
                                        ->label('Comisión TDEC US$')
                                        ->helperText('Valor expresado en porcentaje. Utilice separador decimal(.)')
                                        ->prefix('%')
                                        ->numeric()
                                        ->rules(['required', 'max:22'])
                                        ->validationMessages([
                                            'numeric'   => 'Campo tipo numerico.',
                                            'required'  => 'Campo Requerido',
                                            'max'       => 'El campo no debe ser mayor a 22%',
                                            
                                        ]),
                                    TextInput::make('commission_tdec_renewal')
                                        ->label('Comisión Renovacion TDEC US$')
                                        ->helperText('Valor expresado en porcentaje. Utilice separador decimal(.)')
                                        ->prefix('%')
                                        ->numeric()
                                        ->rules(['required', 'max:22'])
                                        ->validationMessages([
                                            'numeric'   => 'Campo tipo numerico.',
                                            'required'  => 'Campo Requerido',
                                            'max'       => 'El campo no debe ser mayor a 22%',

                                        ]),

                                ])->columnSpanFull(),
                            Grid::make(2)
                                ->schema([
                                    TextInput::make('commission_tdev')
                                        ->label('Comisión TDEV US$')
                                        ->helperText('Valor expresado en porcentaje. Utilice separador decimal(.)')
                                        ->prefix('%')
                                        ->numeric()
                                        ->rules(['required', 'max:40'])
                                        ->validationMessages([
                                            'numeric'   => 'Campo tipo numerico.',
                                            'required'  => 'Campo Requerido',
                                            'max'       => 'El campo no debe ser mayor a 40%',

                                        ]),
                                    TextInput::make('commission_tdev_renewal')
                                        ->label('Comisión Renovacion TDEV US$')
                                        ->helperText('Valor expresado en porcentaje. Utilice separador decimal(.)')
                                        ->prefix('%')
                                        ->numeric()
                                        ->rules(['required', 'max:40'])
                                        ->validationMessages([
                                            'numeric'   => 'Campo tipo numerico.',
                                            'required'  => 'Campo Requerido',
                                            'max'       => 'El campo no debe ser mayor a 40%',

                                        ]),
                                ])->columnSpanFull()
                        ]),
                    Step::make('Datos Bancarios(VES)')
                        ->schema([
                            TextInput::make('local_beneficiary_name')
                                ->label('Nombre/Razon Social del Beneficiario')
                                ->afterStateUpdated(function (Set $set, $state) {
                                    $set('local_beneficiary_name', strtoupper($state));
                                })
                                ->live(onBlur: true)
                                ->prefixIcon('heroicon-s-identification')
                                ->maxLength(255),
                            TextInput::make('local_beneficiary_rif')
                                ->label('CI/RIF del Beneficiario')
                                ->prefixIcon('heroicon-s-identification')
                                ->numeric()
                                ->validationMessages([
                                    'numeric'  => 'Campo tipo numerico',
                                ])
                                ->maxLength(255),
                            TextInput::make('local_beneficiary_account_number')
                                ->label('Número de Cuenta del Beneficiario')
                                ->prefixIcon('heroicon-s-identification')
                                ->numeric()
                                ->validationMessages([
                                    'numeric'  => 'Campo tipo numerico',
                                ])
                                ->maxLength(255),
                            Select::make('local_beneficiary_account_bank')
                                ->label('Banco del Beneficiario')
                                ->prefixIcon('heroicon-s-identification')
                                ->options([
                                    'BANCO DE VENEZUELA'            => 'BANCO DE VENEZUELA',
                                    'BANCO BICENTENARIO'            => 'BANCO BICENTENARIO',
                                    'BANCO MERCANTIL'               => 'BANCO MERCANTIL',
                                    'BANCO PROVINCIAL'              => 'BANCO PROVINCIAL',
                                    'BANCO CARONI'                  => 'BANCO CARONI',
                                    'BANCO DEL CARIBE'              => 'BANCO DEL CARIBE',
                                    'BANCO DEL TESORO'              => 'BANCO DEL TESORO',
                                    'BANCO NACIONAL DE CREDITO'     => 'BANCO NACIONAL DE CREDITO',
                                    'BANESCO'                       => 'BANESCO',
                                    'BANCO CARONI'                  => 'BANCO CARONI',
                                    'FONDO COMUN'                   => 'FONDO COMUN',
                                    'BANCO CANARIAS'                => 'BANCO CANARIAS',
                                    'BANCO DEL SUR'                 => 'BANCO DEL SUR',
                                    'BANCO AGRICOLA DE VENEZUELA'   => 'BANCO AGRICOLA DE VENEZUELA',
                                    'BANPLUS'                       => 'BANPLUS',
                                    'MI BANCO'                      => 'MI BANCO',
                                    'BANCAMIGA'                     => 'BANCAMIGA',
                                    'BANFANB'                       => 'BANFANB',
                                    'BANCARIBE'                     => 'BANCARIBE',
                                    'BANCO ACTIVO'                  => 'BANCO ACTIVO',
                                ]),
                            Select::make('local_beneficiary_account_type')
                                ->label('Tipo de Cuenta del Beneficiario')
                                ->prefixIcon('heroicon-s-identification')
                                ->options([
                                    'AHORRO'      => 'AHORRO',
                                    'CORRIENTE'   => 'CORRIENTE',
                                ]),
                            Select::make('country_code_beneficiary')
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
                                ->required()
                                ->validationMessages([
                                    'required'  => 'Campo Requerido',
                                ])
                                ->hiddenOn('edit'),
                            TextInput::make('local_beneficiary_phone_pm')
                                ->label('Teléfono Pago Movil del Beneficiario')
                                ->prefixIcon('heroicon-s-phone')
                                ->tel()
                                ->validationMessages([
                                    'numeric'  => 'Campo tipo numeric',
                                ])
                                ->live(onBlur: true)
                                ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                    $countryCode = '+58';

                                    if ($countryCode) {
                                        $cleanNumber = ltrim(preg_replace('/[^0-9]/', '', $state), '0');
                                        $set('local_beneficiary_phone_pm', $countryCode . $cleanNumber);
                                    }
                                }),
                        ]),
                    Step::make('Datos Bancarios(US$)')
                        ->schema([
                            TextInput::make('extra_beneficiary_name')
                                ->label('Nombre/Razon Social')
                                ->afterStateUpdated(function (Set $set, $state) {
                                    $set('extra_beneficiary_name', strtoupper($state));
                                })
                                ->live(onBlur: true)
                                ->prefixIcon('heroicon-s-identification')
                                ->maxLength(255),
                            TextInput::make('extra_beneficiary_ci_rif')
                                ->label('Nro. CI/RIF/ID/PASAPORTE')
                                ->prefixIcon('heroicon-s-identification')
                                ->numeric()
                                ->validationMessages([
                                    'numeric'  => 'Campo tipo numeric',
                                ])
                                ->maxLength(255),
                            TextInput::make('extra_beneficiary_account_number')
                                ->label('Número de cuenta')
                                ->numeric()
                                ->validationMessages([
                                    'numeric'  => 'Campo tipo numerico',
                                ])
                                ->prefixIcon('heroicon-s-identification')
                                ->maxLength(255),
                            Select::make('extra_beneficiary_account_bank')
                                ->label('Banco')
                                ->prefixIcon('heroicon-s-identification')
                                ->searchable()
                                ->preload()
                                ->options([
                                    'JPMORGAN CHASE & CO'                               => 'JPMORGAN CHASE & CO',
                                    'BANK OF AMERICA'                                   => 'BANK OF AMERICA',
                                    'WELLS FARGO'                                       => 'WELLS FARGO',
                                    'CITIBANK (CITIGROUP)'                              => 'CITIBANK (CITIGROUP)',
                                    'U.S. BANK'                                         => 'U.S. BANK',
                                    'PNC FINANCIAL SERVICES'                            => 'PNC FINANCIAL SERVICES',
                                    'TRUIST FINANCIAL CORPORATION'                      => 'TRUIST FINANCIAL CORPORATION',
                                    'CAPITAL ONE'                                       => 'CAPITAL ONE',
                                    'TD BANK (TORONTO-DOMINION BANK)'                   => 'TD BANK (TORONTO-DOMINION BANK)',
                                    'HSBC BANK USA'                                     => 'HSBC BANK USA',
                                    'FIFTH THIRD BANK'                                  => 'FIFTH THIRD BANK',
                                    'REGIONS FINANCIAL CORPORATION'                     => 'REGIONS FINANCIAL CORPORATION',
                                    'HUNTINGTON NATIONAL BANK'                          => 'HUNTINGTON NATIONAL BANK',
                                    'NAVY FEDERAL CREDIT UNION'                         => 'NAVY FEDERAL CREDIT UNION',
                                    'STATE EMPLOYEES CREDIT UNION (SECU)'               => 'STATE EMPLOYEES CREDIT UNION (SECU)',
                                    'BANCO NACIONAL DE PANAMÁ (BNP)'                    => 'BANCO NACIONAL DE PANAMÁ (BNP)',
                                    'CAJA DE AHORROS'                                   => 'CAJA DE AHORROS',
                                    'BANCO GENERAL'                                     => 'BANCO GENERAL',
                                    'GLOBAL BANK'                                       => 'GLOBAL BANK',
                                    'BANESCO PANAMÁ'                                    => 'BANESCO PANAMÁ',
                                    'METROBANK'                                         => 'METROBANK',
                                    'BANCO LATINOAMERICANO DE COMERCIO EXTERIOR (BLADEX)' => 'BANCO LATINOAMERICANO DE COMERCIO EXTERIOR (BLADEX)',
                                    'HSBC BANK PANAMÁ'                                  => 'HSBC BANK PANAMÁ',
                                    'SCOTIABANK PANAMÁ'                                 => 'SCOTIABANK PANAMÁ',
                                    'CITIBANK PANAMÁ'                                   => 'CITIBANK PANAMÁ',
                                    'BANCO SANTANDER PANAMÁ'                            => 'BANCO SANTANDER PANAMÁ',
                                    'BANCO DAVIVIENDA PANAMÁ'                           => 'BANCO DAVIVIENDA PANAMÁ',
                                    'BANCO ALIADO'                                      => 'BANCO ALIADO',
                                    'MULTIBANK'                                         => 'MULTIBANK',
                                    'BANCAMIGA'                                         => 'BANCAMIGA',
                                    'BANCO DEL TESORO'                                  => 'BANCO DEL TESORO',
                                    'PROVINCIAL'                                        => 'PROVINCIAL',
                                ]),
                            TextInput::make('extra_beneficiary_address')
                                ->label('Direccion')
                                ->afterStateUpdated(function (Set $set, $state) {
                                    $set('extra_beneficiary_address', strtoupper($state));
                                })
                                ->live(onBlur: true)
                                ->prefixIcon('heroicon-s-identification')
                                ->maxLength(255),
                            Select::make('extra_beneficiary_account_type')
                                ->label('Banco del Beneficiario')
                                ->prefixIcon('heroicon-s-identification')
                                ->searchable()
                                ->preload()
                                ->options([
                                    'CUENTA DE CHEQUES (CHECKING ACCOUNT)'                              => 'CUENTA DE CHEQUES (CHECKING ACCOUNT)',
                                    'CUENTA DE AHORROS (SAVINGS ACCOUNT)'                               => 'CUENTA DE AHORROS (SAVINGS ACCOUNT)',
                                    'CUENTA CORRIENTE (CURRENT ACCOUNT)'                                => 'CUENTA CORRIENTE (CURRENT ACCOUNT)',
                                    'CUENTA DE DEPÓSITO A PLAZO FIJO (CERTIFICATE OF DEPOSIT - CD)'     => 'CUENTA DE DEPÓSITO A PLAZO FIJO (CERTIFICATE OF DEPOSIT - CD)',
                                    'CUENTA DE NEGOCIOS (BUSINESS ACCOUNT)'                             => 'CUENTA DE NEGOCIOS (BUSINESS ACCOUNT)',
                                    'CUENTA DE INVERSIÓN (INVESTMENT ACCOUNT)'                          => 'CUENTA DE INVERSIÓN (INVESTMENT ACCOUNT)',
                                    'CUENTA DE RETIRO INDIVIDUAL (INDIVIDUAL RETIREMENT ACCOUNT - IRA)' => 'CUENTA DE RETIRO INDIVIDUAL (INDIVIDUAL RETIREMENT ACCOUNT - IRA)',
                                    'CUENTA DE FONDOS DE EMERGENCIA (EMERGENCY FUND ACCOUNT)'           => 'CUENTA DE FONDOS DE EMERGENCIA (EMERGENCY FUND ACCOUNT)',
                                    'CUENTA PARA MENORES (MINOR ACCOUNT / CUSTODIAL ACCOUNT)'           => 'CUENTA PARA MENORES (MINOR ACCOUNT / CUSTODIAL ACCOUNT)',
                                    'CUENTA CONJUNTA (JOINT ACCOUNT)'                                   => 'CUENTA CONJUNTA (JOINT ACCOUNT)',
                                    'CUENTA EN MONEDA EXTRANJERA (CUENTA EN DÓLARES, EUROS, ETC.)'      => 'CUENTA EN MONEDA EXTRANJERA (CUENTA EN DÓLARES, EUROS, ETC.)',
                                    'CUENTA DE RETIRO (CUENTA DE JUBILACIÓN)'                           => 'CUENTA DE RETIRO (CUENTA DE JUBILACIÓN)',
                                    'CUENTA DE FIDEICOMISO (TRUST ACCOUNT)'                             => 'CUENTA DE FIDEICOMISO (TRUST ACCOUNT)',
                                ]),
                            TextInput::make('extra_beneficiary_route')
                                ->label('Ruta')
                                ->afterStateUpdated(function (Set $set, $state) {
                                    $set('extra_beneficiary_route', strtoupper($state));
                                })
                                ->live(onBlur: true)
                                ->prefixIcon('heroicon-s-identification')
                                ->maxLength(255),
                            TextInput::make('extra_beneficiary_swift')
                                ->label('Swift')
                                ->afterStateUpdated(function (Set $set, $state) {
                                    $set('extra_beneficiary_swift', strtoupper($state));
                                })
                                ->live(onBlur: true)
                                ->prefixIcon('heroicon-s-identification')
                                ->maxLength(255),
                            TextInput::make('extra_beneficiary_zelle')
                                ->label('Zelle')
                                ->afterStateUpdated(function (Set $set, $state) {
                                    $set('extra_beneficiary_zelle', strtoupper($state));
                                })
                                ->live(onBlur: true)
                                ->prefixIcon('heroicon-s-identification')
                                ->maxLength(255),
                        ]),
                    Step::make('Acuerdo y condiciones')
                        ->schema([
                            Section::make('Lea detenidamente las siguientes condiciones!')
                                ->description(function (Get $get) {
                                    return 'Este acuerdo establece la relación entre la Agencia o Agente y Tu Dr. Group ( quien en adelante se denominará la Compañía ) los cuales 
                                            se sujetarán a los siguientes términos: La Compañía le ha asignado a la Agencia o Agente un número de identificación ( arriba mencionado ), 
                                            bajo el cual se registrará el negocio proveniente de dicha Agencia o Agente. 
                                            Este Acuerdo será efectivo a partir de la fecha de suscripción en la Compañía. Cualquiera de las partes puede terminar este Acuerdo con un 
                                            preaviso mínimo de sesenta (60) días, siempre y cuando notique su intención por escrito. 
                                            La Compañía se reserva el derecho a terminar este Acuerdo por motivo de actos fraudulentos o el incumplimiento de cualquiera de las normas 
                                            contenidas en el mismo. 
                                            La Agencia tiene la potestad de de nir la comisión por venta que reciben los agentes bajo su estructura. 
                                            Forma parte integrante e indivisible de este acuerdo: el Addendum contentivo de las normas particulares que rigen la relación, los documentos 
                                            complementarios de identicación de la Agencia o el Agente, los datos ociales de identicación de las cuentas bancarias dispuestas para el 
                                            pago de comisiones y cualquier otro documento que se adjunte en el trascurso de la relación comercial.';
                                })
                                ->icon('heroicon-m-folder-plus')
                                ->schema([
                                    Checkbox::make('is_accepted_conditions')
                                        ->live()
                                        ->label('ACEPTO')
                                        ->required(),
                                ])->columnSpanFull(),
                        ]),
                ])
                ->submitAction(new HtmlString(Blade::render(<<<BLADE
                    <x-filament::button type="submit" size="sm">
                        Actualizar Información   
                    </x-filament::button>
                BLADE)))
                ->columnSpanFull()->columns(4),
            ]);
    }
}