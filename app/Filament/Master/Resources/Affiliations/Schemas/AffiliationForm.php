<?php

namespace App\Filament\Master\Resources\Affiliations\Schemas;

use App\Models\City;
use App\Models\State;
use App\Models\Region;
use App\Models\Country;
use App\Models\Affiliation;
use Filament\Schemas\Schema;
use App\Models\IndividualQuote;
use Filament\Forms\Components\Radio;
use Illuminate\Support\Facades\Auth;
use App\Models\DetailIndividualQuote;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class AffiliationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
            Section::make('PRE-AFILIACIÓN')
                ->collapsible(false)
                ->collapsed('edit')
                ->description('Formulario de afiliación. Campo Requerido(*)')
                ->icon('heroicon-s-building-library')
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('code')
                            ->label('Codigo de afiliacion')
                            ->prefixIcon('heroicon-m-clipboard-document-check')
                            ->disabled()
                            ->dehydrated()
                            ->maxLength(255)
                            ->default(function () {
                                if (Affiliation::max('id') == null) {
                                    $parte_entera = 0;
                                } else {
                                    $parte_entera = Affiliation::max('id');
                                }
                                return 'TDEC-AFI-000' . $parte_entera + 1;
                            })
                            ->required(),

                    ]),
                    Select::make('individual_quote_id')
                        ->default(fn() => request()->query('id'))
                        ->label('Código de cotización')
                        ->live()
                        ->prefixIcon('heroicon-m-clipboard-document-check')
                        ->options(function () {
                            return IndividualQuote::select('id', 'status', 'full_name', 'owner_code')
                                ->where('owner_code', Auth::user()->code_agency)
                                ->where('status', 'APROBADA')
                                ->pluck('full_name', 'id');
                        })
                        ->searchable()
                        ->preload()
                        // ->afterStateUpdated(function (Set $set, $state) {
                        //     $code = IndividualQuote::select('code', 'id')->where('id', $state)->first()->code;
                        //     $set('code_individual_quote', $code);
                        // })
                        ->required()
                        ->validationMessages([
                            'required'  => 'Campo Requerido',
                        ]),
                    Select::make('plan_id')
                        ->default(function () {
                            $plan_id = request()->query('plan_id');
                            if (isset($plan_id)) {
                                return $plan_id;
                            }
                            return null;
                        })
                        ->label('Plan')
                        ->live()
                        ->searchable()
                        ->preload()
                        ->prefixIcon('heroicon-m-clipboard-document-check')
                        ->options(function (Get $get) {
                            $plans = DetailIndividualQuote::join('plans', 'detail_individual_quotes.plan_id', '=', 'plans.id')
                                ->join('individual_quotes', 'detail_individual_quotes.individual_quote_id', '=', 'individual_quotes.id')
                                ->where('individual_quotes.id', $get('individual_quote_id'))
                                ->select('plans.id as plan_id', 'plans.description as description')
                                ->distinct() // Asegurarse de que no haya duplicados
                                ->get()
                                ->pluck('description', 'plan_id');

                            return $plans;
                        })
                        ->required()
                        ->validationMessages([
                            'required'  => 'Campo Requerido',
                        ]),
                    Select::make('coverage_id')
                        ->label('Cobertura(s) cotizadas')
                        ->live()
                        ->options(function (Get $get) {
                            $coverages = DetailIndividualQuote::join('coverages', 'detail_individual_quotes.coverage_id', '=', 'coverages.id')
                                ->join('individual_quotes', 'detail_individual_quotes.individual_quote_id', '=', 'individual_quotes.id')
                                ->where('individual_quotes.id', $get('individual_quote_id'))
                                ->where('detail_individual_quotes.plan_id', $get('plan_id'))
                                ->select('coverages.id as coverage_id', 'coverages.price as description')
                                ->distinct() // Asegurarse de que no haya duplicados
                                ->get()
                                ->pluck('description', 'coverage_id');

                            return $coverages;
                        })
                        ->searchable()
                        ->prefixIcon('heroicon-s-globe-europe-africa')
                        ->preload(),
                    Select::make('payment_frequency')
                        ->label('Frecuencia de pago')
                        ->live()
                        ->options([
                            'ANUAL'      => 'ANUAL',
                            'TRIMESTRAL' => 'TRIMESTRAL',
                            'SEMESTRAL'  => 'SEMESTRAL'
                        ])
                        ->searchable()
                        ->live()
                        ->prefixIcon('heroicon-s-globe-europe-africa')
                        ->required()
                        ->validationMessages([
                            'required'  => 'Campo Requerido',
                        ])
                        ->preload()
                        ->afterStateUpdated(function ($state, $set, Get $get) {
                            if ($get('payment_frequency') == 'ANUAL') {
                                //busco el valor de la cotizacion de acuerdo al plan y a la covertura
                                $data_quote = DetailIndividualQuote::select('individual_quote_id', 'plan_id', 'coverage_id', 'subtotal_anual')
                                    ->where('individual_quote_id', $get('individual_quote_id'))
                                    ->where('plan_id', $get('plan_id'))
                                    ->when($get('plan_id') != 1, function ($query) use ($get) {
                                        return $query->where('coverage_id', $get('coverage_id'));
                                    })
                                    // ->where('coverage_id', $get('coverage_id'))
                                    ->get();

                                $set('total_amount', $data_quote->sum('subtotal_anual'));
                            }
                            if ($get('payment_frequency') == 'TRIMESTRAL') {

                                $data_quote = DetailIndividualQuote::select('individual_quote_id', 'plan_id', 'coverage_id', 'subtotal_quarterly')
                                    ->where('individual_quote_id', $get('individual_quote_id'))
                                    ->where('plan_id', $get('plan_id'))
                                    ->when($get('plan_id') != 1, function ($query) use ($get) {
                                        return $query->where('coverage_id', $get('coverage_id'));
                                    })
                                    ->get();

                                $set('total_amount', $data_quote->sum('subtotal_quarterly'));
                            }
                            if ($get('payment_frequency') == 'SEMESTRAL') {

                                $data_quote = DetailIndividualQuote::select('individual_quote_id', 'plan_id', 'coverage_id', 'subtotal_biannual')
                                    ->where('individual_quote_id', $get('individual_quote_id'))
                                    ->where('plan_id', $get('plan_id'))
                                    ->when($get('plan_id') != 1, function ($query) use ($get) {
                                        return $query->where('coverage_id', $get('coverage_id'));
                                    })
                                    ->get();

                                $set('total_amount', $data_quote->sum('subtotal_biannual'));
                            }

                            $fee_anual = DetailIndividualQuote::select('individual_quote_id', 'plan_id', 'coverage_id', 'subtotal_anual')
                                ->where('individual_quote_id', $get('individual_quote_id'))
                                ->where('plan_id', $get('plan_id'))
                                ->when($get('plan_id') != 1, function ($query) use ($get) {
                                    return $query->where('coverage_id', $get('coverage_id'));
                                })
                                ->get();

                            $set('fee_anual', $fee_anual->sum('subtotal_anual'));
                        }),
                    TextInput::make('fee_anual')
                        ->label('Tarifa anual')
                        ->prefix('US$')
                        ->numeric()
                        ->disabled()
                        ->dehydrated()
                        ->live(),
                    TextInput::make('total_amount')
                        ->label('Total a pagar')
                        ->helperText('Punto(.) para separar decimales')
                        ->prefix('US$')
                        ->numeric()
                        ->disabled()
                        ->dehydrated()
                        ->live(),

                    /**
                     * Campos referenciales para jerarquia
                     * -----------------------------------------------------------------
                     */
                    Hidden::make('status')->default('PRE-APROBADA'),
                    Hidden::make('created_by')->default(Auth::user()->name),
                    Hidden::make('code_agency')->default(Auth::user()->code_agency),
                    Hidden::make('owner_code')->default(Auth::user()->code_agency),

                ])->columns(3),
            Section::make('INFORMACION PRINCIPAL DEL CONTRATANTE')
                ->collapsible()
                ->collapsed('edit')
                ->description('Campo Requerido(*)')
                ->icon('heroicon-s-building-office-2')
                ->schema([
                    TextInput::make('full_name_con')
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
                    TextInput::make('nro_identificacion_con')
                        ->label('Nro. de identidad')
                        ->prefix('V/E/C')
                        ->numeric()
                        ->unique(
                            ignoreRecord: true,
                            table: 'affiliations',
                            column: 'nro_identificacion_con',
                        )
                        ->validationMessages([
                            'unique'    => 'El RIF ya se encuentra registrado.',
                            'numeric'   => 'El campo es numerico',
                        ])
                        ->required(),

                    Select::make('sex_con')
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

                    DatePicker::make('birth_date_con')
                        ->label('Fecha de Nacimiento')
                        ->prefixIcon('heroicon-m-calendar-days')
                        ->displayFormat('d/m/Y')
                        ->required()
                        ->validationMessages([
                            'required'  => 'Campo Requerido',
                        ]),
                    TextInput::make('email_con')
                        ->label('Email')
                        ->prefixIcon('heroicon-s-at-symbol')
                        ->email()
                        ->required()
                        ->unique(
                            ignoreRecord: true,
                            table: 'affiliations',
                            column: 'email_con',
                        )
                        ->validationMessages([
                            'unique'    => 'El Email Corporativo ya se encuentra registrado.',
                            'required'  => 'Campo requerido',
                            'email'     => 'El campo es un email',
                        ])
                        ->maxLength(255),
                    TextInput::make('adress_con')
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
                    Select::make('country_code_con')
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
                        ->hiddenOn('edit')
                        ->default('+58')
                        ->live(onBlur: true),
                    TextInput::make('phone_con')
                        ->prefixIcon('heroicon-s-phone')
                        ->tel()
                        ->label('Número de teléfono')
                        ->required()
                        ->validationMessages([
                            'required'  => 'Campo Requerido',
                        ])
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, callable $set, Get $get) {
                            $countryCode = $get('country_code_con');
                            if ($countryCode) {
                                $cleanNumber = ltrim(preg_replace('/[^0-9]/', '', $state), '0');
                                $set('phone_con', $countryCode . $cleanNumber);
                            }
                        }),
                    Select::make('country_id_con')
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
                    Select::make('state_id_con')
                        ->label('Estado')
                        ->options(function (Get $get) {
                            return State::where('country_id', $get('country_id_con'))->pluck('definition', 'id');
                        })
                        ->afterStateUpdated(function (Set $set, $state) {
                            $region_id = State::where('id', $state)->value('region_id');
                            $region = Region::where('id', $region_id)->value('definition');
                            $set('region_con', $region);
                        })
                        ->live()
                        ->searchable()
                        ->prefixIcon('heroicon-s-globe-europe-africa')
                        ->required()
                        ->validationMessages([
                            'required'  => 'Campo Requerido',
                        ])
                        ->preload(),
                    TextInput::make('region_con')
                        ->label('Región')
                        ->prefixIcon('heroicon-m-map')
                        ->disabled()
                        ->dehydrated()
                        ->maxLength(255),
                    Select::make('city_id_con')
                        ->label('Ciudad')
                        ->options(function (Get $get) {
                            return City::where('country_id', $get('country_id_con'))->where('state_id', $get('state_id_con'))->pluck('definition', 'id');
                        })
                        ->searchable()
                        ->prefixIcon('heroicon-s-globe-europe-africa')
                        ->required()
                        ->validationMessages([
                            'required'  => 'Campo Requerido',
                        ])
                        ->preload(),
                ])->columns(4),
            Section::make('INFORMACION PRINCIPAL DEL TITULAR')
                ->collapsible()
                ->collapsed('edit')
                ->description('Campo Requerido(*)')
                ->icon('heroicon-s-building-office-2')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            Radio::make('feedback')
                                ->label('Si el CONTRATANTE es el mismo TITULAR Indicar:')
                                ->default(true)
                                ->live()
                                ->boolean()
                                ->inline()
                                ->inlineLabel(false)
                        ])->hiddenOn('edit'),
                    Grid::make(4)
                        ->schema([
                            TextInput::make('full_name_ti')
                                ->label('Nombre y Apellido')
                                ->afterStateUpdated(function (Set $set, $state) {
                                    $set('name', strtoupper($state));
                                })
                                ->live(onBlur: true)
                                ->prefixIcon('heroicon-s-identification')

                                ->validationMessages([
                                    'required' => 'Campo requerido',
                                ])
                                ->maxLength(255),
                            TextInput::make('nro_identificacion_ti')
                                ->label('Nro. de identidad')
                                ->prefix('V/E/C')
                                ->numeric()
                                ->unique(
                                    ignoreRecord: true,
                                    table: 'affiliations',
                                    column: 'nro_identificacion_ti',
                                )
                                ->validationMessages([
                                    'numeric'   => 'El campo es numerico',
                                ])
                                ->required(),

                            Select::make('sex_ti')
                                ->label('Sexo')
                                ->live()
                                ->options([
                                    'MASCULINO' => 'MASCULINO',
                                    'FEMENINO' => 'FEMENINO',
                                ])
                                ->searchable()
                                ->prefixIcon('heroicon-s-globe-europe-africa')
                                ->preload(),

                            DatePicker::make('birth_date_ti')
                                ->label('Fecha de Nacimiento')
                                ->prefixIcon('heroicon-m-calendar-days')
                                ->displayFormat('d/m/Y'),
                            TextInput::make('email_ti')
                                ->label('Email')
                                ->prefixIcon('heroicon-s-at-symbol')
                                ->email()
                                ->unique(
                                    ignoreRecord: true,
                                    table: 'affiliations',
                                    column: 'email_ti',
                                )
                                ->validationMessages([
                                    'unique'    => 'El Email Corporativo ya se encuentra registrado.',
                                    'email'     => 'El campo es un email',
                                ])
                                ->maxLength(255),
                            TextInput::make('adress_ti')
                                ->label('Dirección')
                                ->afterStateUpdated(function (Set $set, $state) {
                                    $set('address', strtoupper($state));
                                })
                                ->live(onBlur: true)
                                ->prefixIcon('heroicon-s-identification')
                                ->maxLength(255),
                            Select::make('country_code_ti')
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
                                ->hiddenOn('edit')
                                ->live(onBlur: true),
                            TextInput::make('phone_ti')
                                ->prefixIcon('heroicon-s-phone')
                                ->tel()
                                ->label('Número de teléfono')
                                ->live(onBlur: true)
                                ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                    $countryCode = $get('country_code_ti');
                                    if ($countryCode) {
                                        $cleanNumber = ltrim(preg_replace('/[^0-9]/', '', $state), '0');
                                        $set('phone_ti', $countryCode . $cleanNumber);
                                    }
                                }),
                            Select::make('country_id_ti')
                                ->label('País')
                                ->live()
                                ->options(Country::all()->pluck('name', 'id'))
                                ->searchable()
                                ->prefixIcon('heroicon-s-globe-europe-africa')
                                ->preload(),
                            Select::make('state_id_ti')
                                ->label('Estado')
                                ->options(function (Get $get) {
                                    return State::where('country_id', $get('country_id_ti'))->pluck('definition', 'id');
                                })
                                ->afterStateUpdated(function (Set $set, $state) {
                                    $region_id = State::where('id', $state)->value('region_id');
                                    $region = Region::where('id', $region_id)->value('definition');
                                    $set('region_ti', $region);
                                })
                                ->live()
                                ->searchable()
                                ->prefixIcon('heroicon-s-globe-europe-africa')
                                ->preload(),
                            TextInput::make('region_ti')
                                ->label('Región')
                                ->prefixIcon('heroicon-m-map')
                                ->disabled()
                                ->dehydrated()
                                ->maxLength(255),
                            Select::make('city_id_ti')
                                ->label('Ciudad')
                                ->options(function (Get $get) {
                                    return City::where('country_id', $get('country_id_ti'))->where('state_id', $get('state_id_ti'))->pluck('definition', 'id');
                                })
                                ->searchable()
                                ->prefixIcon('heroicon-s-globe-europe-africa')
                                ->preload(),
                        ])->hidden(fn(Get $get) => $get('feedback')),

                ])->columns(4),

            Section::make('AFILIADOS')
                ->description('Campo Requerido(*)')
                ->collapsed()
                ->icon('heroicon-s-building-office-2')
                ->hiddenOn('edit')
                ->schema([
                    Repeater::make('affiliates')
                        ->label('afiliados')
                        // ->headers([
                        //     Header::make('Nombre completo'),
                        //     Header::make('C.I.'),
                        //     Header::make('Sexo'),
                        //     Header::make('Fecha de nacimiento'),
                        //     // Header::make('Estatura'),
                        //     // Header::make('Peso'),
                        //     Header::make('Parentesco'),
                        // ])
                        // ->renderHeader(false)
                        // ->showLabels()
                        // ->stackAt(MaxWidth::ExtraSmall)
                        // ->reorderable(false)
                        // ->relationship()
                        ->schema([
                            TextInput::make('full_name')
                                ->label('Nombre completo')
                                ->afterStateUpdated(function (Set $set, $state) {
                                    $set('full_name', strtoupper($state));
                                })
                                ->live(onBlur: true)
                                ->maxLength(255),
                            TextInput::make('nro_identificacion')
                                ->label('Nro. de identidad')
                                ->numeric()
                                ->unique(
                                    ignoreRecord: true,
                                    table: 'affiliates',
                                    column: 'nro_identificacion',
                                )
                                ->validationMessages([
                                    'numeric'   => 'El campo es numerico',
                                ]),
                            Select::make('sex')
                                ->label('Sexo')
                                ->options([
                                    'MASCULINO' => 'MASCULINO',
                                    'FEMENINO' => 'FEMENINO',
                                ]),
                            DatePicker::make('birth_date')
                                ->format('d-m-Y'),
                            // TextInput::make('stature')
                            //     ->label('Nro. de personas')
                            //     ->numeric(),
                            // TextInput::make('weight')
                            //     ->label('Nro. de personas')
                            //     ->numeric(),
                            Select::make('relationship')
                                ->label('Parentesco')
                                ->options([
                                    'MADRE'     => 'MADRE',
                                    'PADRE'     => 'PADRE',
                                    'ESPOSA'    => 'ESPOSA',
                                    'ESPOSO'    => 'ESPOSO',
                                    'HIJO'      => 'HIJO',
                                    'HIJA'      => 'HIJA',
                                ]),
                        ])
                        // ->defaultItems(6)
                        ->addActionLabel('Agregar afiliado')
                        ->columns(5)

                ]),

            Section::make('DECLARACION DE CONDICIONES MEDICAS')
                ->description('(Sólo para solicitantes del Plan Especial). Responda Si o No, tomando en cuenta todos los solicitantes. Las respuestas afirmativas deben ser ampliadas.')
                ->collapsed()
                ->icon('heroicon-s-building-office-2')
                ->schema([
                    Radio::make('cuestion_1')
                        ->label('¿ Usted y el grupo de beneficiarios solicitantes, gozan de buena salud ?')
                        ->default(false)
                        ->live()
                        ->boolean()
                        ->inline()
                        ->inlineLabel(false),
                    Radio::make('cuestion_2')
                        ->label('¿ Usted o el grupo de beneficiarios presentan alguna condición médica o congénita?')
                        ->default(false)
                        ->live()
                        ->boolean()
                        ->inline()
                        ->inlineLabel(false),
                    Radio::make('cuestion_3')
                        ->label('¿ Usted o el grupo de beneficiarios ha sido intervenido quirúrgicamente?')
                        ->default(false)
                        ->live()
                        ->boolean()
                        ->inline()
                        ->inlineLabel(false),
                    Radio::make('cuestion_4')
                        ->label('Enfermedades Cerebrovasculares, tales como: Desmayos, confusión, parálisis de miembros, dicultad para
                                    hablar, articular y entender, Accidente Cerebro-vascular (ACV). Cefalea o migraña. Epilepsia o Convulsiones.
                                    Otros trastornos o enfermedad del Cerebro o Sistema Nervioso.')
                        ->default(false)
                        ->live()
                        ->boolean()
                        ->inline()
                        ->inlineLabel(false),
                    Radio::make('cuestion_5')
                        ->label('Enfermedades Respiratorias, tales como: Asma Bronquial, Bronquitis, Bronquiolitis, Enfisema, Neumonía, Enfermedad pulmonar Obstructiva Crónica (EPOC) u otras enfermedades del Sistema Respiratorio.')
                        ->default(false)
                        ->live()
                        ->boolean()
                        ->inline()
                        ->inlineLabel(false),
                    Radio::make('cuestion_6')
                        ->label('Enfermedades o Trastornos Endocrinos tales como: Diabetes Mellitus, Bocio, hipertiroidismo, hipotiroidismo, Tiroiditis, Resistencia a la insulina, enfermedad de Cushing, cáncer de tiroides.')
                        ->default(false)
                        ->live()
                        ->boolean()
                        ->inline()
                        ->inlineLabel(false),
                    Radio::make('cuestion_7')
                        ->label('Enfermedades Gastrointestinales como: Litiasis vesicular, Cólico Biliar, Úlcera gástrica, gastritis, Hemorragia
                                    digestivas, colitis, hemorroides, Apendicitis, Peritonitis, Pancreatitis u otros desórdenes del estómago, intestino,
                                    hígado o vesícula biliar.')
                        ->default(false)
                        ->live()
                        ->boolean()
                        ->inline()
                        ->inlineLabel(false),
                    Radio::make('cuestion_8')
                        ->label('Enfermedades Renales: Litiasis renal, Cólico nefrítico, Sangre en la orina o Hematuria, Cistitis, Infecciones urinarias, Pielonefritis, Insficiencia renal aguda. Otras enfermedades del riñón, vejiga o próstata.')
                        ->default(false)
                        ->live()
                        ->boolean()
                        ->inline()
                        ->inlineLabel(false),
                    Radio::make('cuestion_9')
                        ->label('Enfermedades Osteoarticulares, Artrosis, Artritis reumatoide, Traumatismo craneoencefálico, Fracturas óseas,
                                    Luxaciones o esguinces, tumores óseos, u otros trastornos de los músculos, articulaciones o columna vertical o
                                    espalda.')
                        ->default(false)
                        ->live()
                        ->boolean()
                        ->inline()
                        ->inlineLabel(false),
                    Radio::make('cuestion_10')
                        ->label('¿Ha sufrido o padece de alguna enfermedad de la Piel como: Dermatitis, Celulitis, Abscesos cutáneos, quistes, tumores o cáncer? ,Quemaduras o Heridas Complicadas.')
                        ->default(false)
                        ->live()
                        ->boolean()
                        ->inline()
                        ->inlineLabel(false),
                    Radio::make('cuestion_11')
                        ->label('¿Padece de alguna enfermedad o desorden de los ojos, oídos, nariz o garganta?')
                        ->default(false)
                        ->live()
                        ->boolean()
                        ->inline()
                        ->inlineLabel(false),
                    Radio::make('cuestion_12')
                        ->label('¿Ha padecido de algún Envenenamiento o Intoxicación, ¿Alergia o Reacción de Hipersensibilidad (medicamentosa, alimentaria, picadura de insecto, otras), edema de glotis o analaxia?')
                        ->default(false)
                        ->live()
                        ->boolean()
                        ->inline()
                        ->inlineLabel(false),
                    Radio::make('cuestion_13')
                        ->label('¿Usted o alguno de los solicitantes, toma algún tipo de medicamentos por tratamiento prolongado?')
                        ->default(false)
                        ->live()
                        ->boolean()
                        ->inline()
                        ->inlineLabel(false),
                    Radio::make('cuestion_14')
                        ->label('¿Ha padecido de algún Envenenamiento o Intoxicación, ¿Alergia o Reacción de Hipersensibilidad (medicamentosa, alimentaria, picadura de insecto, otras), edema de glotis o analaxia?')
                        ->default(false)
                        ->live()
                        ->boolean()
                        ->inline()
                        ->inlineLabel(false),
                    Section::make('DETALLES:')
                        ->description('En caso de haber respondido a rmativamente alguna de las preguntas de la DECLARACIÓN CONDICIONES MÉDICAS, indique la pregunta que 
                                        corresponda, especi que la persona solicitante e indique detalles como: Diagnistico/Enfermedad, Fecha y Condicion actual.')
                        ->hidden(fn(Get $get) => $get('plan_id') == 1 || $get('plan_id') == 2)
                        ->schema([
                            Textarea::make('observations')
                                ->label('Observaciones')
                                ->autosize()
                                ->required()
                        ])->columnSpan('full')

                ])
                ->hidden(fn(Get $get) => $get('plan_id') == 1 || $get('plan_id') == 2),
            Section::make('ACUERDO Y CONDICIONES')
                ->collapsed()
                ->description(function (Get $get) {
                    if ($get('plan_id') == 1 || $get('plan_id') == 2) {
                        return 'Estoy de acuerdo en aceptar la cobertura domiciliaria para patologías agudas del plan seleccionado, bajo los términos y condiciones con que sea
                                       emitido. De no ser así, notificare mi desacuerdo por escrito, durante los quince (15) días siguientes.';
                    }
                    if ($get('plan_id') == 3) {
                        return 'Certifico que he leído todas las respuestas y declaraciones en esta solicitud y que a mi mejor entendimiento, están completas y son verdaderas.
                                    Entiendo que cualquier omisión o declaración incompleta o incorrecta puede causar que las reclamaciones sean negadas y que el plan sea modificado, rescindido
                                    o cancelado.
                                    Estoy de acuerdo en aceptar la cobertura bajo los términos y condiciones con que sea emitida.
                                    De no ser así , notificaré mi desacuerdo por escrito a la compañía durante los quince (15) días siguientes al recibir el certificado de cobertura.
                                    Como Agente, acepto completa responsabilidad por el envío de esta solicitud, todas las primas cobradas y por la entrega de la póliza cuando sea emitida.
                                    Desconozco la existencia de cualquier condición que no haya sido revelada en esta solicitud que pudiera afectar la asegurabilidad de los propuestos asegurados.';
                    }
                })
                ->icon('heroicon-m-folder-plus')
                ->schema([
                    Checkbox::make('is_accepted')
                        ->label('ACEPTO')
                        ->required(),
                ])
                ->hiddenOn('edit')
                ->columns(3),
            ]);
    }
}