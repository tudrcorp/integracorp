<?php

namespace App\Filament\Business\Resources\TravelAgencies\Schemas;

use App\Http\Controllers\UtilsController;
use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\State;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
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
                Section::make("Brand Logo")
                    ->description("Logo de la Agencia")
                    ->icon('heroicon-o-identification')
                    ->schema([
                        FileUpload::make('logo')
                            ->label('Logo')
                            ->directory('logos-agencias-viajes')
                            ->image(),

                    ])->columnSpanFull()->columns(4),
                    
                Section::make("Informacion General")
                    ->collapsed()
                    ->description("Informacion General de la Agencia")
                    ->icon('heroicon-o-paper-airplane')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre de la Agencia')
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
                        DatePicker::make('aniversary')
                            ->label('Fecha Aniversario de la Agencia')
                            ->format('d/m/Y'),
                        TextInput::make('representante')
                            ->label('Nombre del Representante'),
                        TextInput::make('idRepresentante')
                            ->label('ID Representante'),
                        DatePicker::make('FechaNacimientoRepresentante')
                            ->label('Fecha de Nacimiento Representante')
                            ->format('d/m/Y'),
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
                        TextInput::make('phoneAdditional')
                            ->label('Número de teléfono adicional')
                            ->tel(),
                        TextInput::make('email')
                            ->label('Email address')
                            ->email(),
                        TextInput::make('userInstagram')
                            ->label('Usuario Instagram'),
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
                        TextInput::make('address')
                            ->label('Direccion'),
                        TextInput::make('status')
                            ->label('Status')
                            ->default('Activo'),

                        TextInput::make('userPortalWeb')
                            ->label('Usuario portal web'),
                        DatePicker::make('fechaIngreso')
                            ->label('Fecha de Ingreso')
                            ->format('d/m/Y')
                            ->default(now()),
                        Hidden::make('createdBy')->default(Auth::user()->name)->hiddenOn('edit'),
                        Hidden::make('updatedBy')->default(Auth::user()->name)->hiddenOn('create'),

                    ])->columnSpanFull()->columns(4),

                Section::make("Contacto Secundario")
                    ->collapsed()
                    ->description("Informacion de Contacto Secundario de la Agencia")
                    ->icon('heroicon-o-phone-arrow-up-right')
                    ->schema([
                        TextInput::make('nameSecundario')
                            ->label('Nombre/Razón Social del Beneficiario')
                            ->afterStateUpdatedJs(<<<'JS'
                                        $set('nameSecundario', $state.toUpperCase());
                                    JS)
                            ->live(onBlur: true)
                            ->prefixIcon('heroicon-s-identification')
                            ->maxLength(255),
                        TextInput::make('emailSecundario')
                            ->label('Email')
                            ->afterStateUpdatedJs(<<<'JS'
                                            $set('emailSecundario', $state.toUpperCase());
                                        JS)
                            ->live(onBlur: true)
                            ->prefixIcon('heroicon-s-identification')
                            ->maxLength(255),
                        TextInput::make('phoneSecundario')
                            ->label('Telefono')
                            ->afterStateUpdatedJs(<<<'JS'
                                        $set('phoneSecundario', $state.toUpperCase());
                                    JS)
                            ->live(onBlur: true)
                            ->prefixIcon('heroicon-s-identification')
                            ->maxLength(255),
                        DatePicker::make('fechaNacimientoSecundario')
                            ->label('Fecha de Nacimiento')
                            ->format('d/m/Y'),

                    ])->columnSpanFull()->columns(4),

                Section::make('Agentes')
                    ->description('Información de agentes asociados a la agencia de viajes.')
                    ->collapsed()
                    ->schema([
                        Repeater::make('travelAgents')
                            ->label('Tabla dinamica de Agentes')
                            ->relationship()
                            ->table([
                                TableColumn::make('Nombre y Apellido'),
                                TableColumn::make('Cargo'),
                                TableColumn::make('Correo Electrónico'),
                                TableColumn::make('Teléfono'),
                                TableColumn::make('Fecha de Nacimiento'),
                            ])
                            ->schema([
                                TextInput::make('name')
                                    ->afterStateUpdatedJs(<<<'JS'
                                                    $set('name', $state.toUpperCase());    
                                                JS),
                                TextInput::make('cargo')
                                    ->afterStateUpdatedJs(<<<'JS'
                                                $set('cargo', $state.toUpperCase());    
                                            JS),
                                TextInput::make('email')
                                    ->afterStateUpdatedJs(<<<'JS'
                                                $set('email', $state.toUpperCase());    
                                            JS),
                                TextInput::make('phone')
                                    ->afterStateUpdatedJs(<<<'JS'
                                                $set('phone', $state.toUpperCase());    
                                            JS),
                                DatePicker::make('fechaNacimiento')
                                    ->label('Fecha de Nacimiento')
                                    ->rules(['required', 'date'])
                                    ->validationMessages([
                                        'required' => 'El campo es obligatorio.',
                                        'date' => 'El campo debe ser una fecha.',
                                    ]),

                                Hidden::make('created_by')->default(Auth::user()->name)->hiddenOn('edit'),
                                Hidden::make('updated_by')->default(Auth::user()->name)->hiddenOn('create'),
                            ])
                            ->addActionLabel('Añadir Contacto')
                            ->columnSpanFull()
                            ->reorderable()
                    ])->columnSpanFull(),

                Section::make('Información Jerarquica')
                    ->description("Informacion Jerarquica de la Agencia y Comiciones")
                    ->icon('heroicon-m-adjustments-vertical')
                    ->collapsed()
                    ->columns(2)
                    ->schema([
                        Select::make('classification')
                            ->label('Clasificación')
                            ->options([
                                'AGENCIA DE VIEAJES'  => 'AGENCIA DE VIEAJES',
                                'MAYORISTA'           => 'MAYORISTA',
                                'CONSOLIDADOR'        => 'CONSOLIDADOR',
                                'FREELANCE'           => 'FREELANCE',
                                'AGENTE'              => 'AGENTE',
                                'AGENTE DE CORRETAJE' => 'AGENTE DE CORRETAJE',
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
                                '1' => '1',
                                '2' => '2',
                                '3' => '3',
                                '4' => '4',
                                '5' => '5',
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

                Section::make('DATOS BANCARIOS MONEDA NACIONAL')
                    ->description('Fomulario. Campo Requerido(*)')
                    ->collapsed()
                    ->icon('heroicon-o-credit-card')
                    ->schema([
                        TextInput::make('local_beneficiary_name')
                            ->label('Nombre/Razón Social del Beneficiario')
                            ->afterStateUpdatedJs(<<<'JS'
                                $set('local_beneficiary_name', $state.toUpperCase());
                            JS)
                            ->live(onBlur: true)
                            ->prefixIcon('heroicon-s-identification')
                            ->maxLength(255),
                        TextInput::make('local_beneficiary_rif')
                            ->label('CI/RIF del Beneficiario')
                            ->prefixIcon('heroicon-s-identification')
                            ->validationMessages([
                                'numeric'  => 'Campo tipo numerico',
                            ])
                            ->maxLength(255),
                        TextInput::make('local_beneficiary_phone_pm')
                            ->label('Teléfono Pago Movil del Beneficiario')
                            ->prefixIcon('heroicon-s-phone')
                            ->tel()
                            ->helperText('Formato: 04121234567, 04241869168')
                            ->mask('09999999999'),

                        Fieldset::make('Cuenta Nacional, Moneda Nacional(Bs.)')->schema([
                            TextInput::make('local_beneficiary_account_number')
                                ->label('Número de Cuenta del Beneficiario')
                                ->prefixIcon('heroicon-s-identification'),
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
                        ])->columnSpanFull()->columns(3),

                        Fieldset::make('Cuenta Nacional, Moneda Intenacional(US$, EUR)')->schema([
                            TextInput::make('local_beneficiary_account_number_mon_inter')
                                ->label('Número de Cuenta del Beneficiario')
                                ->prefixIcon('heroicon-s-identification'),
                            Select::make('local_beneficiary_account_bank_mon_inter')
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
                            Select::make('local_beneficiary_account_type_mon_inter')
                                ->label('Tipo de Cuenta del Beneficiario')
                                ->prefixIcon('heroicon-s-identification')
                                ->options([
                                    'AHORRO'      => 'AHORRO',
                                    'CORRIENTE'   => 'CORRIENTE',
                                ]),
                        ])->columnSpanFull()->columns(3),

                    ])->columnSpanFull()->columns(3),
                Section::make('DATOS BANCARIOS MONEDA EXTRANJERA')
                    ->description('Fomulario. Campo Requerido(*)')
                    ->collapsed()
                    ->icon('heroicon-o-currency-dollar')
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

                
            ]);
    }
}
