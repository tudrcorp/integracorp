<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\CorporateAllies\Schemas;

use App\Models\City;
use App\Models\Country;
use App\Models\State;
use App\Models\SupplierClasificacion;
use App\Models\SupplierEstatusSistema;
use App\Models\SupplierStatusConvenio;
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
     * @var array<string, string>
     */
    private const COUNTRY_DIAL_CODES = [
        'AFG' => '+93',
        'AGO' => '+244',
        'ALB' => '+355',
        'AND' => '+376',
        'ARE' => '+971',
        'ARG' => '+54',
        'ARM' => '+374',
        'ATG' => '+1',
        'AUS' => '+61',
        'AUT' => '+43',
        'AZE' => '+994',
        'BDI' => '+257',
        'BEL' => '+32',
        'BEN' => '+229',
        'BFA' => '+226',
        'BGD' => '+880',
        'BGR' => '+359',
        'BHR' => '+973',
        'BHS' => '+1',
        'BIH' => '+387',
        'BLR' => '+375',
        'BLZ' => '+501',
        'BOL' => '+591',
        'BRA' => '+55',
        'BRB' => '+1',
        'BRN' => '+673',
        'BTN' => '+975',
        'BWA' => '+267',
        'CAF' => '+236',
        'CAN' => '+1',
        'CHE' => '+41',
        'CHL' => '+56',
        'CHN' => '+86',
        'CMR' => '+237',
        'COD' => '+243',
        'COG' => '+242',
        'COL' => '+57',
        'COM' => '+269',
        'CPV' => '+238',
        'CRI' => '+506',
        'CUB' => '+53',
        'CYP' => '+357',
        'CZE' => '+420',
        'DEU' => '+49',
        'DJI' => '+253',
        'DMA' => '+1',
        'DNK' => '+45',
        'DOM' => '+1',
        'DZA' => '+213',
        'ECU' => '+593',
        'EGY' => '+20',
        'ERI' => '+291',
        'ESP' => '+34',
        'EST' => '+372',
        'ETH' => '+251',
        'FIN' => '+358',
        'FJI' => '+679',
        'FRA' => '+33',
        'FSM' => '+691',
        'GAB' => '+241',
        'GBR' => '+44',
        'GEO' => '+995',
        'GHA' => '+233',
        'GIN' => '+224',
        'GMB' => '+220',
        'GNB' => '+245',
        'GNQ' => '+240',
        'GRC' => '+30',
        'GRD' => '+1',
        'GTM' => '+502',
        'GUY' => '+592',
        'HND' => '+504',
        'HRV' => '+385',
        'HTI' => '+509',
        'HUN' => '+36',
        'IDN' => '+62',
        'IND' => '+91',
        'IRL' => '+353',
        'IRN' => '+98',
        'IRQ' => '+964',
        'ISL' => '+354',
        'ISR' => '+972',
        'ITA' => '+39',
        'JAM' => '+1',
        'JOR' => '+962',
        'JPN' => '+81',
        'KAZ' => '+7',
        'KEN' => '+254',
        'KGZ' => '+996',
        'KHM' => '+855',
        'KIR' => '+686',
        'KNA' => '+1',
        'KOR' => '+82',
        'KWT' => '+965',
        'LAO' => '+856',
        'LBN' => '+961',
        'LBR' => '+231',
        'LBY' => '+218',
        'LCA' => '+1',
        'LIE' => '+423',
        'LKA' => '+94',
        'LSO' => '+266',
        'LTU' => '+370',
        'LUX' => '+352',
        'LVA' => '+371',
        'MAR' => '+212',
        'MCO' => '+377',
        'MDA' => '+373',
        'MDG' => '+261',
        'MDV' => '+960',
        'MEX' => '+52',
        'MHL' => '+692',
        'MKD' => '+389',
        'MLI' => '+223',
        'MLT' => '+356',
        'MMR' => '+95',
        'MNE' => '+382',
        'MNG' => '+976',
        'MOZ' => '+258',
        'MRT' => '+222',
        'MUS' => '+230',
        'MWI' => '+265',
        'MYS' => '+60',
        'NAM' => '+264',
        'NER' => '+227',
        'NGA' => '+234',
        'NIC' => '+505',
        'NOR' => '+47',
        'NPL' => '+977',
        'NRU' => '+674',
        'NZL' => '+64',
        'OMN' => '+968',
        'PAK' => '+92',
        'PAN' => '+507',
        'PER' => '+51',
        'PHL' => '+63',
        'PLW' => '+680',
        'PNG' => '+675',
        'POL' => '+48',
        'PRK' => '+850',
        'PRT' => '+351',
        'PRY' => '+595',
        'PSE' => '+970',
        'QAT' => '+974',
        'ROU' => '+40',
        'RUS' => '+7',
        'RWA' => '+250',
        'SAU' => '+966',
        'SDN' => '+249',
        'SEN' => '+221',
        'SGP' => '+65',
        'SLB' => '+677',
        'SLE' => '+232',
        'SLV' => '+503',
        'SMR' => '+378',
        'SOM' => '+252',
        'SRB' => '+381',
        'SSD' => '+211',
        'STP' => '+239',
        'SUR' => '+597',
        'SVK' => '+421',
        'SVN' => '+386',
        'SWE' => '+46',
        'SWZ' => '+268',
        'SYC' => '+248',
        'SYR' => '+963',
        'TCD' => '+235',
        'TGO' => '+228',
        'THA' => '+66',
        'TJK' => '+992',
        'TKM' => '+993',
        'TLS' => '+670',
        'TON' => '+676',
        'TTO' => '+1',
        'TUN' => '+216',
        'TUR' => '+90',
        'TUV' => '+688',
        'TZA' => '+255',
        'UGA' => '+256',
        'UKR' => '+380',
        'URY' => '+598',
        'USA' => '+1',
        'UZB' => '+998',
        'VAT' => '+379',
        'VCT' => '+1',
        'VEN' => '+58',
        'VNM' => '+84',
        'VUT' => '+678',
        'WSM' => '+685',
        'YEM' => '+967',
        'ZAF' => '+27',
        'ZMB' => '+260',
        'ZWE' => '+263',
    ];

    private static function phoneField(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->prefix(fn (Get $get): string => self::resolveCountryDialCode($get('country_id')) ?? '')
            ->placeholder('Ej: +584141234567')
            ->helperText('Se sugiere el código internacional según el país seleccionado.')
            ->tel()
            ->live(onBlur: true)
            ->afterStateUpdated(function (?string $state, Set $set, Get $get) use ($name): void {
                $dialCode = self::resolveCountryDialCode($get('country_id'));

                if (blank($dialCode) || blank($state)) {
                    return;
                }

                $set($name, self::formatPhoneWithDialCode($state, $dialCode));
            })
            ->maxLength(255)
            ->rules(['nullable', 'regex:/^\+?\d{8,15}$/'])
            ->validationMessages([
                'regex' => 'Debe tener entre 8 y 15 dígitos, con o sin prefijo +.',
            ]);
    }

    private static function resolveCountryDialCode(mixed $countryId): ?string
    {
        if (blank($countryId)) {
            return null;
        }

        $countryCode = Country::query()
            ->whereKey($countryId)
            ->value('code');

        if (blank($countryCode)) {
            return null;
        }

        return self::COUNTRY_DIAL_CODES[strtoupper((string) $countryCode)] ?? null;
    }

    private static function formatPhoneWithDialCode(string $phone, string $dialCode): string
    {
        if (str_starts_with($phone, '+')) {
            return $phone;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        $digits = ltrim($digits, '0');
        $dialDigits = ltrim($dialCode, '+');

        if (blank($digits)) {
            return $dialCode;
        }

        if (str_starts_with($digits, $dialDigits)) {
            return '+'.$digits;
        }

        return $dialCode.$digits;
    }

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
                                                    ->afterStateUpdated(function (Set $set, Get $get): void {
                                                        $set('state_id', null);
                                                        $set('city_id', null);

                                                        $dialCode = self::resolveCountryDialCode($get('country_id'));

                                                        if (blank($dialCode)) {
                                                            return;
                                                        }

                                                        if (blank($get('phone'))) {
                                                            $set('phone', $dialCode);
                                                        }

                                                        if (blank($get('people_contact'))) {
                                                            $set('people_contact', $dialCode);
                                                        }
                                                    })
                                                    ->searchable()
                                                    ->preload()
                                                    ->placeholder('Seleccione un país')
                                                    ->columnSpan(['default' => 1, 'lg' => 2]),
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
                                                    ->placeholder('Seleccione un estado')
                                                    ->disabled(fn (Get $get): bool => blank($get('country_id'))),
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
                                                    ->placeholder('Seleccione una ciudad')
                                                    ->disabled(fn (Get $get): bool => blank($get('country_id')) || blank($get('state_id'))),
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
                            ->icon('heroicon-o-phone')
                            ->schema([
                                Placeholder::make('corporate_ally_contact_intro')
                                    ->hiddenLabel()
                                    ->content(new HtmlString(
                                        '<p class="text-sm leading-relaxed text-gray-600 dark:text-gray-300">'
                                        .'<span class="font-semibold text-gray-900 dark:text-white">Paso 3 — Contacto.</span> '
                                        .'Teléfonos, correo y redes del aliado corporativo.'
                                        .'</p>'
                                    ))
                                    ->columnSpanFull(),

                                Section::make('Datos de contacto')
                                    ->description('Teléfonos, correo y redes del aliado corporativo.')
                                    ->icon('heroicon-o-user-group')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 2])
                                            ->extraAttributes(['class' => self::INNER_CARD])
                                            ->schema([
                                                self::phoneField('phone', 'Teléfono principal'),
                                                self::phoneField('people_contact', 'Teléfono secundario'),
                                                TextInput::make('email')
                                                    ->label('Correo electrónico')
                                                    ->placeholder('contacto@empresa.com')
                                                    ->email()
                                                    ->maxLength(255)
                                                    ->columnSpan(['default' => 1, 'lg' => 2]),
                                                Textarea::make('social_networks')
                                                    ->label('Redes sociales')
                                                    ->placeholder('Instagram, LinkedIn, sitio web…')
                                                    ->rows(3)
                                                    ->columnSpan(['default' => 1, 'lg' => 2]),
                                            ]),
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
