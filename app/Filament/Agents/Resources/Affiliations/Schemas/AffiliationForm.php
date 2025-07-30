<?php

namespace App\Filament\Agents\Resources\Affiliations\Schemas;

use App\Models\City;
use App\Models\Agent;
use App\Models\State;
use App\Models\Agency;
use App\Models\Region;
use App\Models\Country;
use App\Models\Coverage;
use App\Models\Affiliation;
use Filament\Schemas\Schema;
use App\Models\IndividualQuote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Radio;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use App\Models\DetailIndividualQuote;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Facades\Blade;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Wizard;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Fieldset;
use Illuminate\Database\Eloquent\Builder;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Forms\Components\Repeater\TableColumn;

class AffiliationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make('Información principal')
                        ->description('Datos para la afiliación')
                        // ->icon(Heroicon::ClipboardDocumentList)
                        // ->completedIcon(Heroicon::Check)
                        ->schema([
                            Grid::make()->schema([
                                TextInput::make('code')
                                    ->label('Código de afiliación')
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
                                        return 'TDEC-IND-000' . $parte_entera + 1;
                                    })
                                    ->required(),

                            ])->columns(3),
                            Grid::make(3)->schema([
                                Select::make('individual_quote_id')
                                    ->label('Nombre del cliente')
                                    ->live()
                                    ->disabled()
                                    ->dehydrated()
                                    ->prefixIcon('heroicon-m-clipboard-document-check')
                                    ->options(IndividualQuote::select('id', 'agent_id', 'status', 'full_name')->where('agent_id', Auth::user()->agent_id)->where('status', 'APROBADA')->pluck('full_name', 'id'))
                                    ->default(function () {
                                        $id = request()->query('id');
                                        if (isset($id)) {
                                            return $id;
                                        }
                                        return null;
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        $code = IndividualQuote::select('code', 'id')->where('id', $state)->first()->code;
                                        $set('code_individual_quote', $code);
                                    })
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
                                    ->disabled(function () {
                                        $plan_id = request()->query('plan_id');
                                        if (isset($plan_id) && $plan_id != null) {
                                            return true;
                                        }
                                        return false;
                                    })
                                    ->dehydrated()
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
                                    ->helperText('Punto(.) para separar miles.')
                                    ->label('Cobertura')
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
                                    ->relationship(
                                        name: 'coverage',
                                        modifyQueryUsing: fn(Builder $query, Get $get) => $query->where('plan_id', $get('plan_id'))->orderBy('price', 'asc'),
                                    )
                                    ->getOptionLabelFromRecordUsing(fn(Coverage $record) => number_format($record->price, 0, '', '.'))
                                    ->searchable()
                                    ->required()
                                    ->validationMessages([
                                        'required'  => 'Campo Requerido',
                                    ])
                                    ->prefixIcon('heroicon-s-globe-europe-africa')
                                    ->hidden(fn(Get $get) => $get('plan_id') == 1 || $get('plan_id') == null)
                                    ->preload(),
                                Select::make('payment_frequency')
                                    ->label('Frecuencia de pago')
                                    ->live()
                                    ->options([
                                        'ANUAL'      => 'ANUAL',
                                        'SEMESTRAL'  => 'SEMESTRAL',
                                        'TRIMESTRAL' => 'TRIMESTRAL',
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

                                Hidden::make('created_by')->default(Auth::user()->name),
                                Hidden::make('status')->default('PRE-APROBADA'),
                                Hidden::make('agent_id')->default(Auth::user()->agent_id),
                                Hidden::make('code_agency')->default(function () {
                                    $code_agency = Agent::select('owner_code', 'id')->where('id', Auth::user()->agent_id)->first()->owner_code;
                                    return $code_agency;
                                }),
                                Hidden::make('owner_code')->default(function () {
                                    $owner      = Agent::select('owner_code', 'id')->where('id', Auth::user()->agent_id)->first()->owner_code;

                                    if ($owner == 'TDG-100') {
                                        /**
                                         * Cuando el agente pertenece a TDG-100
                                         * ------------------------------------------
                                         */
                                        return $owner;
                                    } else {
                                        /**
                                         * Cuando el agente pertenece a una agencia Master
                                         * ---------------------------------------------------------------------------------------------
                                         */
                                        $jerarquia  = Agency::select('code', 'owner_code')->where('code', $owner)->first()->owner_code;
                                        return $jerarquia;
                                    }

                                    /**
                                     * Cuando el agente pertenece a una AGENCIA GENERAL
                                     * ------------------------------------------------------
                                     */
                                    if ($owner != $jerarquia && $jerarquia != 'TDG-100') {
                                        return $jerarquia;
                                    }

                                    /**
                                     * Cuando el agente pertenece a una AGENCIA MASTER
                                     * ------------------------------------------------------
                                     */
                                    if ($owner != $jerarquia && $jerarquia == 'TDG-100') {
                                        return $owner;
                                    }
                                }),
                            ])
                        ]),
                    Step::make('Titular')
                        ->description('Información del titular')
                        // ->icon(Heroicon::User)
                        // ->completedIcon(Heroicon::Check)
                        ->schema([
                            Grid::make(3)->schema([
                                TextInput::make('full_name_ti')
                                    ->label('Nombre y Apellido')
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        $set('full_name_ti', strtoupper($state));
                                    })
                                    ->live(onBlur: true)
                                    ->prefixIcon('heroicon-s-identification')
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Campo requerido',
                                    ])
                                    ->maxLength(255),
                                TextInput::make('nro_identificacion_ti')
                                    ->label('Nro. de Identificación')
                                    ->prefixIcon('heroicon-s-identification')
                                    ->unique(
                                        ignoreRecord: true,
                                        table: 'affiliations',
                                        column: 'nro_identificacion_ti',
                                    )
                                    ->mask('999999999')
                                    ->rules([
                                        'regex:/^[0-9]+$/' // Acepta de 1 a 6 dígitos
                                    ])
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
                                    ->required()
                                    ->validationMessages([
                                        'required'  => 'Campo Requerido',
                                    ])
                                    ->preload(),

                                DatePicker::make('birth_date_ti')
                                    ->label('Fecha de Nacimiento')
                                    ->prefixIcon('heroicon-m-calendar-days')
                                    ->displayFormat('d/m/Y')
                                    ->format('d-m-Y')
                                    ->required()
                                    ->validationMessages([
                                        'required'  => 'Campo Requerido',
                                    ]),
                                TextInput::make('email_ti')
                                    ->label('Email')
                                    ->prefixIcon('heroicon-s-at-symbol')
                                    ->email()
                                    ->required()
                                    ->unique(
                                        ignoreRecord: true,
                                        table: 'affiliations',
                                        column: 'email_ti',
                                    )
                                    ->validationMessages([
                                        'unique'    => 'El Correo electrónico ya se encuentra registrado.',
                                        'required'  => 'Campo requerido',
                                        'email'     => 'El campo es un email',
                                    ])
                                    ->maxLength(255),
                                TextInput::make('adress_ti')
                                    ->label('Dirección')
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        $set('adress_ti', strtoupper($state));
                                    })
                                    ->live(onBlur: true)
                                    ->prefixIcon('heroicon-s-identification')
                                    ->required()
                                    ->validationMessages([
                                        'required'  => 'Campo Requerido',
                                    ])
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
                                    ->hiddenOn('edit')
                                    ->default('+58')
                                    ->live(onBlur: true),
                                TextInput::make('phone_ti')
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
                                Select::make('country_id_ti')
                                    ->label('País')
                                    ->live()
                                    ->options(Country::all()->pluck('name', 'id'))
                                    ->searchable()
                                    ->prefixIcon('heroicon-s-globe-europe-africa')
                                    ->required()
                                    ->validationMessages([
                                        'required'  => 'Campo Requerido',
                                    ])
                                    ->default(189)
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
                                    ->required()
                                    ->validationMessages([
                                        'required'  => 'Campo Requerido',
                                    ])
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
                                    ->required()
                                    ->validationMessages([
                                        'required'  => 'Campo Requerido',
                                    ])
                                    ->preload(),
                                FileUpload::make('document')
                                    ->label('Documento del titular')
                                    ->uploadingMessage('Cargando documento...'),

                                Grid::make(1)
                                    ->schema([
                                        Radio::make('feedback')
                                            ->label('¿Desea incluir beneficiarios adicionales?')
                                            ->default(true)
                                            ->live()
                                            ->boolean()
                                            ->inline()
                                            ->inlineLabel(false)
                                    ])->columnSpanFull()->hiddenOn('edit'),
                            ])
                        ]),
                    Step::make('Afiliados')
                        ->hidden(fn(Get $get) => !$get('feedback'))
                        ->description('Data de afiliados')
                        // ->icon(Heroicon::UserGroup)
                        // ->completedIcon(Heroicon::Check)
                        ->schema([
                            Repeater::make('affiliates')
                                ->label('Información de afiliados')
                                // ->table([
                                //     TableColumn::make('Nombre completo'),
                                //     TableColumn::make('Cédula de identidad'),
                                //     TableColumn::make('Sexo'),
                                //     TableColumn::make('Fecha de nacimiento'),
                                //     TableColumn::make('Parentesco'),
                                //     TableColumn::make('Documento'),
                                // ])
                                ->schema([
                                    Grid::make(2)
                                        ->schema([
                                            Fieldset::make('Información personal del afiliado')
                                                ->schema([
                                                    TextInput::make('full_name')
                                                        ->afterStateUpdated(function (Set $set, $state) {
                                                            $set('full_name', strtoupper($state));
                                                        })
                                                        ->required()
                                                        ->validationMessages([
                                                            'required'  => 'Campo Requerido',
                                                        ])
                                                        ->live(onBlur: true)
                                                        ->maxLength(255),
                                                    TextInput::make('nro_identificacion')
                                                        ->numeric()
                                                        ->unique(
                                                            ignoreRecord: true,
                                                            table: 'affiliates',
                                                            column: 'nro_identificacion',
                                                        )
                                                        ->mask('999999999')
                                                        ->rules([
                                                            'regex:/^[0-9]+$/' // Acepta de 1 a 6 dígitos
                                                        ])
                                                        ->required()
                                                        ->validationMessages([
                                                            'numeric'   => 'El campo es numerico',
                                                            'required'  => 'Campo Requerido',
                                                        ]),
                                                    Select::make('sex')
                                                        ->options([
                                                            'MASCULINO' => 'MASCULINO',
                                                            'FEMENINO' => 'FEMENINO',
                                                        ])
                                                        ->required()
                                                        ->validationMessages([
                                                            'required'  => 'Campo Requerido',
                                                        ]),
                                                    DatePicker::make('birth_date')
                                                        ->displayFormat('d-m-Y')
                                                        ->format('d-m-Y')
                                                        ->required()
                                                        ->validationMessages([
                                                            'required'  => 'Campo Requerido',
                                                        ]),
                                                    Select::make('relationship')
                                                        ->label('Parentesco')
                                                        ->options([
                                                            'AMIGO'     => 'AMIGO',
                                                            'MADRE'     => 'MADRE',
                                                            'PADRE'     => 'PADRE',
                                                            'CONYUGE'   => 'CONYUGE',
                                                            'HIJO'      => 'HIJO',
                                                            'HIJA'      => 'HIJA',
                                                            'OTRO'      => 'OTRO',
                                                        ])
                                                        ->required()
                                                        ->validationMessages([
                                                            'required'  => 'Campo Requerido',
                                                        ]),
                                                ])->columnSpanFull(1)->columns(5),
                                            Fieldset::make('Documento de identidad')
                                                ->schema([
                                                    FileUpload::make('document')
                                                        ->label('Documento')
                                                        ->uploadingMessage('Cargando documento...')
                                                        ->required()
                                                        ->validationMessages([
                                                            'required'  => 'Campo Requerido',
                                                        ])

                                                ])->columnSpanFull(1),
                                        ])->columnSpanFull()->columns(2),
                                ])
                                ->columnSpanFull()
                                ->defaultItems(function (Get $get, Set $set) {
                                    //Se reste 1 por el titular, ejempo: La cotización es para 2 personas, el titular y 1 afiliado;
                                    return session()->get('persons') - 1;
                                })
                                ->addActionLabel('Agregar afiliado')
                        ]),
                    Step::make('Información Adicional')
                        ->description('Datos del Pagador')
                        // ->icon(Heroicon::ClipboardDocumentList)
                        // ->completedIcon(Heroicon::Check)
                        ->schema([
                            Grid::make(1)
                                ->schema([
                                    Radio::make('feedback_dos')
                                        ->label('¿El titular de la póliza es el responsable de pago?')
                                        ->default(true)
                                        ->live()
                                        ->boolean()
                                        ->inline()
                                        ->inlineLabel(false)
                                ])->hiddenOn('edit'),
                            Fieldset::make('Datos principales del pagador')
                                ->schema([
                                    TextInput::make('full_name_payer')
                                        ->label('Nombre y Apellido')
                                        ->afterStateUpdated(function (Set $set, $state) {
                                            $set('full_name_payer', strtoupper($state));
                                        })
                                        ->live(onBlur: true)
                                        ->prefixIcon('heroicon-s-identification')
                                        ->required()
                                        ->validationMessages([
                                            'required' => 'Campo requerido',
                                        ])
                                        ->maxLength(255),
                                    TextInput::make('nro_identificacion_payer')
                                        ->label('Nro. de Identificación')
                                        ->prefixIcon('heroicon-s-identification')
                                        ->unique(
                                            ignoreRecord: true,
                                            table: 'affiliations',
                                            column: 'nro_identificacion_payer',
                                        )
                                        ->mask('999999999')
                                        ->rules([
                                            'regex:/^[0-9]+$/' // Acepta de 1 a 6 dígitos
                                        ])
                                        ->validationMessages([
                                            'numeric'   => 'El campo es numerico',
                                        ])
                                        ->required(),
                                    Select::make('country_code_payer')
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
                                    TextInput::make('phone_payer')
                                        ->prefixIcon('heroicon-s-phone')
                                        ->tel()
                                        ->label('Número de teléfono')
                                        ->required()
                                        ->validationMessages([
                                            'required'  => 'Campo Requerido',
                                        ])
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                            $countryCode = $get('country_code_payer');
                                            if ($countryCode) {
                                                $cleanNumber = ltrim(preg_replace('/[^0-9]/', '', $state), '0');
                                                $set('phone_payer', $countryCode . $cleanNumber);
                                            }
                                        }),
                                    TextInput::make('email_payer')
                                        ->label('Email')
                                        ->prefixIcon('heroicon-s-at-symbol')
                                        ->email()
                                        ->required()
                                        ->unique(
                                            ignoreRecord: true,
                                            table: 'affiliations',
                                            column: 'email_payer',
                                        )
                                        ->validationMessages([
                                            'unique'    => 'El Correo electrónico ya se encuentra registrado.',
                                            'required'  => 'Campo requerido',
                                            'email'     => 'El campo es un email',
                                        ])
                                        ->maxLength(255),
                                    Select::make('relationship_payer')
                                        ->label('Parentesco')
                                        ->options([
                                            'AMIGO'     => 'AMIGO',
                                            'MADRE'     => 'MADRE',
                                            'PADRE'     => 'PADRE',
                                            'CONYUGE'   => 'CONYUGE',
                                            'HIJO'      => 'HIJO',
                                            'HIJA'      => 'HIJA',
                                        ]),
                                ])->columns(3)->hidden(fn(Get $get) => $get('feedback_dos')),
                        ]),
                    Step::make('Acuerdo y condiciones')
                        ->description('Leer y aceptar las condiciones')
                        // ->icon(Heroicon::ShieldCheck)
                        // ->completedIcon(Heroicon::Check)
                        ->schema([
                            Section::make('Lea detenidamente las siguientes condiciones!')
                                ->description(function (Get $get) {
                                    if ($get('plan_id') == 1 || $get('plan_id') == 2) {
                                        return 'Estoy de acuerdo en aceptar la cobertura domiciliaria para patologías agudas del plan seleccionado, bajo los términos y condiciones de la emisión. De no ser así, notificare mi desacuerdo por escrito, durante los quince (15) días siguientes.';
                                    }
                                    if ($get('plan_id') == 3) {
                                        return 'Certifico que he leído todas las respuestas y declaraciones en esta solicitud y que a mi mejor entendimiento, están completas y son verdaderas.
                                    Entiendo que cualquier omisión o declaración incompleta o incorrecta puede causar que las reclamaciones sean negadas y que el plan sea modificado, rescindido
                                    o cancelado.
                                    Estoy de acuerdo en aceptar la cobertura bajo los términos y condiciones con que sea emitida.
                                    De no ser así , notificaré mi desacuerdo por escrito a la compañía durante los quince (15) días siguientes al recibir el certificado de cobertura.
                                    Como Agente, acepto completa responsabilidad por el envío de esta solicitud, todas las tarifas cobradas y por la entrega del certificado de afiliación cuando sea emitida.
                                    Desconozco la existencia de cualquier condición que no haya sido revelada en esta solicitud que pudiera afectar la protección de los afiliados.';
                                    }
                                })
                                ->icon('heroicon-m-folder-plus')
                                ->schema([
                                    Checkbox::make('is_accepted')
                                        ->label('ACEPTO')
                                        ->required(),
                                ])
                                ->hiddenOn('edit')
                        ]),
                ])
                    ->submitAction(new HtmlString(Blade::render(<<<BLADE
                    <x-filament::button
                        type="submit"
                        size="sm"
                    >
                        Crear Pre-Afiliación
                    </x-filament::button>
                BLADE)))
                    ->columnSpanFull(),

            ]);
    }
}
