<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\CorporateAllies\Schemas;

use App\Models\City;
use App\Models\Country;
use App\Models\State;
use App\Models\SupplierClasificacion;
use App\Models\SupplierEstatusSistema;
use App\Models\SupplierStatusConvenio;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class CorporateAllyForm
{
    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const INNER_CARD = 'rounded-2xl border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const REPEATER_CARD = 'rounded-2xl border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    /**
     * @return array<string, string>
     */
    private static function venezuelanBanks(): array
    {
        return [
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
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function internationalBanks(): array
    {
        return [
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
            'BANCO NACIONAL DE PANAMÁ (BNP)' => 'BANCO NACIONAL DE PANAMÁ (BNP)',
            'CAJA DE AHORROS' => 'CAJA DE AHORROS',
            'BANCO GENERAL' => 'BANCO GENERAL',
            'GLOBAL BANK' => 'GLOBAL BANK',
            'BANESCO PANAMÁ' => 'BANESCO PANAMÁ',
            'METROBANK' => 'METROBANK',
            'BANCAMIGA' => 'BANCAMIGA',
            'BANCO DEL TESORO' => 'BANCO DEL TESORO',
            'PROVINCIAL' => 'PROVINCIAL',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function localAccountTypes(): array
    {
        return [
            'AHORRO' => 'AHORRO',
            'CORRIENTE' => 'CORRIENTE',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function internationalAccountTypes(): array
    {
        return [
            'CUENTA DE CHEQUES (CHECKING ACCOUNT)' => 'CUENTA DE CHEQUES (CHECKING ACCOUNT)',
            'CUENTA DE AHORROS (SAVINGS ACCOUNT)' => 'CUENTA DE AHORROS (SAVINGS ACCOUNT)',
            'CUENTA CORRIENTE (CURRENT ACCOUNT)' => 'CUENTA CORRIENTE (CURRENT ACCOUNT)',
            'CUENTA DE NEGOCIOS (BUSINESS ACCOUNT)' => 'CUENTA DE NEGOCIOS (BUSINESS ACCOUNT)',
            'CUENTA EN MONEDA EXTRANJERA (DOLARES, EUROS, ETC.)' => 'CUENTA EN MONEDA EXTRANJERA (DOLARES, EUROS, ETC.)',
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('corporateAllyFormTabs')
                    ->columnSpanFull()
                    ->persistTabInQueryString()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Datos principales')
                            ->icon('heroicon-o-building-office-2')
                            ->schema([
                                Placeholder::make('corporate_ally_form_intro')
                                    ->hiddenLabel()
                                    ->content(new HtmlString(
                                        '<p class="text-sm leading-relaxed text-gray-600 dark:text-gray-300">'
                                        .'<span class="font-semibold text-gray-900 dark:text-white">Paso 1 — Datos principales.</span> '
                                        .'Registra razón social, RIF y clasificación del aliado corporativo.'
                                        .'</p>'
                                    ))
                                    ->columnSpanFull(),

                                Section::make('Identificación y razón social')
                                    ->description('Nombre comercial, fiscal y datos legales básicos.')
                                    ->icon('heroicon-o-identification')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 2])
                                            ->extraAttributes(['class' => self::INNER_CARD])
                                            ->schema([
                                                TextInput::make('company_name')
                                                    ->label('Razón social')
                                                    ->placeholder('Ej: EMPRESA ALIADA C.A.')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->columnSpan(['default' => 1, 'lg' => 2])
                                                    ->afterStateUpdatedJs(<<<'JS'
                                                    $set('company_name', $state.toUpperCase());
                                                JS),
                                                TextInput::make('rif')
                                                    ->label('RIF')
                                                    ->placeholder('J-123456789')
                                                    ->required()
                                                    ->mask('J999999999999')
                                                    ->afterStateUpdatedJs(<<<'JS'
                                                    $set('rif', $state.toUpperCase());
                                                JS),
                                            ]),
                                    ])
                                    ->collapsible(),

                                Section::make('Clasificación y convenio')
                                    ->description('Tipo de relación contractual y categoría del aliado en red.')
                                    ->icon('heroicon-o-tag')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 2])
                                            ->extraAttributes(['class' => self::INNER_CARD])
                                            ->schema([
                                                Select::make('supplier_category')
                                                    ->label('Categoría del proveedor')
                                                    ->options(SupplierClasificacion::query()->orderBy('description')->pluck('description', 'description'))
                                                    ->searchable()
                                                    ->preload(),
                                                Select::make('type_agreement')
                                                    ->label('Tipo de convenio')
                                                    ->options(SupplierStatusConvenio::query()->orderBy('description')->pluck('description', 'description'))
                                                    ->searchable()
                                                    ->preload(),
                                                Select::make('status_agreement')
                                                    ->label('Estatus del convenio')
                                                    ->options(SupplierEstatusSistema::query()->orderBy('description')->pluck('description', 'description'))
                                                    ->searchable()
                                                    ->preload(),
                                                Select::make('status')
                                                    ->label('Estatus en sistema')
                                                    ->options(SupplierEstatusSistema::query()->orderBy('description')->pluck('description', 'description'))
                                                    ->searchable()
                                                    ->preload(),
                                            ]),
                                    ])
                                    ->collapsible(),
                            ]),

                        Tab::make('Ubicación')
                            ->icon('heroicon-o-map-pin')
                            ->schema([
                                Placeholder::make('corporate_ally_location_intro')
                                    ->hiddenLabel()
                                    ->content(new HtmlString(
                                        '<p class="text-sm leading-relaxed text-gray-600 dark:text-gray-300">'
                                        .'<span class="font-semibold text-gray-900 dark:text-white">Paso 2 — Ubicación.</span> '
                                        .'Indica país, estado, ciudad y dirección de la sede principal.'
                                        .'</p>'
                                    ))
                                    ->columnSpanFull(),

                                Section::make('Ubicación principal')
                                    ->description('País, estado, ciudad y dirección de la sede principal.')
                                    ->icon('heroicon-o-home-modern')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 2])
                                            ->extraAttributes(['class' => self::INNER_CARD])
                                            ->schema([
                                                Select::make('country_id')
                                                    ->label('País')
                                                    ->options(fn (): array => Country::query()->orderBy('name')->pluck('name', 'id')->all())
                                                    ->live()
                                                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                                                        $set('state_id', null);
                                                        $set('city_id', null);
                                                        $set(
                                                            'country_code',
                                                            filled($state)
                                                                ? (Country::query()->whereKey($state)->value('code') ?? null)
                                                                : null,
                                                        );
                                                    })
                                                    ->searchable()
                                                    ->preload()
                                                    ->required()
                                                    ->placeholder('Seleccione un país')
                                                    ->columnSpan(['default' => 1, 'lg' => 2]),
                                                Hidden::make('country_code')
                                                    ->required()
                                                    ->dehydrated(),
                                                Select::make('state_id')
                                                    ->label('Estado')
                                                    ->options(fn (Get $get): array => State::query()
                                                        ->where('country_id', $get('country_id'))
                                                        ->orderBy('definition')
                                                        ->pluck('definition', 'id')
                                                        ->all())
                                                    ->live()
                                                    ->afterStateUpdated(fn (Set $set): mixed => $set('city_id', null))
                                                    ->searchable()
                                                    ->preload()
                                                    ->required()
                                                    ->placeholder('Seleccione un estado')
                                                    ->disabled(fn (Get $get): bool => blank($get('country_id')))
                                                    ->dehydrated(),
                                                Select::make('city_id')
                                                    ->label('Ciudad')
                                                    ->options(fn (Get $get): array => City::query()
                                                        ->where('state_id', $get('state_id'))
                                                        ->when(
                                                            filled($get('country_id')),
                                                            fn ($query) => $query->where('country_id', $get('country_id')),
                                                        )
                                                        ->orderBy('definition')
                                                        ->pluck('definition', 'id')
                                                        ->all())
                                                    ->searchable()
                                                    ->preload()
                                                    ->required()
                                                    ->placeholder('Seleccione una ciudad')
                                                    ->disabled(fn (Get $get): bool => blank($get('country_id')) || blank($get('state_id')))
                                                    ->dehydrated(),
                                                Textarea::make('address')
                                                    ->label('Dirección')
                                                    ->placeholder('Av., urbanización, punto de referencia')
                                                    ->rows(3)
                                                    ->columnSpan(['default' => 1, 'lg' => 2])
                                                    ->afterStateUpdatedJs(<<<'JS'
                                                    $set('address', $state.toUpperCase());
                                                JS),
                                            ]),
                                    ])
                                    ->collapsible(),
                            ]),

                        Tab::make('Contacto')
                            ->icon('heroicon-o-user-group')
                            ->schema([
                                Placeholder::make('corporate_ally_contact_intro')
                                    ->hiddenLabel()
                                    ->content(new HtmlString(
                                        '<p class="text-sm leading-relaxed text-gray-600 dark:text-gray-300">'
                                        .'<span class="font-semibold text-gray-900 dark:text-white">Paso 3 — Contacto.</span> '
                                        .'Personas de contacto y redes sociales del aliado corporativo.'
                                        .'</p>'
                                    ))
                                    ->columnSpanFull(),

                                Section::make('Contactos principales')
                                    ->description('Personas de contacto registradas para este aliado corporativo.')
                                    ->icon('heroicon-o-users')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Repeater::make('corporateAllyContactPrincipals')
                                            ->label('Contactos principales')
                                            ->relationship()
                                            ->extraAttributes([
                                                'class' => self::REPEATER_CARD,
                                            ])
                                            ->table([
                                                TableColumn::make('Departamento'),
                                                TableColumn::make('Cargo'),
                                                TableColumn::make('Nombre y apellido'),
                                                TableColumn::make('Correo'),
                                                TableColumn::make('Celular'),
                                                TableColumn::make('Teléfono local'),
                                                TableColumn::make('Extensión'),
                                            ])
                                            ->schema([
                                                TextInput::make('departament')
                                                    ->label('Departamento')
                                                    ->placeholder('Ej: FACTURACIÓN')
                                                    ->afterStateUpdatedJs(<<<'JS'
                                                        $set('departament', $state.toUpperCase());
                                                    JS),
                                                TextInput::make('position')
                                                    ->label('Cargo')
                                                    ->placeholder('Ej: COORDINADOR')
                                                    ->afterStateUpdatedJs(<<<'JS'
                                                        $set('position', $state.toUpperCase());
                                                    JS),
                                                TextInput::make('name')
                                                    ->label('Nombre y apellido')
                                                    ->afterStateUpdatedJs(<<<'JS'
                                                        $set('name', $state.toUpperCase());
                                                    JS),
                                                TextInput::make('email')
                                                    ->label('Correo')
                                                    ->email(),
                                                TextInput::make('personal_phone')
                                                    ->label('Teléfono celular')
                                                    ->placeholder('04141234567')
                                                    ->helperText('11 dígitos, sin espacios ni guiones.')
                                                    ->mask('99999999999')
                                                    ->tel()
                                                    ->maxLength(255)
                                                    ->rules(['nullable', 'regex:/^\d{11}$/'])
                                                    ->validationMessages([
                                                        'regex' => 'Debe tener exactamente 11 dígitos, sin espacios ni guiones.',
                                                    ]),
                                                TextInput::make('local_phone')
                                                    ->label('Teléfono local')
                                                    ->placeholder('02121234567')
                                                    ->helperText('11 dígitos, sin espacios ni guiones.')
                                                    ->mask('99999999999')
                                                    ->tel()
                                                    ->maxLength(255)
                                                    ->rules(['nullable', 'regex:/^\d{11}$/'])
                                                    ->validationMessages([
                                                        'regex' => 'Debe tener exactamente 11 dígitos, sin espacios ni guiones.',
                                                    ]),
                                                TextInput::make('extensions')
                                                    ->label('Extensión(es)')
                                                    ->placeholder('Ej: 101, 102'),
                                                Hidden::make('created_by')->default(fn (): string => Auth::user()?->name ?? ''),
                                                Hidden::make('updated_by')
                                                    ->default(fn (): string => Auth::user()?->name ?? '')
                                                    ->hiddenOn('create'),
                                            ])
                                            ->addActionLabel('Agregar contacto')
                                            ->columnSpanFull()
                                            ->defaultItems(0)
                                            ->collapsed()
                                            ->reorderable(),
                                    ])
                                    ->collapsible(),

                                Section::make('Redes sociales')
                                    ->description('Perfiles y enlaces públicos del aliado corporativo.')
                                    ->icon('heroicon-o-share')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Textarea::make('social_networks')
                                            ->label('Redes sociales')
                                            ->placeholder('Instagram, LinkedIn, sitio web…')
                                            ->rows(3)
                                            ->extraAttributes(['class' => self::INNER_CARD])
                                            ->columnSpanFull(),
                                    ])
                                    ->collapsible(),
                            ]),

                        Tab::make('Servicios y condiciones')
                            ->icon('heroicon-o-briefcase')
                            ->schema([
                                Placeholder::make('corporate_ally_services_intro')
                                    ->hiddenLabel()
                                    ->content(new HtmlString(
                                        '<p class="text-sm leading-relaxed text-gray-600 dark:text-gray-300">'
                                        .'<span class="font-semibold text-gray-900 dark:text-white">Paso 4 — Servicios y condiciones.</span> '
                                        .'Describe los servicios del aliado y las condiciones comerciales de pago.'
                                        .'</p>'
                                    ))
                                    ->columnSpanFull(),

                                Section::make('Servicios ofrecidos')
                                    ->description('Descripción de los servicios que presta el aliado.')
                                    ->icon('heroicon-o-clipboard-document-list')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Textarea::make('services')
                                            ->label('Servicios')
                                            ->placeholder('Liste los servicios o beneficios del aliado')
                                            ->rows(4)
                                            ->extraAttributes(['class' => self::INNER_CARD])
                                            ->columnSpanFull()
                                            ->afterStateUpdatedJs(<<<'JS'
                                            $set('services', $state.toUpperCase());
                                        JS),
                                    ])
                                    ->collapsible(),

                                Section::make('Condiciones comerciales')
                                    ->description('Plazo y forma de pago acordados.')
                                    ->icon('heroicon-o-banknotes')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 2])
                                            ->extraAttributes(['class' => self::INNER_CARD])
                                            ->schema([
                                                Select::make('supplier_payment')
                                                    ->label('Forma de pago del proveedor')
                                                    ->options([
                                                        'CONTADO' => 'Contado',
                                                        'CREDITO' => 'Crédito',
                                                    ])
                                                    ->live()
                                                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                                                        if (strtoupper((string) $state) !== 'CREDITO') {
                                                            $set('payment_term', null);
                                                        }
                                                    })
                                                    ->searchable()
                                                    ->preload()
                                                    ->placeholder('Seleccione forma de pago'),
                                                Select::make('payment_term')
                                                    ->label('Plazo de pago')
                                                    ->options([
                                                        '3 DIAS' => '3 días',
                                                        '5 DIAS' => '5 días',
                                                        '7 DIAS' => '7 días',
                                                        '10 DIAS' => '10 días',
                                                        '15 DIAS' => '15 días',
                                                        '20 DIAS' => '20 días',
                                                        '25 DIAS' => '25 días',
                                                        '30 DIAS' => '30 días',
                                                    ])
                                                    ->searchable()
                                                    ->preload()
                                                    ->placeholder('Seleccione plazo de pago')
                                                    ->visible(fn (Get $get): bool => strtoupper((string) $get('supplier_payment')) === 'CREDITO')
                                                    ->required(fn (Get $get): bool => strtoupper((string) $get('supplier_payment')) === 'CREDITO'),
                                            ]),
                                    ])
                                    ->collapsible(),
                            ]),

                        Tab::make('Datos bancarios')
                            ->icon('heroicon-o-building-library')
                            ->schema([
                                Placeholder::make('corporate_ally_banking_intro')
                                    ->hiddenLabel()
                                    ->content(new HtmlString(
                                        '<p class="text-sm leading-relaxed text-gray-600 dark:text-gray-300">'
                                        .'<span class="font-semibold text-gray-900 dark:text-white">Paso 5 — Datos bancarios.</span> '
                                        .'Cuentas nacionales, internacionales y Zelle para pagos al aliado.'
                                        .'</p>'
                                    ))
                                    ->columnSpanFull(),

                                Section::make('Banca en moneda local')
                                    ->description('Beneficiario, cuenta y pago móvil.')
                                    ->icon('heroicon-o-credit-card')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 3])
                                            ->extraAttributes(['class' => self::INNER_CARD])
                                            ->schema([
                                                TextInput::make('local_beneficiary_name')
                                                    ->label('Beneficiario')
                                                    ->maxLength(255)
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdatedJs(<<<'JS'
                                                    $set('local_beneficiary_name', $state.toUpperCase());
                                                JS),
                                                TextInput::make('local_beneficiary_rif')
                                                    ->label('R.I.F.')
                                                    ->maxLength(255),
                                                TextInput::make('local_beneficiary_account_number')
                                                    ->label('N° cuenta')
                                                    ->maxLength(255),
                                                Select::make('local_beneficiary_account_bank')
                                                    ->label('Banco')
                                                    ->options(self::venezuelanBanks())
                                                    ->searchable()
                                                    ->preload(),
                                                Select::make('local_beneficiary_account_type')
                                                    ->label('Tipo de cuenta')
                                                    ->options(self::localAccountTypes())
                                                    ->searchable()
                                                    ->preload(),
                                                TextInput::make('local_beneficiary_phone_pm')
                                                    ->label('Pago móvil')
                                                    ->tel()
                                                    ->mask('09999999999')
                                                    ->helperText('Formato: 04121234567')
                                                    ->maxLength(255),
                                            ]),
                                        Fieldset::make('Cuenta en moneda extranjera (banca local)')
                                            ->extraAttributes(['class' => self::INNER_CARD])
                                            ->schema([
                                                TextInput::make('local_beneficiary_account_number_mon_inter')
                                                    ->label('Cuenta moneda extranjera (local)')
                                                    ->maxLength(255),
                                                Select::make('local_beneficiary_account_bank_mon_inter')
                                                    ->label('Banco (inter.)')
                                                    ->options(self::venezuelanBanks())
                                                    ->searchable()
                                                    ->preload(),
                                                Select::make('local_beneficiary_account_type_mon_inter')
                                                    ->label('Tipo (inter.)')
                                                    ->options(self::localAccountTypes())
                                                    ->searchable()
                                                    ->preload(),
                                            ])
                                            ->columns(3),
                                    ])
                                    ->collapsible(),

                                Section::make('Banca en moneda extranjera')
                                    ->description('Cuenta internacional, Zelle y datos SWIFT / ACH.')
                                    ->icon('heroicon-o-globe-alt')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 5])
                                            ->extraAttributes(['class' => self::INNER_CARD])
                                            ->schema([
                                                TextInput::make('extra_beneficiary_name')
                                                    ->label('Beneficiario')
                                                    ->maxLength(255)
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdatedJs(<<<'JS'
                                                    $set('extra_beneficiary_name', $state.toUpperCase());
                                                JS),
                                                TextInput::make('extra_beneficiary_ci_rif')
                                                    ->label('CI / RIF')
                                                    ->maxLength(255),
                                                TextInput::make('extra_beneficiary_account_number')
                                                    ->label('N° cuenta')
                                                    ->maxLength(255),
                                                Select::make('extra_beneficiary_account_bank')
                                                    ->label('Banco')
                                                    ->options(self::internationalBanks())
                                                    ->searchable()
                                                    ->preload(),
                                                Select::make('extra_beneficiary_account_type')
                                                    ->label('Tipo de cuenta')
                                                    ->options(self::internationalAccountTypes())
                                                    ->searchable()
                                                    ->preload(),
                                                TextInput::make('extra_beneficiary_route')
                                                    ->label('Routing')
                                                    ->maxLength(255)
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdatedJs(<<<'JS'
                                                    $set('extra_beneficiary_route', $state.toUpperCase());
                                                JS),
                                                TextInput::make('extra_beneficiary_zelle')
                                                    ->label('Zelle')
                                                    ->maxLength(255)
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdatedJs(<<<'JS'
                                                    $set('extra_beneficiary_zelle', $state.toUpperCase());
                                                JS),
                                                TextInput::make('extra_beneficiary_ach')
                                                    ->label('ACH')
                                                    ->maxLength(255)
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdatedJs(<<<'JS'
                                                    $set('extra_beneficiary_ach', $state.toUpperCase());
                                                JS),
                                                TextInput::make('extra_beneficiary_swift')
                                                    ->label('SWIFT')
                                                    ->maxLength(255)
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdatedJs(<<<'JS'
                                                    $set('extra_beneficiary_swift', $state.toUpperCase());
                                                JS),
                                                TextInput::make('extra_beneficiary_aba')
                                                    ->label('ABA')
                                                    ->maxLength(255)
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdatedJs(<<<'JS'
                                                    $set('extra_beneficiary_aba', $state.toUpperCase());
                                                JS),
                                                TextInput::make('extra_beneficiary_address')
                                                    ->label('Dirección')
                                                    ->maxLength(255)
                                                    ->columnSpan(['default' => 1, 'lg' => 5])
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdatedJs(<<<'JS'
                                                    $set('extra_beneficiary_address', $state.toUpperCase());
                                                JS),
                                            ]),
                                    ])
                                    ->collapsible(),
                            ]),

                        Tab::make('Notas')
                            ->icon('heroicon-o-chat-bubble-left-right')
                            ->schema([
                                Placeholder::make('corporate_ally_notes_intro')
                                    ->hiddenLabel()
                                    ->content(new HtmlString(
                                        '<p class="text-sm leading-relaxed text-gray-600 dark:text-gray-300">'
                                        .'<span class="font-semibold text-gray-900 dark:text-white">Paso 6 — Notas y observaciones.</span> '
                                        .'Bitácora interna de seguimiento operativo del aliado corporativo.'
                                        .'</p>'
                                    ))
                                    ->columnSpanFull(),

                                Section::make('Bitácora')
                                    ->description('Observaciones internas sobre el aliado corporativo.')
                                    ->icon('heroicon-o-clipboard-document-list')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Repeater::make('corporateAllyObservacions')
                                            ->label('Notas y observaciones')
                                            ->relationship()
                                            ->extraAttributes(['class' => self::REPEATER_CARD])
                                            ->table([
                                                TableColumn::make('Nota')->width('90%'),
                                                TableColumn::make('Responsable')->width('10%'),
                                            ])
                                            ->schema([
                                                Textarea::make('observation')
                                                    ->label('Nota')
                                                    ->autosize()
                                                    ->required(),
                                                TextInput::make('created_by')
                                                    ->label('Responsable')
                                                    ->disabled()
                                                    ->dehydrated()
                                                    ->default(fn (): string => Auth::user()?->name ?? ''),
                                            ])
                                            ->addActionLabel('Agregar nota')
                                            ->columnSpanFull()
                                            ->defaultItems(0)
                                            ->collapsed()
                                            ->reorderable(),
                                    ])
                                    ->collapsible(),
                            ]),
                    ]),
            ]);
    }
}
