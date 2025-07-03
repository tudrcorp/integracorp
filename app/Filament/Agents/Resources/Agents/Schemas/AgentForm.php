<?php

namespace App\Filament\Agents\Resources\Agents\Schemas;

use App\Models\City;
use App\Models\State;
use App\Models\Region;
use App\Models\Country;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Facades\Blade;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Wizard;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class AgentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make('INFORMACION PRINCIPAL')
                        ->description('Información principal del solicitante')
                        ->icon(Heroicon::User)
                        ->completedIcon(Heroicon::Check)
                        ->schema([
                            TextInput::make('name')
                                ->label('Nombre/Razon Social')
                                ->afterStateUpdated(function (Set $set, $state) {
                                    $set('name', strtoupper($state));
                                })
                                ->live(onBlur: true)
                                ->prefixIcon('heroicon-s-identification')
                                ->required()
                                ->validationMessages([
                                    'required' => 'Campo requerido',
                                ])
                                ->maxLength(255),
                            TextInput::make('rif')
                                ->label('RIF: (si posee!)')
                                ->prefix('J-')
                                ->numeric()
                                ->validationMessages([
                                    'numeric'   => 'El campo es numerico',
                                ]),
                            TextInput::make('ci')
                                ->label('Cedula de Identidad')
                                ->prefix('V/E/C')
                                ->numeric()
                                ->required()
                                ->validationMessages([
                                    'numeric'   => 'El campo es numerico',
                                ]),

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

                            DatePicker::make('birth_date')
                                ->label('Fecha de Nacimiento')
                                ->prefixIcon('heroicon-m-calendar-days')
                                ->displayFormat('d/m/Y')
                                ->required()
                                ->validationMessages([
                                    'required'  => 'Campo Requerido',
                                ]),

                            DatePicker::make('company_init_date')
                                ->label('Fecha de Ingreso')
                                ->prefixIcon('heroicon-m-calendar-days')
                                ->displayFormat('d/m/Y'),

                            TextInput::make('email')
                                ->label('Email Corporativo')
                                ->prefixIcon('heroicon-s-at-symbol')
                                ->email()
                                ->required()
                                ->validationMessages([
                                    'required'  => 'Campo requerido',
                                    'email'     => 'El campo es un email',
                                ])
                                ->maxLength(255),
                            TextInput::make('address')
                                ->label('Dirección')
                                ->afterStateUpdated(function (Set $set, $state) {
                                    $set('address', strtoupper($state));
                                })
                                ->live(onBlur: true)
                                ->prefixIcon('heroicon-s-identification')
                                ->required()
                                ->validationMessages([
                                    'required'  => 'Campo Requerido',
                                ])
                                ->maxLength(255),
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
                            TextInput::make('user_instagram')
                                ->label('Usuario de Instagram')
                                ->prefixIcon('heroicon-s-user')
                                ->maxLength(255),
                        ])->columns(4),
                    Step::make('MONEDA NACIONAL')
                        ->description('Datos bancarios para recibir pagos en moneda nacional')
                        ->icon(Heroicon::CreditCard) //heroicon-s-credit-card
                    ->completedIcon(Heroicon::Check)
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
                            TextInput::make('local_beneficiary_phone_pm')
                                ->label('Teléfono Pago Movil del Beneficiario')
                                ->prefixIcon('heroicon-s-phone')
                                ->tel()
                                ->live(onBlur: true)
                                ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                    $countryCode = '+58';

                                    if ($countryCode) {
                                        $cleanNumber = ltrim(preg_replace('/[^0-9]/', '', $state), '0');
                                        $set('local_beneficiary_phone_pm', $countryCode . $cleanNumber);
                                    }
                                }),
                        ])->columnSpanFull()->columns(3),
                    Step::make('MONEDA EXTRANJERA')
                        ->description('Datos bancarios para recibir pagos en moneda extranjera')
                        ->icon(Heroicon::CurrencyDollar)
                        ->completedIcon(Heroicon::Check) //heroicon-s-currency-dollar
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
                                ->prefixIcon('heroicon-s-identification')
                                ->numeric()
                                ->validationMessages([
                                    'numeric'  => 'Campo tipo numeric',
                                ])
                                ->live()
                                ->maxLength(255),
                            Select::make('extra_beneficiary_account_bank')
                                ->label('Banco')
                                ->prefixIcon('heroicon-s-identification')
                                ->searchable()
                                ->preload()
                                ->options([
                                    'JPMORGAN CHASE & CO'                                   => 'JPMORGAN CHASE & CO',
                                    'BANK OF AMERICA'                                       => 'BANK OF AMERICA',
                                    'WELLS FARGO'                                           => 'WELLS FARGO',
                                    'CITIBANK (CITIGROUP)'                                  => 'CITIBANK (CITIGROUP)',
                                    'U.S. BANK'                                             => 'U.S. BANK',
                                    'PNC FINANCIAL SERVICES'                                => 'PNC FINANCIAL SERVICES',
                                    'TRUIST FINANCIAL CORPORATION'                          => 'TRUIST FINANCIAL CORPORATION',
                                    'CAPITAL ONE'                                           => 'CAPITAL ONE',
                                    'TD BANK (TORONTO-DOMINION BANK)'                       => 'TD BANK (TORONTO-DOMINION BANK)',
                                    'HSBC BANK USA'                                         => 'HSBC BANK USA',
                                    'FIFTH THIRD BANK'                                      => 'FIFTH THIRD BANK',
                                    'REGIONS FINANCIAL CORPORATION'                         => 'REGIONS FINANCIAL CORPORATION',
                                    'HUNTINGTON NATIONAL BANK'                              => 'HUNTINGTON NATIONAL BANK',
                                    'NAVY FEDERAL CREDIT UNION'                             => 'NAVY FEDERAL CREDIT UNION',
                                    'STATE EMPLOYEES CREDIT UNION (SECU)'                   => 'STATE EMPLOYEES CREDIT UNION (SECU)',
                                    'BANCO NACIONAL DE PANAMÁ (BNP)'                        => 'BANCO NACIONAL DE PANAMÁ (BNP)',
                                    'CAJA DE AHORROS'                                       => 'CAJA DE AHORROS',
                                    'BANCO GENERAL'                                         => 'BANCO GENERAL',
                                    'GLOBAL BANK'                                           => 'GLOBAL BANK',
                                    'BANESCO PANAMÁ'                                        => 'BANESCO PANAMÁ',
                                    'METROBANK'                                             => 'METROBANK',
                                    'BANCO LATINOAMERICANO DE COMERCIO EXTERIOR (BLADEX)'   => 'BANCO LATINOAMERICANO DE COMERCIO EXTERIOR (BLADEX)',
                                    'HSBC BANK PANAMÁ'                                      => 'HSBC BANK PANAMÁ',
                                    'SCOTIABANK PANAMÁ'                                     => 'SCOTIABANK PANAMÁ',
                                    'CITIBANK PANAMÁ'                                       => 'CITIBANK PANAMÁ',
                                    'BANCO SANTANDER PANAMÁ'                                => 'BANCO SANTANDER PANAMÁ',
                                    'BANCO DAVIVIENDA PANAMÁ'                               => 'BANCO DAVIVIENDA PANAMÁ',
                                    'BANCO ALIADO'                                          => 'BANCO ALIADO',
                                    'MULTIBANK'                                             => 'MULTIBANK',
                                    'BANCAMIGA'                                             => 'BANCAMIGA',
                                    'BANCO DEL TESORO'                                      => 'BANCO DEL TESORO',
                                    'PROVINCIAL'                                            => 'PROVINCIAL',
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
                                ->label('Tipo de cuenta')
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
                        ])->columnSpanFull()->columns(3),
                ])
                ->submitAction(new HtmlString(Blade::render(<<<BLADE
                    <x-filament::button
                        type="submit"
                        size="sm"
                    >
                        Actualizar información
                    </x-filament::button>
                BLADE)))
                ->columnSpanFull(),
            ]);
    }
}