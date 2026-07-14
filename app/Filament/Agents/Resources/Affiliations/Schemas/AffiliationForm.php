<?php

namespace App\Filament\Agents\Resources\Affiliations\Schemas;

use App\Http\Controllers\UtilsController;
use App\Models\Affiliation;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\City;
use App\Models\Country;
use App\Models\Coverage;
use App\Models\DetailIndividualQuote;
use App\Models\IndividualQuote;
use App\Models\Region;
use App\Models\State;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

class AffiliationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make('Información principal')
                        ->description('Datos para la afiliación')
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

                                        return 'TDEC-IND-000'.$parte_entera + 1;
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
                                        $id = request()->query('id') ?? request()->query('individual_quote_id');
                                        if (isset($id)) {
                                            return $id;
                                        }

                                        $dataRecords = session()->get('data_records') ?? [];
                                        $quoteRecord = is_array($dataRecords[0] ?? null) ? $dataRecords[0] : [];

                                        return $quoteRecord['individual_quote_id'] ?? null;
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        $code = IndividualQuote::select('code', 'id')->where('id', $state)->first()->code;
                                        $set('code_individual_quote', $code);
                                    })
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Campo Requerido',
                                    ]),

                                Select::make('plan_id')
                                    ->default(function () {
                                        $planId = request()->query('plan_id');
                                        if ($planId !== null && $planId !== '') {
                                            return $planId;
                                        }

                                        $dataRecords = session()->get('data_records') ?? [];
                                        $quoteRecord = is_array($dataRecords[0] ?? null) ? $dataRecords[0] : [];

                                        return $quoteRecord['plan_id'] ?? null;
                                    })
                                    ->label('Plan')
                                    ->live()
                                    ->disabled(function () {
                                        $planId = request()->query('plan_id');
                                        if ($planId !== null && $planId !== '') {
                                            return true;
                                        }

                                        $dataRecords = session()->get('data_records') ?? [];
                                        $quoteRecord = is_array($dataRecords[0] ?? null) ? $dataRecords[0] : [];

                                        return filled($quoteRecord['plan_id'] ?? null);
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
                                        'required' => 'Campo Requerido',
                                    ]),
                                Select::make('coverage_id')
                                    ->helperText('Punto(.) para separar miles.')
                                    ->label('Cobertura')
                                    ->live()
                                    ->default(function () {
                                        $coverageId = request()->query('coverage_id');
                                        if ($coverageId !== null && $coverageId !== '') {
                                            return $coverageId;
                                        }

                                        $dataRecords = session()->get('data_records') ?? [];
                                        $quoteRecord = is_array($dataRecords[0] ?? null) ? $dataRecords[0] : [];

                                        return $quoteRecord['coverage_id'] ?? null;
                                    })
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
                                        modifyQueryUsing: fn (Builder $query, Get $get) => $query->where('plan_id', $get('plan_id'))->orderBy('price', 'asc'),
                                    )
                                    ->getOptionLabelFromRecordUsing(fn (Coverage $record) => number_format($record->price, 0, '', '.'))
                                    ->searchable()
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Campo Requerido',
                                    ])
                                    ->disabled(function () {
                                        $coverageId = request()->query('coverage_id');
                                        if ($coverageId !== null && $coverageId !== '') {
                                            return true;
                                        }

                                        $dataRecords = session()->get('data_records') ?? [];
                                        $quoteRecord = is_array($dataRecords[0] ?? null) ? $dataRecords[0] : [];

                                        return filled($quoteRecord['coverage_id'] ?? null);
                                    })
                                    ->dehydrated()
                                    ->prefixIcon('heroicon-s-globe-europe-africa')
                                    ->hidden(fn (Get $get) => $get('plan_id') == 1 || $get('plan_id') == null)
                                    ->preload(),
                                Select::make('payment_frequency')
                                    ->label('Frecuencia de pago')
                                    ->live()
                                    // ->options([
                                    //     'ANUAL'      => 'ANUAL',
                                    //     'SEMESTRAL'  => 'SEMESTRAL',
                                    //     'TRIMESTRAL' => 'TRIMESTRAL',
                                    // ])
                                    ->options(function (Get $get) {
                                        $simpleArray = [
                                            'ANUAL' => 'ANUAL',
                                            'SEMESTRAL' => 'SEMESTRAL',
                                            'TRIMESTRAL' => 'TRIMESTRAL',
                                        ];
                                        $simpleArrayMonth = [
                                            'ANUAL' => 'ANUAL',
                                            'SEMESTRAL' => 'SEMESTRAL',
                                            'TRIMESTRAL' => 'TRIMESTRAL',
                                            'MENSUAL' => 'MENSUAL',
                                        ];
                                        if (Agent::where('id', Auth::user()->agent_id)->first()->activate_monthly_frequency == 1) {
                                            return $simpleArrayMonth;
                                        }

                                        return $simpleArray;
                                    })
                                    ->searchable()
                                    ->live()
                                    ->prefixIcon('heroicon-s-globe-europe-africa')
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Campo Requerido',
                                    ])
                                    ->preload()
                                    ->afterStateUpdated(function ($state, $set, Get $get) {
                                        if ($get('payment_frequency') == 'ANUAL') {
                                            // busco el valor de la cotizacion de acuerdo al plan y a la covertura
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
                                        if ($get('payment_frequency') == 'MENSUAL') {

                                            $data_quote = DetailIndividualQuote::select('individual_quote_id', 'plan_id', 'coverage_id', 'subtotal_monthly')
                                                ->where('individual_quote_id', $get('individual_quote_id'))
                                                ->where('plan_id', $get('plan_id'))
                                                ->when($get('plan_id') != 1, function ($query) use ($get) {
                                                    return $query->where('coverage_id', $get('coverage_id'));
                                                })
                                                ->get();

                                            $set('total_amount', $data_quote->sum('subtotal_monthly'));
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
                                Hidden::make('ownerAccountManagers')->default(function () {
                                    $user_id = Auth::user()->agent_id;

                                    return Agent::where('id', $user_id)->first()->ownerAccountManagers;
                                }),
                                Hidden::make('created_by')->default(Auth::user()->name),
                                Hidden::make('status')->default('PRE-APROBADA'),
                                Hidden::make('agent_id')->default(Auth::user()->agent_id),

                                // Calculo del codigo de la agencia a la que pertenece el agente
                                Hidden::make('code_agency')->default(function () {
                                    // ....
                                    // El owner_code del agente logueado es el codigo de la agencia a la que pertenece
                                    $code_agency = Agent::select('owner_code', 'id')->where('id', Auth::user()->agent_id)->first()->owner_code;

                                    return $code_agency;

                                }),

                                // Calculo de codigo del propietario final de la agencia a la que pertenece el agente
                                Hidden::make('owner_code')->default(function () {
                                    $owner = Agent::select('owner_code', 'id')->where('id', Auth::user()->agent_id)->first()->owner_code;

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
                                        $jerarquia = Agency::select('code', 'owner_code')->where('code', $owner)->first()->owner_code;

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
                            ]),
                        ]),
                    Step::make('Titular')
                        ->description('Información del titular')
                        ->schema([
                            Grid::make(3)->schema([
                                TextInput::make('full_name_ti')
                                    ->label('Nombre y Apellido')
                                    ->afterStateUpdatedJs(<<<'JS'
                                        $set('full_name_ti', $state.toUpperCase());
                                    JS)
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
                                        table: Affiliation::class,
                                        column: 'nro_identificacion_ti',
                                    )
                                    ->mask('999999999')
                                    ->rules([
                                        'regex:/^[0-9]+$/', // Acepta de 1 a 6 dígitos
                                    ])
                                    ->validationMessages([
                                        'numeric' => 'El campo es numerico',
                                        'unique' => 'La cedula del titular ya se enceuntra registrada',
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
                                        'required' => 'Campo Requerido',
                                    ])
                                    ->preload(),

                                DatePicker::make('birth_date_ti')
                                    ->label('Fecha de Nacimiento')
                                    ->prefixIcon('heroicon-m-calendar-days')
                                    ->displayFormat('d/m/Y')
                                    ->format('d-m-Y')
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Campo Requerido',
                                    ]),
                                TextInput::make('email_ti')
                                    ->label('Correo Electrónico')
                                    ->email()
                                    ->rule('regex:/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/')
                                    ->validationMessages([
                                        'required' => 'Campo requerido',
                                        'email' => 'El correo no es valido',
                                        'regex' => 'El correo no debe contener mayúsculas, espacios, ñ, ni caracteres especiales no permitidos.',
                                    ]),
                                TextInput::make('adress_ti')
                                    ->label('Dirección')
                                    ->afterStateUpdatedJs(<<<'JS'
                                        $set('adress_ti', $state.toUpperCase());
                                    JS)
                                    ->live(onBlur: true)
                                    ->prefixIcon('heroicon-s-identification')
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Campo Requerido',
                                    ])
                                    ->maxLength(255),
                                Select::make('country_code_ti')
                                    ->label('Código de país')
                                    ->options(fn () => UtilsController::getCountries())
                                    ->hiddenOn('edit')
                                    ->default('+58')
                                    ->live(onBlur: true),
                                TextInput::make('phone_ti')
                                    ->prefixIcon('heroicon-s-phone')
                                    ->tel()
                                    ->label('Número de teléfono')
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Campo Requerido',
                                    ])
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                        $countryCode = $get('country_code_ti');
                                        if ($countryCode) {
                                            $cleanNumber = ltrim(preg_replace('/[^0-9]/', '', $state), '0');
                                            $set('phone_ti', $countryCode.$cleanNumber);
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
                                        'required' => 'Campo Requerido',
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
                                        'required' => 'Campo Requerido',
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
                                        'required' => 'Campo Requerido',
                                    ])
                                    ->preload(),
                                FileUpload::make('document')
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Campo Requerido',
                                    ])
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
                                            ->inlineLabel(false),
                                    ])->columnSpanFull()->hiddenOn('edit'),
                            ]),
                        ]),
                    Step::make('Afiliados')
                        ->hidden(fn (Get $get) => ! $get('feedback'))
                        ->description('Data de afiliados')
                        ->schema([
                            Repeater::make('affiliates')
                                ->label('Información de afiliados')
                                ->schema([
                                    Grid::make(2)
                                        ->schema([
                                            Fieldset::make('Información personal del afiliado')
                                                ->schema([
                                                    TextInput::make('full_name')
                                                        ->label('Nombre y Apellido')
                                                        ->afterStateUpdatedJs(<<<'JS'
                                                            $set('full_name', $state.toUpperCase());
                                                        JS)
                                                        ->required()
                                                        ->validationMessages([
                                                            'required' => 'Campo Requerido',
                                                        ])
                                                        ->live(onBlur: true)
                                                        ->maxLength(255),
                                                    TextInput::make('nro_identificacion')
                                                        ->label('Número de Identificación')
                                                        ->unique(
                                                            ignoreRecord: true,
                                                            table: 'affiliates',
                                                            column: 'nro_identificacion',
                                                        )
                                                        ->mask('999999999')
                                                        ->rules([
                                                            'regex:/^[0-9]+$/', // Acepta de 1 a 6 dígitos
                                                        ])
                                                        ->required()
                                                        ->validationMessages([
                                                            'numeric' => 'El campo es numerico',
                                                            'required' => 'Campo Requerido',
                                                        ]),
                                                    Select::make('sex')
                                                        ->label('Sexo')
                                                        ->options([
                                                            'MASCULINO' => 'MASCULINO',
                                                            'FEMENINO' => 'FEMENINO',
                                                        ])
                                                        ->required()
                                                        ->validationMessages([
                                                            'required' => 'Campo Requerido',
                                                        ]),
                                                    DatePicker::make('birth_date')
                                                        ->label('Fecha de Nacimiento')
                                                        ->displayFormat('d-m-Y')
                                                        ->format('d-m-Y')
                                                        ->required()
                                                        ->validationMessages([
                                                            'required' => 'Campo Requerido',
                                                        ]),
                                                    Select::make('relationship')
                                                        ->label('Parentesco')
                                                        ->options([
                                                            'AMIGO' => 'AMIGO',
                                                            'MADRE' => 'MADRE',
                                                            'PADRE' => 'PADRE',
                                                            'CONYUGE' => 'CONYUGE',
                                                            'HIJO' => 'HIJO',
                                                            'HIJA' => 'HIJA',
                                                            'OTRO' => 'OTRO',
                                                        ])
                                                        ->required()
                                                        ->validationMessages([
                                                            'required' => 'Campo Requerido',
                                                        ]),
                                                ])->columnSpanFull(1)->columns(5),
                                            Fieldset::make('Documento de identidad')
                                                ->schema([
                                                    FileUpload::make('document')
                                                        ->label('Documento')
                                                        ->uploadingMessage('Cargando documento...')
                                                        ->required()
                                                        ->validationMessages([
                                                            'required' => 'Campo Requerido',
                                                        ]),

                                                ])->columnSpanFull(1),
                                        ])->columnSpanFull()->columns(2),
                                ])
                                ->columnSpanFull()
                                ->defaultItems(function (Get $get, Set $set): int {
                                    $persons = (int) (session()->get('persons') ?? 0);

                                    if ($persons <= 0) {
                                        $dataRecords = session()->get('data_records') ?? [];
                                        $quoteRecord = is_array($dataRecords[0] ?? null) ? $dataRecords[0] : [];
                                        $persons = (int) ($quoteRecord['total_persons'] ?? 1);
                                    }

                                    // Se resta 1 por el titular: cotización de 2 personas => 1 afiliado adicional.
                                    return max(0, $persons - 1);
                                })
                                ->addActionLabel('Agregar afiliado'),
                        ]),
                    Step::make('Declaración de Condiciones Médicas')
                        ->hidden(fn (Get $get) => $get('plan_id') != 3)
                        ->description('Data de afiliados')
                        ->schema([
                            Fieldset::make('Cuestionario de salud')
                                ->schema([
                                    Radio::make('cuestion_1')
                                        ->label('¿Usted y el grupo de beneficiarios solicitantes, gozan de buena salud?')
                                        ->boolean()
                                        ->inline(),
                                    Radio::make('cuestion_2')
                                        ->label('¿Usted o el grupo de beneficiarios presentan alguna condición médica o congénita?')
                                        ->boolean()
                                        ->inline(),
                                    Radio::make('cuestion_3')
                                        ->label('¿Usted o el grupo de beneficiario ha sido intervenido quirúrgicamente? ')
                                        ->boolean()
                                        ->inline(),
                                    Radio::make('cuestion_4')
                                        ->label('¿Usted o el grupo de beneficiario padece o ha padecido alguna enfermedad?')
                                        ->boolean()
                                        ->inline(),
                                    Radio::make('cuestion_5')
                                        ->label('Enfermedades Cardiovasculares, tales como; Hipertensión Arterial, Ataque cardíaco, Angina o dolor de pecho,
                                                    Soplo Cardíaco, Insuficiencia Cardíaca Congestiva o desórdenes del corazón o sistema circulatorio.')
                                        ->boolean()
                                        ->inline(),
                                    Radio::make('cuestion_6')
                                        ->label('Enfermedades Cerebrovasculares, tales como: Desmayos, confusión, parálisis de miembros, dificultad para
                                                    hablar, articular y entender, Accidente Cerebro-vascular (ACV). Cefalea o migraña. Epilepsia o Convulsiones.
                                                    Otros trastornos o enfermedad del Cerebro o Sistema Nervioso.')
                                        ->boolean()
                                        ->inline(),
                                    Radio::make('cuestion_7')
                                        ->label('Enfermedades Respiratorias, tales como: Asma Bronquial, Bronquitis, Bronquiolitis, Enfisema, Neumonía, Enfer-
                                                    medad pulmonar Obstructiva Crónica (EPOC) u otras enfermedades del Sistema Respiratorio.')
                                        ->boolean()
                                        ->inline(),
                                    Radio::make('cuestion_8')
                                        ->label('Enfermedades o Trastornos Endocrinos tales como: Diabetes Mellitus, Bocio, hipertiroidismo, hipotiroidismo,
                                                Tiroiditis, Resistencia a la insulina, enfermedad de Cushing, cáncer de tiroides.')
                                        ->boolean()
                                        ->inline(),
                                    Radio::make('cuestion_9')
                                        ->label('Enfermedades Gastrointestinales como: Litiasis vesicular, Cólico Biliar, Úlcera gástrica, gastritis, Hemorragia
                                                digestivas, colitis, hemorroides, Apendicitis, Peritonitis, Pancreatitis u otros desórdenes del estómago, intestino,
                                                hígado o vesícula biliar.')
                                        ->boolean()
                                        ->inline(),
                                    Radio::make('cuestion_10')
                                        ->label('Enfermedades Renales: Litiasis renal, Cólico nefrítico, Sangre en la orina o Hematuria, Cistitis, Infecciones
                                                urinarias, Pielonefritis, Insuficiencia renal aguda. Otras enfermedades del riñón, vejiga o próstata.')
                                        ->boolean()
                                        ->inline(),
                                    Radio::make('cuestion_11')
                                        ->label('Enfermedades Osteoarticulares, Artrosis, Artritis reumatoide, Traumatismo craneoencefálico, Fracturas óseas,
                                                Luxaciones o esguinces, tumores óseos, u otros trastornos de los músculos, articulaciones o columna vertical o
                                                espalda.')
                                        ->boolean()
                                        ->inline(),
                                    Radio::make('cuestion_12')
                                        ->label('¿Ha sufrido o padece de alguna enfermedad de la Piel como: Dermatitis, Celulitis, Abscesos cutáneos, quistes,
                                                tumores o cáncer? ,Quemaduras o Heridas Complicadas.')
                                        ->boolean()
                                        ->inline(),
                                    Radio::make('cuestion_13')
                                        ->label('¿Padece de alguna enfermedad o desorden de los ojos, oídos, nariz o garganta?')
                                        ->boolean()
                                        ->inline(),
                                    Radio::make('cuestion_14')
                                        ->label('¿Ha padecido de algún Envenenamiento o Intoxicación, ¿Alergia o Reacción de Hipersensibilidad (medicamen-
                                                tosa, alimentaria, picadura de insecto, otras), edema de glotis o anafilaxia?')
                                        ->boolean()
                                        ->inline(),
                                    Radio::make('cuestion_15')
                                        ->label('¿Usted o alguno de los solicitantes, toma algún tipo de medicamentos por tratamiento prolongado?')
                                        ->boolean()
                                        ->inline(),
                                    Radio::make('cuestion_16')
                                        ->label('¿Ha padecido de algún Envenenamiento o Intoxicación, ¿Alergia o Reacción de Hipersensibilidad (medicamen-
                                                tosa, alimentaria, picadura de insecto, otras), edema de glotis o anafilaxia?')
                                        ->boolean()
                                        ->inline(),
                                ])->columns(1)->columnSpanFull(),
                            Fieldset::make('Información Adicional')
                                ->schema([
                                    Textarea::make('observations_cuestions')
                                        ->label('Observaciones adicionales')
                                        ->helperText('En caso de haber respondido afirmativamente alguna de las preguntas de la DECLARACIÓN CONDICIONES MÉDICAS, indique la pregunta que
                                                        corresponda, especifique la persona solicitante e indique detalles como: Diagnistico/Enfermedad, Fecha y Condicion actual.'),
                                ])->columnSpanFull()->columns(1),
                        ])->columnSpanFull(),
                    Step::make('Información Adicional')
                        ->description('Datos del Pagador')
                        ->schema([
                            Grid::make(1)
                                ->schema([
                                    Radio::make('feedback_dos')
                                        ->label('¿El titular de la póliza es el responsable de pago?')
                                        ->default(true)
                                        ->live()
                                        ->boolean()
                                        ->inline()
                                        ->inlineLabel(false),
                                ])->hiddenOn('edit'),
                            Fieldset::make('Datos principales del pagador')
                                ->schema([
                                    TextInput::make('full_name_payer')
                                        ->label('Nombre y Apellido')
                                        ->afterStateUpdatedJs(<<<'JS'
                                            $set('full_name_payer', $state.toUpperCase());
                                        JS)
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
                                            'regex:/^[0-9]+$/', // Acepta de 1 a 6 dígitos
                                        ])
                                        ->required(),
                                    Select::make('country_code_payer')
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
                                        ->hiddenOn('edit')
                                        ->default('+58')
                                        ->live(onBlur: true),
                                    TextInput::make('phone_payer')
                                        ->prefixIcon('heroicon-s-phone')
                                        ->tel()
                                        ->label('Número de teléfono')
                                        ->required()
                                        ->validationMessages([
                                            'required' => 'Campo Requerido',
                                        ])
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                            $countryCode = $get('country_code_payer');
                                            if ($countryCode) {
                                                $cleanNumber = ltrim(preg_replace('/[^0-9]/', '', $state), '0');
                                                $set('phone_payer', $countryCode.$cleanNumber);
                                            }
                                        }),
                                    TextInput::make('email_payer')
                                        ->label('Correo Electrónico')
                                        ->email()
                                        ->rule('regex:/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/')
                                        ->validationMessages([
                                            'required' => 'Campo requerido',
                                            'email' => 'El correo no es valido',
                                            'regex' => 'El correo no debe contener mayúsculas, espacios, ñ, ni caracteres especiales no permitidos.',
                                        ]),
                                    Select::make('relationship_payer')
                                        ->label('Parentesco')
                                        ->options([
                                            'AMIGO' => 'AMIGO',
                                            'MADRE' => 'MADRE',
                                            'PADRE' => 'PADRE',
                                            'CONYUGE' => 'CONYUGE',
                                            'HIJO' => 'HIJO',
                                            'HIJA' => 'HIJA',
                                        ]),
                                ])->columns(3)->hidden(fn (Get $get) => $get('feedback_dos')),
                        ]),
                    Step::make('Acuerdo y condiciones')
                        ->description('Leer y aceptar las condiciones')
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
                                ->hiddenOn('edit'),
                        ]),
                ])
                    ->submitAction(new HtmlString(Blade::render(<<<'BLADE'
                    <x-filament::button
                        type="submit"
                        size="sm"
                        wire:target="create" 
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-70 pointer-events-none"
                        class="min-w-28 justify-center bg-indigo-600 hover:bg-indigo-700 text-white" 
                    >
                        {{-- Contenido NORMAL: Visible solo cuando NO está cargando --}}
                        <span wire:loading.remove wire:target="create">
                            Guardar y Finalizar
                        </span>

                        {{-- Contenido CARGANDO: Visible solo mientras está cargando --}}
                        <span wire:loading wire:target="create"class="flex items-center space-x-2">
                            <span>Preafiliando y Generando Certificado PDF...</span>
                        </span>
                    </x-filament::button>
                BLADE)))
                    ->columnSpanFull(),

            ]);
    }
}
