<?php

namespace App\Filament\Master\Resources\Agents\Schemas;

use App\Models\Agent;
use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\State;
use App\Support\CountrySelectOptions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AgentForm
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('masterAgentFormTabs')
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Informacion Principal')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Section::make('Informacion Principal')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make()->schema([
                                            Select::make('agent_type_id')
                                                ->label('Tipo de Agente')
                                                ->options(function (Get $get) {
                                                    return DB::table('agent_types')
                                                        ->whereIn('definition', ['AGENTE', 'SUB-AGENTE'])
                                                        ->get()
                                                        ->pluck('definition', 'id');
                                                })
                                                ->searchable()
                                                ->live()
                                                ->preload(),
                                            Select::make('owner_agent')
                                                ->label('Agente Responsable')
                                                ->options(Agent::select('name', 'id', 'status', 'agent_type_id', 'owner_code')->where('agent_type_id', 2)->where('status', 'ACTIVO')->where('owner_code', Auth::user()->code_agency)->pluck('name', 'id'))
                                                ->searchable()
                                                ->live()
                                                ->hidden(fn (Get $get) => $get('agent_type_id') == 2)
                                                ->preload()
                                                ->helperText('Esta lista despliega solo los agentes activos y que este registrados en su organización'),
                                            /**Jerarquia */
                                            Hidden::make('created_by')->default(Auth::user()->name),
                                            Hidden::make('status')->default('ACTIVO'),
                                            Hidden::make('owner_code')->default(function (Get $get) {
                                                return Auth::user()->code_agency;
                                            }),
                                        ])->columnSpanFull()->columns(4),
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
                                            ->unique(
                                                ignoreRecord: true,
                                                table: 'agents',
                                                column: 'rif',
                                            )
                                            ->validationMessages([
                                                'unique' => 'El RIF ya se encuentra registrado.',
                                                'numeric' => 'El campo es numerico',
                                            ]),
                                        TextInput::make('ci')
                                            ->label('Cedula de Identidad')
                                            ->prefix('V/E/C')
                                            ->numeric()
                                            ->unique(
                                                ignoreRecord: true,
                                                table: 'agents',
                                                column: 'ci',
                                            )
                                            ->required()
                                            ->validationMessages([
                                                'unique' => 'El RIF ya se encuentra registrado.',
                                                'numeric' => 'El campo es numerico',
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
                                                'required' => 'Campo Requerido',
                                            ])
                                            ->preload(),

                                        DatePicker::make('birth_date')
                                            ->label('Fecha de Nacimiento')
                                            ->prefixIcon('heroicon-m-calendar-days')
                                            ->displayFormat('d/m/Y')
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Campo Requerido',
                                            ]),

                                        DatePicker::make('company_init_date')
                                            ->label('Fecha de Ingreso')
                                            ->prefixIcon('heroicon-m-calendar-days')
                                            ->displayFormat('d/m/Y'),

                                        TextInput::make('email')
                                            ->label('Correo electrónico')
                                            ->prefixIcon('heroicon-s-at-symbol')
                                            ->email()
                                            ->required()
                                            ->unique(
                                                ignoreRecord: true,
                                                table: 'agents',
                                                column: 'email',
                                            )
                                            ->validationMessages([
                                                'unique' => 'El Correo electrónico ya se encuentra registrado.',
                                                'required' => 'Campo requerido',
                                                'email' => 'El campo es un email',
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
                                                'required' => 'Campo Requerido',
                                            ])
                                            ->maxLength(255),
                                        Select::make('country_code')
                                            ->label('Código de país')
                                            ->options([
                                                '+1' => '🇺🇸 +1 (Estados Unidos)',
                                                '+44' => '🇬🇧 +44 (Reino Unido)',
                                                '+49' => '🇩🇪 +49 (Alemania)',
                                                '+33' => '🇫🇷 +33 (Francia)',
                                                '+34' => '🇪🇸 +34 (España)',
                                                '+39' => '🇮🇹 +39 (Italia)',
                                                '+7' => '🇷🇺 +7 (Rusia)',
                                                '+55' => '🇧🇷 +55 (Brasil)',
                                                '+91' => '🇮🇳 +91 (India)',
                                                '+86' => '🇨🇳 +86 (China)',
                                                '+81' => '🇯🇵 +81 (Japón)',
                                                '+82' => '🇰🇷 +82 (Corea del Sur)',
                                                '+52' => '🇲🇽 +52 (México)',
                                                '+58' => '🇻🇪 +58 (Venezuela)',
                                                '+57' => '🇨🇴 +57 (Colombia)',
                                                '+54' => '🇦🇷 +54 (Argentina)',
                                                '+56' => '🇨🇱 +56 (Chile)',
                                                '+51' => '🇵🇪 +51 (Perú)',
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
                                                '+20' => '🇪🇬 +20 (Egipto)',
                                                '+27' => '🇿🇦 +27 (Sudáfrica)',
                                                '+234' => '🇳🇬 +234 (Nigeria)',
                                                '+212' => '🇲🇦 +212 (Marruecos)',
                                                '+971' => '🇦🇪 +971 (Emiratos Árabes)',
                                                '+92' => '🇵🇰 +92 (Pakistán)',
                                                '+880' => '🇧🇩 +880 (Bangladesh)',
                                                '+62' => '🇮🇩 +62 (Indonesia)',
                                                '+63' => '🇵🇭 +63 (Filipinas)',
                                                '+66' => '🇹🇭 +66 (Tailandia)',
                                                '+60' => '🇲🇾 +60 (Malasia)',
                                                '+65' => '🇸🇬 +65 (Singapur)',
                                                '+61' => '🇦🇺 +61 (Australia)',
                                                '+64' => '🇳🇿 +64 (Nueva Zelanda)',
                                                '+90' => '🇹🇷 +90 (Turquía)',
                                                '+375' => '🇧🇾 +375 (Bielorrusia)',
                                                '+372' => '🇪🇪 +372 (Estonia)',
                                                '+371' => '🇱🇻 +371 (Letonia)',
                                                '+370' => '🇱🇹 +370 (Lituania)',
                                                '+48' => '🇵🇱 +48 (Polonia)',
                                                '+40' => '🇷🇴 +40 (Rumania)',
                                                '+46' => '🇸🇪 +46 (Suecia)',
                                                '+47' => '🇳🇴 +47 (Noruega)',
                                                '+45' => '🇩🇰 +45 (Dinamarca)',
                                                '+41' => '🇨🇭 +41 (Suiza)',
                                                '+43' => '🇦🇹 +43 (Austria)',
                                                '+31' => '🇳🇱 +31 (Países Bajos)',
                                                '+32' => '🇧🇪 +32 (Bélgica)',
                                                '+353' => '🇮🇪 +353 (Irlanda)',
                                                '+375' => '🇧🇾 +375 (Bielorrusia)',
                                                '+380' => '🇺🇦 +380 (Ucrania)',
                                                '+994' => '🇦🇿 +994 (Azerbaiyán)',
                                                '+995' => '🇬🇪 +995 (Georgia)',
                                                '+976' => '🇲🇳 +976 (Mongolia)',
                                                '+998' => '🇺🇿 +998 (Uzbekistán)',
                                                '+84' => '🇻🇳 +84 (Vietnam)',
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
                                                'required' => 'Campo Requerido',
                                            ])
                                            ->hiddenOn('edit'),
                                        TextInput::make('phone')
                                            ->prefixIcon('heroicon-s-phone')
                                            ->tel()
                                            ->label('Número de teléfono')
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Campo Requerido',
                                            ])
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                                $countryCode = $get('country_code');
                                                if ($countryCode) {
                                                    $cleanNumber = ltrim(preg_replace('/[^0-9]/', '', $state), '0');
                                                    $set('phone', $countryCode.$cleanNumber);
                                                }
                                            }),

                                        Fieldset::make('Dirección en Venezuela')
                                            ->schema([

                                                Select::make('country_id')
                                                    ->label('País')
                                                    ->live()
                                                    ->afterStateUpdated(function (Set $set): void {
                                                        $set('state_id', null);
                                                        $set('city_id', null);
                                                        $set('region', null);
                                                    })
                                                    ->options(Country::all()->pluck('name', 'id'))
                                                    ->searchable()
                                                    ->disabled()
                                                    ->default(183) // Venezuela
                                                    ->prefixIcon('heroicon-s-globe-europe-africa'),
                                                Select::make('state_id')
                                                    ->label('Estado')
                                                    ->options(function (Get $get): array {
                                                        if (blank($get('country_id'))) {
                                                            return [];
                                                        }

                                                        return State::query()
                                                            ->where('country_id', $get('country_id'))
                                                            ->orderBy('definition')
                                                            ->pluck('definition', 'id')
                                                            ->all();
                                                    })
                                                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                                                        $regionId = State::query()->whereKey($state)->value('region_id');
                                                        $region = Region::query()->whereKey($regionId)->value('definition');
                                                        $set('region', $region);
                                                        $set('city_id', null);
                                                    })
                                                    ->live()
                                                    ->searchable()
                                                    ->prefixIcon('heroicon-s-globe-europe-africa')
                                                    ->preload(),
                                                Select::make('city_id')
                                                    ->label('Ciudad')
                                                    ->options(function (Get $get) {
                                                        return City::where('country_id', $get('country_id'))->where('state_id', $get('state_id'))->pluck('definition', 'id');
                                                    })
                                                    ->searchable()
                                                    ->prefixIcon('heroicon-s-globe-europe-africa')
                                                    ->preload(),

                                                Textarea::make('address')
                                                    ->columnSpanFull()
                                                    ->label('Dirección')
                                                    ->afterStateUpdatedJs(<<<'JS'
                                                                                            $set('address', $state.toUpperCase());
                                                                                        JS)
                                                    ->live(onBlur: true)
                                                    ->rows(1)
                                                    ->maxLength(255),

                                            ])->columnSpanFull()->columns(3),
                                        Fieldset::make('Dirección en Otros Paises')
                                            ->schema([

                                                Select::make('country_other_country')
                                                    ->label('País')
                                                    ->live()
                                                    ->default(185)
                                                    ->afterStateUpdated(function (Set $set): void {
                                                        $set('state_id', null);
                                                        $set('city_id', null);
                                                        $set('region', null);
                                                    })
                                                    ->options(fn(): array => CountrySelectOptions::exceptVenezuelaInSpanish())
                                                    ->searchable()
                                                    ->prefixIcon('heroicon-s-globe-europe-africa'),
                                                TextInput::make('state_other_country')
                                                    ->label('Estado')
                                                    ->prefixIcon('heroicon-s-globe-europe-africa'),
                                                TextInput::make('city_other_country')
                                                    ->label('Ciudad')
                                                    ->prefixIcon('heroicon-s-globe-europe-africa'),
                                                TextInput::make('postal_code_other_country')
                                                    ->label('Código Postal')
                                                    ->prefixIcon('heroicon-s-identification')
                                                    ->maxLength(255),
                                                Textarea::make('address_other_country')
                                                    ->columnSpanFull()
                                                    ->rows(1)
                                                    ->label('Dirección')
                                                    ->afterStateUpdatedJs(<<<'JS'
                                                                                            $set('address_other_country', $state.toUpperCase());
                                                                                        JS)
                                                    ->live(onBlur: true)
                                                    ->maxLength(255),

                                            ])->columnSpanFull()->columns(4),
                                        
                                        TextInput::make('user_tdev')
                                            ->label('Usuario de Tu Doctor en Viajes (TDEV)')
                                            ->prefixIcon('heroicon-s-identification')
                                            ->maxLength(255),
                                        TextInput::make('user_instagram')
                                            ->label('Usuario de Instagram')
                                            ->prefixIcon('heroicon-s-user')
                                            ->maxLength(255),
                                    ])->columnSpanFull()->columns(3),
                            ]),
                        Tab::make('Comisiones')
                            ->icon('heroicon-o-currency-dollar')
                            ->schema([
                                Section::make('Comisiones')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
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
                                                    ->rules(['required', 'max:20'])
                                                    ->validationMessages([
                                                        'required' => 'Campo Requerido.',
                                                        'max' => 'El valor no puede ser mayor al 20%',
                                                    ]),
                                                TextInput::make('commission_tdec_renewal')
                                                    ->label('Comisión Renovacion TDEC US$')
                                                    ->helperText('Valor expresado en porcentaje. Utilice separador decimal(.)')
                                                    ->prefix('%')
                                                    ->numeric()
                                                    ->rules(['required', 'max:20'])
                                                    ->validationMessages([
                                                        'required' => 'Campo Requerido.',
                                                        'max' => 'El valor no puede ser mayor al 20%',
                                                    ]),

                                            ])->columnSpanFull(),
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('commission_tdev')
                                                    ->label('Comisión TDEV US$')
                                                    ->helperText('Valor expresado en porcentaje. Utilice separador decimal(.)')
                                                    ->prefix('%')
                                                    ->numeric()
                                                    ->rules(['required', 'max:35'])
                                                    ->validationMessages([
                                                        'required' => 'Campo Requerido.',
                                                        'max' => 'El valor no puede ser mayor al 20%',
                                                    ]),
                                                TextInput::make('commission_tdev_renewal')
                                                    ->label('Comisión Renovacion TDEV US$')
                                                    ->helperText('Valor expresado en porcentaje. Utilice separador decimal(.)')
                                                    ->prefix('%')
                                                    ->numeric()
                                                    ->rules(['required', 'max:35'])
                                                    ->validationMessages([
                                                        'required' => 'Campo Requerido.',
                                                        'max' => 'El valor no puede ser mayor al 20%',
                                                    ]),
                                            ])->columnSpanFull(),
                                    ]),
                            ]),
                        Tab::make('Información Bancaria Local(VES)')
                            ->icon('heroicon-o-building-library')
                            ->schema([
                                Section::make('Información Bancaria Local(VES)')
                                    ->description('Datos bancarios para recibir pagos en moneda nacional')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
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
                                                'numeric' => 'Campo tipo numerico',
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
                                                    'BANCO DE VENEZUELA' => 'BANCO DE VENEZUELA',
                                                    'BANCO BICENTENARIO' => 'BANCO BICENTENARIO',
                                                    'BANCO MERCANTIL' => 'BANCO MERCANTIL',
                                                    'BANCO PROVINCIAL' => 'BANCO PROVINCIAL',
                                                    'BANCO CARONI' => 'BANCO CARONI',
                                                    'BANCO DEL CARIBE' => 'BANCO DEL CARIBE',
                                                    'BANCO DEL TESORO' => 'BANCO DEL TESORO',
                                                    'BANCO NACIONAL DE CREDITO' => 'BANCO NACIONAL DE CREDITO',
                                                    'BANESCO' => 'BANESCO',
                                                    'BANCO CARONI' => 'BANCO CARONI',
                                                    'FONDO COMUN' => 'FONDO COMUN',
                                                    'BANCO CANARIAS' => 'BANCO CANARIAS',
                                                    'BANCO DEL SUR' => 'BANCO DEL SUR',
                                                    'BANCO AGRICOLA DE VENEZUELA' => 'BANCO AGRICOLA DE VENEZUELA',
                                                    'BANPLUS' => 'BANPLUS',
                                                    'MI BANCO' => 'MI BANCO',
                                                    'BANCAMIGA' => 'BANCAMIGA',
                                                    'BANFANB' => 'BANFANB',
                                                    'BANCARIBE' => 'BANCARIBE',
                                                    'BANCO ACTIVO' => 'BANCO ACTIVO',
                                                    'BANCO VENEZOLANO DE CREDITO' => 'BANCO VENEZOLANO DE CREDITO',
                                                ]),
                                            Select::make('local_beneficiary_account_type')
                                                ->label('Tipo de Cuenta del Beneficiario')
                                                ->prefixIcon('heroicon-s-identification')
                                                ->options([
                                                    'AHORRO' => 'AHORRO',
                                                    'CORRIENTE' => 'CORRIENTE',
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
                                                    'BANCO DE VENEZUELA' => 'BANCO DE VENEZUELA',
                                                    'BANCO BICENTENARIO' => 'BANCO BICENTENARIO',
                                                    'BANCO MERCANTIL' => 'BANCO MERCANTIL',
                                                    'BANCO PROVINCIAL' => 'BANCO PROVINCIAL',
                                                    'BANCO CARONI' => 'BANCO CARONI',
                                                    'BANCO DEL CARIBE' => 'BANCO DEL CARIBE',
                                                    'BANCO DEL TESORO' => 'BANCO DEL TESORO',
                                                    'BANCO NACIONAL DE CREDITO' => 'BANCO NACIONAL DE CREDITO',
                                                    'BANESCO' => 'BANESCO',
                                                    'BANCO CARONI' => 'BANCO CARONI',
                                                    'FONDO COMUN' => 'FONDO COMUN',
                                                    'BANCO CANARIAS' => 'BANCO CANARIAS',
                                                    'BANCO DEL SUR' => 'BANCO DEL SUR',
                                                    'BANCO AGRICOLA DE VENEZUELA' => 'BANCO AGRICOLA DE VENEZUELA',
                                                    'BANPLUS' => 'BANPLUS',
                                                    'MI BANCO' => 'MI BANCO',
                                                    'BANCAMIGA' => 'BANCAMIGA',
                                                    'BANFANB' => 'BANFANB',
                                                    'BANCARIBE' => 'BANCARIBE',
                                                    'BANCO ACTIVO' => 'BANCO ACTIVO',
                                                ]),
                                            Select::make('local_beneficiary_account_type_mon_inter')
                                                ->label('Tipo de Cuenta del Beneficiario')
                                                ->prefixIcon('heroicon-s-identification')
                                                ->options([
                                                    'AHORRO' => 'AHORRO',
                                                    'CORRIENTE' => 'CORRIENTE',
                                                ]),
                                        ])->columnSpanFull()->columns(3),

                                    ])->columnSpanFull()->columns(3),
                            ]),
                        Tab::make('Información Bancaria Extra(US$)')
                            ->icon('heroicon-o-globe-alt')
                            ->schema([
                                Section::make('Información Bancaria Extra(US$)')
                                    ->description('Datos bancarios para recibir pagos en moneda extranjera')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        TextInput::make('extra_beneficiary_name')
                                            ->label('Nombre/Razon Social')
                                            ->afterStateUpdatedJs(<<<'JS'
                                            $set('extra_beneficiary_name', $state.toUpperCase());
                                        JS)
                                            ->live(onBlur: true)
                                            ->prefixIcon('heroicon-s-identification')
                                            ->maxLength(255),
                                        TextInput::make('extra_beneficiary_ci_rif')
                                            ->label('Nro. CI/RIF/ID/PASAPORTE')
                                            ->prefixIcon('heroicon-s-identification')
                                            ->numeric()
                                            ->validationMessages([
                                                'numeric' => 'Campo tipo numeric',
                                            ])
                                            ->maxLength(255),
                                        TextInput::make('extra_beneficiary_account_number')
                                            ->label('Número de cuenta')
                                            ->prefixIcon('heroicon-s-identification')
                                            ->numeric()
                                            ->validationMessages([
                                                'numeric' => 'Campo tipo numeric',
                                            ])
                                            ->live()
                                            ->maxLength(255),
                                        Select::make('extra_beneficiary_account_bank')
                                            ->label('Banco')
                                            ->prefixIcon('heroicon-s-identification')
                                            ->searchable()
                                            ->preload()
                                            ->options([
                                                'FACEBANK INTERNATIONAL' => 'FACEBANK INTERNATIONAL',
                                                'JPMORGAN CHASE & CO' => 'JPMORGAN CHASE & CO',
                                                'BANK OF AMERICA' => 'BANK OF AMERICA',
                                                'WELLS FARGO' => 'WELLS FARGO',
                                                'CITIBANK (CITIGROUP)' => 'CITIBANK (CITIGROUP)',
                                                'U.S. BANK' => 'U.S. BANK',
                                                'PNC FINANCIAL SERVICES' => 'PNC FINANCIAL SERVICES',
                                                'TRUIST FINANCIAL CORPORATION' => 'TRUIST FINANCIAL CORPORATION',
                                                'CAPITAL ONE' => 'CAPITAL ONE',
                                                'TD BANK (TORONTO-DOMINION BANK)' => 'TD BANK (TORONTO-DOMINION BANK)',
                                                'HSBC BANK USA' => 'HSBC BANK USA',
                                                'FIFTH THIRD BANK' => 'FIFTH THIRD BANK',
                                                'REGIONS FINANCIAL CORPORATION' => 'REGIONS FINANCIAL CORPORATION',
                                                'HUNTINGTON NATIONAL BANK' => 'HUNTINGTON NATIONAL BANK',
                                                'NAVY FEDERAL CREDIT UNION' => 'NAVY FEDERAL CREDIT UNION',
                                                'STATE EMPLOYEES CREDIT UNION (SECU)' => 'STATE EMPLOYEES CREDIT UNION (SECU)',
                                                'BANCO NACIONAL DE PANAMÁ (BNP)' => 'BANCO NACIONAL DE PANAMÁ (BNP)',
                                                'CAJA DE AHORROS' => 'CAJA DE AHORROS',
                                                'BANCO GENERAL' => 'BANCO GENERAL',
                                                'GLOBAL BANK' => 'GLOBAL BANK',
                                                'BANESCO PANAMÁ' => 'BANESCO PANAMÁ',
                                                'METROBANK' => 'METROBANK',
                                                'BANCO LATINOAMERICANO DE COMERCIO EXTERIOR (BLADEX)' => 'BANCO LATINOAMERICANO DE COMERCIO EXTERIOR (BLADEX)',
                                                'HSBC BANK PANAMÁ' => 'HSBC BANK PANAMÁ',
                                                'SCOTIABANK PANAMÁ' => 'SCOTIABANK PANAMÁ',
                                                'CITIBANK PANAMÁ' => 'CITIBANK PANAMÁ',
                                                'BANCO SANTANDER PANAMÁ' => 'BANCO SANTANDER PANAMÁ',
                                                'BANCO DAVIVIENDA PANAMÁ' => 'BANCO DAVIVIENDA PANAMÁ',
                                                'BANCO ALIADO' => 'BANCO ALIADO',
                                                'MULTIBANK' => 'MULTIBANK',
                                                'BANCAMIGA' => 'BANCAMIGA',
                                                'BANCO DEL TESORO' => 'BANCO DEL TESORO',
                                                'PROVINCIAL' => 'PROVINCIAL',
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
                                                'CUENTA DE CHEQUES (CHECKING ACCOUNT)' => 'CUENTA DE CHEQUES (CHECKING ACCOUNT)',
                                                'CUENTA DE AHORROS (SAVINGS ACCOUNT)' => 'CUENTA DE AHORROS (SAVINGS ACCOUNT)',
                                                'CUENTA CORRIENTE (CURRENT ACCOUNT)' => 'CUENTA CORRIENTE (CURRENT ACCOUNT)',
                                                'CUENTA DE DEPÓSITO A PLAZO FIJO (CERTIFICATE OF DEPOSIT - CD)' => 'CUENTA DE DEPÓSITO A PLAZO FIJO (CERTIFICATE OF DEPOSIT - CD)',
                                                'CUENTA DE NEGOCIOS (BUSINESS ACCOUNT)' => 'CUENTA DE NEGOCIOS (BUSINESS ACCOUNT)',
                                                'CUENTA DE INVERSIÓN (INVESTMENT ACCOUNT)' => 'CUENTA DE INVERSIÓN (INVESTMENT ACCOUNT)',
                                                'CUENTA DE RETIRO INDIVIDUAL (INDIVIDUAL RETIREMENT ACCOUNT - IRA)' => 'CUENTA DE RETIRO INDIVIDUAL (INDIVIDUAL RETIREMENT ACCOUNT - IRA)',
                                                'CUENTA DE FONDOS DE EMERGENCIA (EMERGENCY FUND ACCOUNT)' => 'CUENTA DE FONDOS DE EMERGENCIA (EMERGENCY FUND ACCOUNT)',
                                                'CUENTA PARA MENORES (MINOR ACCOUNT / CUSTODIAL ACCOUNT)' => 'CUENTA PARA MENORES (MINOR ACCOUNT / CUSTODIAL ACCOUNT)',
                                                'CUENTA CONJUNTA (JOINT ACCOUNT)' => 'CUENTA CONJUNTA (JOINT ACCOUNT)',
                                                'CUENTA EN MONEDA EXTRANJERA (CUENTA EN DÓLARES, EUROS, ETC.)' => 'CUENTA EN MONEDA EXTRANJERA (CUENTA EN DÓLARES, EUROS, ETC.)',
                                                'CUENTA DE RETIRO (CUENTA DE JUBILACIÓN)' => 'CUENTA DE RETIRO (CUENTA DE JUBILACIÓN)',
                                                'CUENTA DE FIDEICOMISO (TRUST ACCOUNT)' => 'CUENTA DE FIDEICOMISO (TRUST ACCOUNT)',
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
                            ]),
                    ]),
            ]);
    }
}
