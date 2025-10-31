<?php

namespace App\Filament\Business\Resources\IndividualQuotes\Schemas;


use App\Models\Log;
use App\Models\Plan;
use App\Models\Agent;
use App\Models\State;
use App\Models\Agency;
use App\Models\Region;
use App\Models\AgeRange;
use Filament\Schemas\Schema;
use App\Models\IndividualQuote;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Radio;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Facades\Blade;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Wizard;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use App\Http\Controllers\UtilsController;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Forms\Components\Repeater\TableColumn;

class IndividualQuoteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make('SOLICITANTE')
                        ->schema([
                            Section::make('data_client')
                                ->heading('¡Bienvenido/a de nuevo! 👋 ')
                                ->description('Estás a punto de comenzar a crear una nueva cotización, por favor ingresa la información del cliente para personalizarla. ¡Puede ver el avance del proceso en la barra de estatus!')
                                ->schema([
                                    Grid::make(4)
                                        ->schema([
                                            TextInput::make('code')
                                                ->label('Nro. de cotización')
                                                ->prefixIcon('heroicon-m-clipboard-document-check')
                                                ->default(function () {
                                                    if (IndividualQuote::max('id') == null) {
                                                        $parte_entera = 0;
                                                    } else {
                                                        $parte_entera = IndividualQuote::max('id');
                                                    }
                                                    return 'COT-IND-000' . $parte_entera + 1;
                                                })
                                                ->disabled()
                                                ->dehydrated()
                                                ->maxLength(255),

                                        ])->columnSpanFull(),
                                    Grid::make(4)
                                        ->schema([
                                            TextInput::make('full_name')
                                                ->label('Nombre y Apellido')
                                                ->prefixIcon('heroicon-m-user')
                                                ->required()
                                                ->validationMessages([
                                                    'required' => 'Campo requerido',
                                                ])
                                                ->afterStateUpdatedJs(<<<'JS'
                                                    $set('full_name', $state.toUpperCase());
                                                JS),

                                            Select::make('country_code')
                                                ->label('Código de país')
                                                ->options(fn() => UtilsController::getCountries())
                                                ->searchable()
                                                ->default('+58')
                                                ->live(onBlur: true)
                                                ->validationMessages([
                                                    'required'  => 'Campo Requerido',
                                                ])
                                                ->hiddenOn('edit'),
                                            TextInput::make('phone')
                                                ->prefixIcon('heroicon-s-phone')
                                                ->tel()
                                                ->label('Número de teléfono')
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
                                            TextInput::make('email')
                                                ->label('Correo Electrónico')
                                                ->email()
                                                ->rule('regex:/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/')
                                                ->validationMessages([
                                                    'required' => 'Campo requerido',
                                                    'email' => 'El correo no es valido',
                                                    'regex' => 'El correo no debe contener mayúsculas, espacios, ñ, ni caracteres especiales no permitidos.',
                                                ])
                                        ])->columnSpanFull(),
                                        
                                    Fieldset::make('Asociar Agencia y/o Agente')
                                    ->schema([
                                        Select::make('code_agency')
                                            ->label('Lista de Agencias')
                                            ->options(Agency::all()->pluck('name_corporative', 'code'))
                                            ->searchable()
                                            ->live()
                                            ->prefixIcon('heroicon-c-building-library')
                                            ->preload(),
                                        Select::make('agent_id')
                                            ->label('Agentes')
                                            ->options(function (Get $get) {
                                                if ($get('code_agency') == null) {
                                                    return Agent::where('owner_code', 'TDG-100')->pluck('name', 'id');
                                                }
                                                return Agent::where('owner_code', $get('code_agency'))->pluck('name', 'id');
                                            })
                                            ->searchable()
                                            ->prefixIcon('fontisto-person')
                                            ->preload(),
                                    ])->columnSpanFull(),
                                    
                                    Hidden::make('created_by')->default(Auth::user()->name),
                                    Hidden::make('status')->default('PRE-APROBADA'),
                                ])
                                ->columns(3)
                                ->columnSpanFull()

                        ]),
                    Step::make('PLANES A COTIZAR')
                        ->description('Plan(es) que desea cotizar:')
                        ->schema([
                            Section::make('plans')
                                ->heading('¡Sección de planes a cotizar! 🎯')
                                // ->description('¡Perfecto! selecciona la que mejor se ajuste a las necesidades del cliente y continúa con el proceso.')
                                ->schema([
                                    Radio::make('plan')
                                        ->columns(3)
                                        ->label('Selecciona el/los planes que desea cotizar:')
                                        ->required()
                                        ->live()
                                        ->options(function () {

                                            $planesConBeneficios = Plan::where('type', 'BASICO')->get()->pluck('description', 'id');

                                            //agregar el plan livewire
                                            $planesConBeneficios->put('CM', 'COTIZACIÓN MULTIPLE');

                                            return $planesConBeneficios;
                                        })
                                        ->descriptions([
                                            1    => 'Edad: 0 a +99 años/ilimitado.',
                                            2    => 'Edad: 0 a 85 años.',
                                            3    => 'Edad: 0 a 85 años.',
                                            'CM' => 'Seleccione más de dos (2) planes.'
                                        ])
                                ])
                        ]),
                    Step::make('RANGO DE EDAD')
                        ->description('Rango de edad y/o población:')
                        ->schema([
                            Section::make('age_range')
                                ->heading('¡Listo para el último paso! 🏁')
                                ->schema([

                                    /**
                                     * REPETER PLAN INICIAl
                                     */
                                    Repeater::make('details_quote_plan_inicial')
                                        ->grid(4)
                                        ->label('Indique edad y número de afiliados al plan:')
                                        ->defaultItems(fn(Get $get) => AgeRange::where('plan_id', 1)->count())
                                        ->addable(false)
                                        ->hidden(function (Get $get) {
                                            if ($get('plan') == 1) {
                                                return false;
                                            }
                                            return true;
                                        })
                                        ->table([
                                            TableColumn::make('Rango de Edad')->width('150px'),
                                            TableColumn::make('Total de personas')
                                        ])
                                        ->schema([
                                            Hidden::make('plan_id')->default(1),
                                            Radio::make('age_range_id')
                                                ->label(false)

                                                ->inLine()

                                                ->disableOptionWhen(function ($value, $state, Get $get) {
                                                    return collect($get('../*.age_range_id'))
                                                        ->reject(fn($id) => $id == $state)
                                                        ->filter()
                                                        ->contains($value);
                                                })
                                                ->options(function (Get $get) {
                                                    return AgeRange::where('plan_id', 1)->pluck('range', 'id');
                                                })->columnSpan(4),
                                            Select::make('total_persons')
                                                // ->label(false)
                                                ->options([
                                                    1 => 1,
                                                    2 => 2,
                                                    3 => 3,
                                                    4 => 4,
                                                    5 => 5,
                                                    6 => 6,
                                                    7 => 7,
                                                    8 => 8,
                                                    9 => 9,
                                                    10 => 10,
                                                ])
                                                ->placeholder('Cantidad de personas'),
                                        ])->columns(2),

                                    /**
                                     * REPETER PLAN IDEAL
                                     */
                                    Repeater::make('details_quote_plan_ideal')
                                        ->grid(2)
                                        ->label('Indique edad y número de afiliados al plan:')
                                        ->defaultItems(fn(Get $get) => AgeRange::where('plan_id', 2)->count())
                                        ->addable(false)
                                        ->hidden(function (Get $get) {
                                            if ($get('plan') == 2) {
                                                return false;
                                            }
                                            return true;
                                        })
                                        ->table([
                                            TableColumn::make('Rango de Edad')->width('300px'),
                                            TableColumn::make('Total de personas'),
                                        ])
                                        ->schema([
                                            Hidden::make('plan_id')->default(2),
                                            Radio::make('age_range_id')
                                                ->label(false)
                                                ->inLine()
                                                ->live()
                                                ->disableOptionWhen(function ($value, $state, Get $get) {
                                                    return collect($get('../*.age_range_id'))
                                                        ->reject(fn($id) => $id == $state)
                                                        ->filter()
                                                        ->contains($value);
                                                })
                                                ->options(function (Get $get) {
                                                    return AgeRange::where('plan_id', 2)->pluck('range', 'id');
                                                })->columnSpan(4),
                                            Select::make('total_persons')
                                                ->label(false)
                                                // ->native(false)
                                                ->options([
                                                    1 => 1,
                                                    2 => 2,
                                                    3 => 3,
                                                    4 => 4,
                                                    5 => 5,
                                                    6 => 6,
                                                    7 => 7,
                                                    8 => 8,
                                                    9 => 9,
                                                    10 => 10,
                                                ])
                                                ->placeholder('Cantidad de personas'),
                                        ])->columns(4),

                                    /**
                                     * REPETER PLAN ESPECIAL
                                     */
                                    Repeater::make('details_quote_plan_especial')
                                        ->grid(2)
                                        ->label('Indique edad y número de afiliados al plan:')
                                        ->defaultItems(fn(Get $get) => AgeRange::where('plan_id', 3)->count())
                                        ->addable(false)
                                        ->hidden(function (Get $get) {
                                            if ($get('plan') == 3) {
                                                return false;
                                            }
                                            return true;
                                        })
                                        ->table([
                                            TableColumn::make('Rango de Edad')->width('380px'),
                                            TableColumn::make('Total de personas'),
                                        ])
                                        ->schema([
                                            Hidden::make('plan_id')->default(3),
                                            Radio::make('age_range_id')
                                                ->label(false)
                                                ->inLine()
                                                ->live()
                                                ->disableOptionWhen(function ($value, $state, Get $get) {
                                                    return collect($get('../*.age_range_id'))
                                                        ->reject(fn($id) => $id == $state)
                                                        ->filter()
                                                        ->contains($value);
                                                })
                                                ->options(function (Get $get) {
                                                    return AgeRange::where('plan_id', 3)->pluck('range', 'id');
                                                })->columnSpan(4),
                                            Select::make('total_persons')
                                                ->options([
                                                    1 => 1,
                                                    2 => 2,
                                                    3 => 3,
                                                    4 => 4,
                                                    5 => 5,
                                                    6 => 6,
                                                    7 => 7,
                                                    8 => 8,
                                                    9 => 9,
                                                    10 => 10,
                                                ])
                                                ->placeholder('Cantidad de personas'),
                                        ])->columns(2),

                                    /**
                                     * REPETER PLAN MULTIPLE
                                     */
                                    Repeater::make('details_quote')
                                        ->label('Indique los planes, la edad y número de personas:')
                                        ->addActionLabel('Añadir plan')
                                        ->hidden(function (Get $get) {
                                            if ($get('plan') == 'CM') {
                                                return false;
                                            }
                                            return true;
                                        })
                                        ->schema([
                                            Radio::make('plan_id')
                                                ->label('Seleccione una opción y añadir plan:')

                                                ->inLine()
                                                ->live()
                                                ->options(function (Get $get) {
                                                    return Plan::where('type', 'BASICO')->pluck('description', 'id');
                                                })->columnSpan(3),
                                            Select::make('age_range_id')
                                                ->label('Rango de edad')
                                                ->placeholder('Rango de edad')
                                                ->options(function (Get $get) {
                                                    return AgeRange::where('plan_id', $get('plan_id'))->pluck('range', 'id');
                                                })
                                                ->live()
                                                ->searchable()
                                                ->prefixIcon('heroicon-s-globe-europe-africa')
                                                ->disableOptionWhen(function ($value, $state, Get $get) {
                                                    return collect($get('../*.age_range_id'))
                                                        ->reject(fn($id) => $id == $state)
                                                        ->filter()
                                                        ->contains($value);
                                                })
                                                ->validationMessages([
                                                    'required'  => 'Campo Requerido',
                                                ])
                                                ->preload(),
                                            TextInput::make('total_persons')
                                                ->label('Cantidad de personas')
                                                ->placeholder('Cantidad de personas')
                                                ->numeric(),
                                        ])->columns(2),
                                ])
                        ])
                ])
                    ->submitAction(new HtmlString(Blade::render(<<<BLADE
                    <x-filament::button
                        type="submit"
                        size="sm"
                    >
                        Crear cotización
                    </x-filament::button>
                BLADE)))
                    ->columnSpanFull(),
            ]);
    }
}