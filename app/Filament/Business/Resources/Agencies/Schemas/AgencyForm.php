<?php

namespace App\Filament\Business\Resources\Agencies\Schemas;

use App\Models\City;
use App\Models\User;
use App\Models\State;
use App\Models\Agency;
use App\Models\Region;
use App\Models\Country;
use App\Models\AgencyType;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use App\Http\Controllers\UtilsController;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class AgencyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('AGENCIAS')
                    ->collapsible()
                    ->description('Fomulario para el registro de agencias. Campo Requerido(*)')
                    ->icon('heroicon-s-building-library')
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
                            ->options(AgencyType::all()->pluck('definition', 'id'))
                            ->searchable()
                            ->live()
                            ->required()
                            ->validationMessages([
                                'required' => 'Campo requerido',
                            ])
                            ->preload(),
                        Select::make('select_owner_code')
                            ->label('Jerarquia')
                            ->options(function (Get $get) {
                                return Agency::select('code', 'agency_type_id')
                                    ->where('agency_type_id', 1)
                                    ->get()
                                    ->mapWithKeys(function ($agency) {
                                        $type = AgencyType::find($agency->agency_type_id)->definition;
                                        return [$agency->code => "{$type} - {$agency->code}"];
                                    });
                            })
                            ->hidden(fn(Get $get) => $get('agency_type_id') == 1 || $get('agency_type_id') == null)
                            ->helperText('Esta lista despliega solo las agencias master. Si Usted decide dejar este campo vacio, el sistema tomara TDG-100 como agencia master.')
                            ->afterStateUpdated(function (Set $set, $state) {
                                if ($state == null) {
                                    return $set('owner_code', 'TDG-100');
                                }
                                return $set('owner_code', $state);
                            })
                            ->searchable()
                            ->live()
                            ->preload(),
                        Hidden::make('owner_code')
                            ->live()
                            ->default('TDG-100'),
                        Hidden::make('created_by')->default(Auth::user()->name),
                        Select::make('ownerAccountManagers')
                            ->label('Acount Manager')
                            ->options(function (Get $get) {
                                return User::where('is_accountManagers', true)->get()->pluck('name', 'id');
                            })
                            ->searchable()
                            ->preload(),

                    ])->columnSpanFull()->columns(4),
                Section::make('INFORMACION PRINCIPAL')
                    ->description('Fomulario. Campo Requerido(*)')
                    ->collapsed()
                    ->icon('heroicon-s-building-office-2')
                    ->schema([
                        TextInput::make('name_corporative')
                            ->label('Razon Social')
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
                            ->label('Rif')
                            ->prefix('J-')
                            ->numeric()
                            ->unique(
                                ignoreRecord: true,
                                table: 'agencies',
                                column: 'rif',
                            )
                            ->required()
                            ->validationMessages([
                                'unique'    => 'El RIF ya se encuentra registrado.',
                                'required'  => 'Campo requerido',
                                'numeric'   => 'El campo es numerico',
                            ])
                            ->required(),
                        TextInput::make('email')
                            ->label('Correo electrónico')
                            ->prefixIcon('heroicon-s-at-symbol')
                            ->email()
                            ->required()
                            ->unique(
                                ignoreRecord: true,
                                table: 'agencies',
                                column: 'email',
                            )
                            ->validationMessages([
                                'unique'    => 'El Correo electrónico ya se encuentra registrado.',
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
                        TextInput::make('ci_responsable')
                            ->label('Cedula del responsable')
                            ->prefix('J-')
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
                    ])->columnSpanFull()->columns(4),
                Section::make('INFORMACION DE CONTACTO SECUNDARIO')
                    ->description('Fomulario. Campo Requerido(*)')
                    ->collapsed()
                    ->icon('heroicon-s-building-office-2')
                    ->schema([
                        TextInput::make('name_contact_2')
                            ->label('Nombre/Razon Social')
                            ->afterStateUpdated(function (Set $set, $state) {
                                $set('name_contact_2', strtoupper($state));
                            })
                            ->live(onBlur: true)
                            ->prefixIcon('heroicon-s-identification')
                            ->maxLength(255),
                        TextInput::make('email_contact_2')
                            ->label('Email secundario')
                            ->prefixIcon('heroicon-s-at-symbol')
                            ->email()
                            ->validationMessages([
                                'email'  => 'Campo formato email',
                            ])
                            ->maxLength(255),
                        Select::make('country_code_2')
                            ->label('Código de país')
                            ->options(UtilsController::getCountries())
                            ->live(onBlur: true)
                            ->searchable()
                            ->preload()
                            ->default('+58'),
                        TextInput::make('phone_contact_2')
                            ->prefixIcon('heroicon-s-phone')
                            ->tel()
                            ->label('Número de teléfono')
                            ->live(onBlur: true)
                            ->validationMessages([
                                'numeric'   => 'El campo es numerico',
                            ])
                            ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                $countryCode = $get('country_code_2');
                                if ($countryCode) {
                                    $cleanNumber = ltrim(preg_replace('/[^0-9]/', '', $state), '0');
                                    $set('phone_contact_2', $countryCode . $cleanNumber);
                                }
                            }),
                    ])->columnSpanFull()->columns(4),
                Section::make('DATOS BANCARIOS MONEDA NACIONAL')
                    ->description('Fomulario. Campo Requerido(*)')
                    ->collapsed()
                    ->icon('heroicon-s-building-office-2')
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
                                'required'  => 'Campo Requerido',
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
                        Grid::make(4)->schema([
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
                                ->options(UtilsController::getCountries())
                                ->searchable()
                                ->default('+58')
                                ->live(onBlur: true)
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
                                    $countryCode = $get('country_code_beneficiary');
                                    if ($countryCode) {
                                        $cleanNumber = ltrim(preg_replace('/[^0-9]/', '', $state), '0');
                                        $set('local_beneficiary_phone_pm', $countryCode . $cleanNumber);
                                    }
                                }),
                        ])->columnSpanFull(),

                    ])->columnSpanFull()->columns(3),
                Section::make('DATOS BANCARIOS MONEDA EXTRANJERA')
                    ->description('Fomulario. Campo Requerido(*)')
                    ->collapsed()
                    ->icon('heroicon-s-building-office-2')
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


                    ])->columnSpanFull()->columns(4),
                Section::make('COMISIONES')
                    ->collapsed()
                    ->description('Fomulario. Campo Requerido(*)')
                    ->icon('heroicon-m-chart-pie')
                    ->schema([
                        Toggle::make('tdec')
                            ->label('TDEC'),
                        Toggle::make('tdev')
                            ->label('TDEV'),
                        TextInput::make('commission_tdec')
                            ->label('Comisión TDEC US$')
                            ->helperText('Valor expresado en porcentaje. Utilice separador decimal(.)')
                            ->prefix('%')
                            ->numeric()
                            ->validationMessages([
                                'numeric'   => 'Campo tipo numerico.',
                            ]),
                        TextInput::make('commission_tdec_renewal')
                            ->label('Comisión Renovacion TDEC US$')
                            ->helperText('Valor expresado en porcentaje. Utilice separador decimal(.)')
                            ->prefix('%')
                            ->numeric()
                            ->validationMessages([
                                'numeric'   => 'Campo tipo numerico.',
                            ]),
                        TextInput::make('commission_tdev')
                            ->label('Comisión TDEV US$')
                            ->helperText('Valor expresado en porcentaje. Utilice separador decimal(.)')
                            ->prefix('%')
                            ->numeric()
                            ->validationMessages([
                                'numeric'   => 'Campo tipo numerico.',
                            ]),
                        TextInput::make('commission_tdev_renewal')
                            ->label('Comisión Renovacion TDEV US$')
                            ->helperText('Valor expresado en porcentaje. Utilice separador decimal(.)')
                            ->prefix('%')
                            ->numeric()
                            ->validationMessages([
                                'numeric'   => 'Campo tipo numerico.',
                            ]),
                    ])->columnSpanFull()->columns(2),
                Section::make('COMENTARIOS')
                    ->collapsed()
                    ->icon('heroicon-m-folder-plus')
                    ->schema([
                        Textarea::make('comments')
                            ->label('Comentarios')
                    ])->columnSpanFull(),
            ]);
    }
}
