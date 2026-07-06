<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\TravelAgencies\Schemas;

use App\Http\Controllers\UtilsController;
use App\Models\City;
use App\Models\Country;
use App\Models\State;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class TravelAgencyForm
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const IOS_SECTION_CLASS = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    public static function travelAgentsRepeater(bool $useRelationship = true): Repeater
    {
        $repeater = Repeater::make('travelAgents')
            ->label('Tabla dinamica de Agentes')
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
                    ->label('Fecha de Nacimiento'),
                Hidden::make('created_by')->default(fn (): string => Auth::user()?->name ?? '')->hiddenOn('edit'),
                Hidden::make('updated_by')->default(fn (): string => Auth::user()?->name ?? '')->hiddenOn('create'),
            ])
            ->addActionLabel('Añadir Contacto')
            ->columnSpanFull()
            ->reorderable();

        if ($useRelationship) {
            $repeater->relationship();
        } else {
            $repeater
                ->defaultItems(1)
                ->minItems(1);
        }

        return $repeater;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('travelAgencyFormTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Marca')
                            ->icon(Heroicon::OutlinedIdentification)
                            ->schema([
                                Section::make('Brand Logo')
                                    ->description('Logo de la Agencia')
                                    ->icon(Heroicon::OutlinedIdentification)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                FileUpload::make('logo')
                                                    ->label('Logo')
                                                    ->directory('logos-agencias-viajes')
                                                    ->image(),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Información general')
                            ->icon(Heroicon::OutlinedPaperAirplane)
                            ->schema([
                                Section::make('Informacion General')
                                    ->description('Informacion General de la Agencia')
                                    ->icon(Heroicon::OutlinedPaperAirplane)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 2, 'xl' => 4])
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextInput::make('name')
                                                    ->label('Nombre de la Agencia')
                                                    ->maxLength(255),
                                                TextInput::make('numberIdentification')
                                                    ->prefix('J/V/E-')
                                                    ->label('Numero de identificacion')
                                                    ->numeric(),
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
                                                    ->live(onBlur: true)
                                                    ->validationMessages([
                                                        'required' => 'Campo Requerido',
                                                    ])
                                                    ->hiddenOn('edit'),
                                                TextInput::make('phone')
                                                    ->prefixIcon('heroicon-s-phone')
                                                    ->tel()
                                                    ->label('Número de teléfono')
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
                                                    ->preload(),
                                                Select::make('state_id')
                                                    ->label('Estado')
                                                    ->options(function (Get $get) {
                                                        if ($get('country_id')) {
                                                            return State::where('country_id', $get('country_id'))->pluck('definition', 'id');
                                                        }

                                                        return [];
                                                    })
                                                    ->live()
                                                    ->searchable()
                                                    ->prefixIcon('heroicon-s-globe-europe-africa')
                                                    ->validationMessages([
                                                        'required' => 'Campo Requerido',
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
                                                Hidden::make('created_by')->default(fn (): string => Auth::user()?->name ?? '')->hiddenOn('edit'),
                                                Hidden::make('updated_by')->default(fn (): string => Auth::user()?->name ?? '')->hiddenOn('create'),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Contactos')
                            ->icon(Heroicon::OutlinedPhoneArrowUpRight)
                            ->schema([
                                Section::make('Contacto del Area Administrativa')
                                    ->description('Informacion de Contacto del Area Administrativa de la Agencia')
                                    ->icon(Heroicon::OutlinedPhoneArrowUpRight)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 2, 'xl' => 4])
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
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
                                            ]),
                                    ]),
                                Section::make('Agentes')
                                    ->description('Información de agentes asociados a la agencia de viajes.')
                                    ->icon(Heroicon::OutlinedUserGroup)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                self::travelAgentsRepeater(),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Jerarquía')
                            ->icon(Heroicon::OutlinedAdjustmentsVertical)
                            ->schema([
                                Section::make('Información Jerarquica')
                                    ->description('Informacion Jerarquica de la Agencia y Comiciones')
                                    ->icon(Heroicon::OutlinedAdjustmentsVertical)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 2, 'xl' => 4])
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                Select::make('classification')
                                                    ->label('Clasificación')
                                                    ->options([
                                                        'AGENCIA DE VIAJES' => 'AGENCIA DE VIAJES',
                                                        'MAYORISTA' => 'MAYORISTA',
                                                        'CONSOLIDADOR' => 'CONSOLIDADOR',
                                                        'FREELANCE' => 'FREELANCE',
                                                        'AGENTE' => 'AGENTE',
                                                        'AGENTE DE CORRETAJE' => 'AGENTE DE CORRETAJE',
                                                    ])
                                                    ->required()
                                                    ->validationMessages([
                                                        'required' => 'Campo Requerido',
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
                                                        'required' => 'Campo Requerido',
                                                    ]),
                                                TextInput::make('agenteSuperiorNivel3')
                                                    ->label('Agente Superior Nivel 3'),
                                                TextInput::make('agenciaSuperiorNivel2')
                                                    ->label('Agencia Superior Nivel 2'),
                                                TextInput::make('agenciaPpalNivel1')
                                                    ->label('Agencia Principal Nivel 1')
                                                    ->default('TDEV')
                                                    ->disabled()
                                                    ->dehydrated(),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Bancos nacionales')
                            ->icon(Heroicon::OutlinedCreditCard)
                            ->schema([
                                Section::make('DATOS BANCARIOS MONEDA NACIONAL')
                                    ->description('Fomulario. Campo Requerido(*)')
                                    ->icon(Heroicon::OutlinedCreditCard)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 2, 'xl' => 3])
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
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
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Bancos extranjeros')
                            ->icon(Heroicon::OutlinedCurrencyDollar)
                            ->schema([
                                Section::make('DATOS BANCARIOS MONEDA EXTRANJERA')
                                    ->description('Fomulario. Campo Requerido(*)')
                                    ->icon(Heroicon::OutlinedCurrencyDollar)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 2, 'xl' => 4])
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
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
                                                        'numeric' => 'Campo tipo numeric',
                                                    ])
                                                    ->maxLength(255),
                                                TextInput::make('extra_beneficiary_account_number')
                                                    ->label('Número de cuenta')
                                                    ->numeric()
                                                    ->validationMessages([
                                                        'numeric' => 'Campo tipo numerico',
                                                    ])
                                                    ->prefixIcon('heroicon-s-identification')
                                                    ->maxLength(255),
                                                Select::make('extra_beneficiary_account_bank')
                                                    ->label('Banco')
                                                    ->prefixIcon('heroicon-s-identification')
                                                    ->searchable()
                                                    ->preload()
                                                    ->options([
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
                                                    ->label('Banco del Beneficiario')
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
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Observaciones')
                            ->icon(Heroicon::OutlinedFolderPlus)
                            ->schema([
                                Section::make('OBSERVACIONES')
                                    ->description('Seccion para que el analista documente todo lo relacionado a reunion y contactos con la Agencia de Viajes')
                                    ->icon(Heroicon::OutlinedFolderPlus)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                Repeater::make('observationCommercialStructures')
                                                    ->label('Observaciones')
                                                    ->relationship()
                                                    ->table([
                                                        TableColumn::make('Observacion/Notas'),
                                                        TableColumn::make('Responsable del Registro'),
                                                        TableColumn::make('Fecha del Registro'),
                                                    ])
                                                    ->schema([
                                                        Textarea::make('observation')
                                                            ->label('Observacion')
                                                            ->autosize(),
                                                        TextInput::make('created_by')
                                                            ->label('Responsable')
                                                            ->default(fn (): string => Auth::user()?->name ?? '')
                                                            ->disabled()
                                                            ->dehydrated(),
                                                        TextInput::make('date')
                                                            ->default(now()->format('d/m/Y H:i:s'))
                                                            ->disabled()
                                                            ->dehydrated(),
                                                    ])
                                                    ->deletable(function () {
                                                        $user = auth()->user()->departament;
                                                        if (in_array('SUPERADMIN', $user)) {
                                                            return true;
                                                        }

                                                        return false;
                                                    })
                                                    ->columns(2)
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
