<?php

namespace App\Livewire;


use App\Models\City;

use App\Models\User;
use App\Models\Agent;
use App\Models\State;
use Pages\EditAgency;
use App\Models\Agency;
use App\Models\Region;
use App\Models\Country;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Livewire\Component;
use Filament\Forms\Form;
use App\Models\AgentType;
use App\Models\TypeAgent;
use Filament\MarkdownEditor;
use Filament\Forms\Components\Grid;
use Illuminate\Contracts\View\View;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Wizard;
use Illuminate\Support\Facades\Crypt;
use Filament\Forms\Components\Section;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use App\Http\Controllers\AgentController;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;


class CreateSubAgent extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];
    public $code;

    public function mount($code = null): void
    {
        $this->code = $code;
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        $code_decrypted = isset($this->code) ? Crypt::decryptString($this->code) : 'TDG-100';

        return $form
            ->schema([
                Section::make('AGENTES')
                    ->collapsible()
                    ->description('Fomulario para el registro de agentes. Campo Requerido(*)')
                    ->icon('heroicon-s-building-library')
                    ->schema([
                        TextInput::make('code_agency')
                            ->label('Código TDG')
                            ->prefixIcon('heroicon-m-clipboard-document-check')
                            ->default($code_decrypted)
                            ->disabled()
                            ->dehydrated()
                            ->maxLength(255),
                        Select::make('agent_type_id')
                            ->label('Tipo de agente')
                            ->live()
                            ->options(AgentType::all()->pluck('definition', 'id'))
                            ->searchable()
                            ->prefixIcon('heroicon-s-globe-europe-africa')
                            ->required()
                            ->validationMessages([
                                'required'  => 'Campo Requerido',
                            ])
                            ->preload(),
                    ])->columns(2),
                Section::make('INFORMACION PRINCIPAL')
                    ->description('Fomulario. Campo Requerido(*)')
                    ->collapsed()
                    ->icon('heroicon-s-building-office-2')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre y Apellido')
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
                        TextInput::make('ci')
                            ->label('Cedula de Identidad')
                            ->prefix('V/E/C')
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
                        TextInput::make('email')
                            ->label('Email')
                            ->prefixIcon('heroicon-s-at-symbol')
                            ->email()
                            ->required()
                            ->unique(
                                ignoreRecord: true,
                                table: 'agents',
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
                            ->live(onBlur: true),
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
                    ])->columns(3),
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
                        Grid::make(3)->schema([
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
                        ]),

                    ])->columns(3),
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
                                'EL BANCO MERCANTIL PANAMÁ'                             => 'EL BANCO MERCANTIL PANAMÁ',
                                'ENCORE BANK'                                            => 'ENCORE BANK',
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
                    ])->columns(3),
                Section::make('ACUERDO Y CONDICIONES')
                    ->collapsed()
                    ->description('Este acuerdo establece la relación entre la Agencia o Agente y Tu Dr. Group ( quien en adelante se denominará la Compañía ) los cuales
                                    se sujetarán a los siguientes términos: La Compañía le ha asignado a la Agencia o Agente un número de identificación ( arriba mencionado ),
                                    bajo el cual se registrará el negocio proveniente de dicha Agencia o Agente.
                                    Este Acuerdo será efectivo a partir de la fecha de suscripción en la Compañía. Cualquiera de las partes puede terminar este Acuerdo con un
                                    preaviso mínimo de sesenta (60) días, siempre y cuando notique su intención por escrito.
                                    La Compañía se reserva el derecho a terminar este Acuerdo por motivo de actos fraudulentos o el incumplimiento de cualquiera de las normas
                                    contenidas en el mismo.
                                    La Agencia tiene la potestad de denir la comisión por venta que reciben los agentes bajo su estructura.
                                    Forma parte integrante e indivisible de este acuerdo: el Addendum contentivo de las normas particulares que rigen la relación, los documentos
                                    complementarios de identicación de la Agencia o el Agente, los datos ociales de identicación de las cuentas bancarias dispuestas para el
                                    pago de comisiones y cualquier otro documento que se adjunte en el trascurso de la relación comercial.')
                    ->icon('heroicon-m-folder-plus')
                    ->schema([
                        Checkbox::make('is_accepted')
                            ->label('ACEPTO')
                            ->required(),
                        Grid::make(2)->schema([
                            FileUpload::make('fir_dig_agent')
                                ->label('Firma Digitalizada del Agente')
                                ->uploadingMessage('Cargando firma...')
                                ->required()
                                ->validationMessages([
                                    'required' => 'Debe cargar la firma digital',
                                ])
                                ->image()
                                ->imageEditor()
                                ->imageEditorAspectRatios([
                                    '16:9',
                                    '4:3',
                                    '1:1',
                                ]),
                            FileUpload::make('file_ci_rif')
                                ->label('CI/RIF')
                                ->required()
                                ->uploadingMessage('Cargando documento...')
                                ->image()
                                ->imageEditor()
                                ->imageEditorAspectRatios([
                                    '16:9',
                                    '4:3',
                                    '1:1',
                                ]),
                            FileUpload::make('file_w8_w9')
                                ->label('W8/W9')
                                ->live()
                                ->hidden(fn(Get $get) => $get('extra_beneficiary_account_number') == null)
                                ->uploadingMessage('Cargando documento...'),
                        ])
                    ])->columns(3),
            ])->statePath('data');
    }

    public function create(): void
    {

        $data = $this->form->getState();

        if ($data['local_beneficiary_account_number'] == null || $data['extra_beneficiary_account_number']) {
            Notification::make()
                ->title('NOTIFICACION')
                ->body('El registro no puede ser completado. Debe completar al menos uno de los donde se solicita la informacion bancaria NACIONAL O EXTRANGERA.')
                ->icon('heroicon-m-user-plus')
                ->iconColor('error')
                ->danger()
                ->send();

            return;
        }

        Agent::create([
            "code_agency"                       => $data['code_agency'],
            "owner_code"                        => $data['code_agency'],
            "name"                              => $data['name'],
            "ci"                                => $data['ci'],
            "sex"                               => $data['sex'],
            "birth_date"                        => $data['birth_date'],
            "email"                             => $data['email'],
            "address"                           => $data['address'],
            "country_code"                      => $data['country_code'],
            "phone"                             => $data['phone'],
            "country_id"                        => $data['country_id'],
            "state_id"                          => $data['state_id'],
            "region"                            => $data['region'],
            "city_id"                           => $data['city_id'],
            "user_instagram"                    => $data['user_instagram'],
            "local_beneficiary_name"            => $data['local_beneficiary_name'],
            "local_beneficiary_rif"             => $data['local_beneficiary_rif'],
            "local_beneficiary_account_number"  => $data['local_beneficiary_account_number'],
            "local_beneficiary_account_bank"    => $data['local_beneficiary_account_bank'],
            "local_beneficiary_account_type"    => $data['local_beneficiary_account_type'],
            "local_beneficiary_phone_pm"        => $data['local_beneficiary_phone_pm'],
            "extra_beneficiary_name"            => $data['extra_beneficiary_name'],
            "extra_beneficiary_ci_rif"          => $data['extra_beneficiary_ci_rif'],
            "extra_beneficiary_account_number"  => $data['extra_beneficiary_account_number'],
            "extra_beneficiary_account_bank"    => $data['extra_beneficiary_account_bank'],
            "extra_beneficiary_address"         => $data['extra_beneficiary_address'],
            "extra_beneficiary_account_type"    => $data['extra_beneficiary_account_type'],
            "extra_beneficiary_route"           => $data['extra_beneficiary_route'],
            "extra_beneficiary_swift"           => $data['extra_beneficiary_swift'],
            "extra_beneficiary_zelle"           => $data['extra_beneficiary_zelle'],
            "is_accepted"                       => $data['is_accepted'],
            "fir_dig_agent"                     => $data['fir_dig_agent'],
            "file_ci_rif"                       => $data['file_ci_rif'],
            "agent_type_id"                     => $data['agent_type_id'],
            "file_w8_w9"                        => isset($data['file_w8_w9']) ? $data['file_w8_w9'] : 0,
            "status"                            => 'POR REVISION',
            "created_by"                        => 'LINK EXTERNO',
        ]);

        Notification::make()
            ->title('AGENTE REGISTRADA')
            ->body('El registro fue enviado con exito')
            ->icon('heroicon-m-user-plus')
            ->iconColor('success')
            ->success()
            ->seconds(5)
            ->send();

        //Notificacion para Admin
        $recipient = User::where('is_admin', 1)->get();
        foreach ($recipient as $user) {
            $recipient_for_user = User::find($user->id);
            Notification::make()
                ->title('REGISTRO DE NUEVO AGENTE')
                ->body('Se ha registrado un nuevo agente de forma exitosa')
                ->icon('heroicon-m-user-plus')
                ->iconColor('success')
                ->success()
                ->sendToDatabase($recipient_for_user);
        }

        $this->form->fill();

        //redirect
        $this->redirect('/at/c');
    }

    public function render(): View
    {
        return view('livewire.create-sub-agent');
    }
}