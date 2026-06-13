<?php

namespace App\Filament\Master\Resources\CorporateQuotes\Schemas;

use App\Http\Controllers\UtilsController;
use App\Models\Agency;
use App\Models\AgencyPlan;
use App\Models\Agent;
use App\Models\AgeRange;
use App\Models\CorporateQuote;
use App\Models\Plan;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

class CorporateQuoteForm
{
    /**
     * @return array<int, \Closure(): \Closure(string, mixed, \Closure): void>
     */
    private static function requireQuoteDetailsRule(): array
    {
        return [
            function (): \Closure {
                return function (string $attribute, mixed $value, \Closure $fail): void {
                    $selectedItems = collect($value ?? [])
                        ->filter(fn (array $item): bool => filled($item['age_range_id'] ?? null));

                    if ($selectedItems->isEmpty()) {
                        $fail('Debe seleccionar al menos un (1) rango de edad para crear la cotización.');

                        return;
                    }

                    $hasInvalidPersonCount = $selectedItems->contains(function (array $item): bool {
                        $totalPersons = $item['total_persons'] ?? null;

                        return ! is_numeric($totalPersons) || (int) $totalPersons < 1;
                    });

                    if ($hasInvalidPersonCount) {
                        $fail('La cantidad de personas debe ser como mínimo una (1) persona.');
                    }
                };
            },
        ];
    }

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
                                            Radio::make('type')
                                                ->label('Seleccione el tipo de cotización')
                                                ->live()
                                                ->inline()
                                                ->options([
                                                    'BASICO' => 'BÁSICA',
                                                    'DRESS-TAILOR' => 'DRESS-TAYLOR / PLANES A LA MEDIDA',
                                                ])
                                                ->required()
                                                ->default('BASICO'),

                                        ])->columnSpanFull(),
                                    Grid::make(4)
                                        ->schema([
                                            TextInput::make('code')
                                                ->label('Nro. de cotización')
                                                ->prefixIcon('heroicon-m-clipboard-document-check')
                                                ->default(function () {
                                                    if (CorporateQuote::max('id') == null) {
                                                        $parte_entera = 0;
                                                    } else {
                                                        $parte_entera = CorporateQuote::max('id');
                                                    }

                                                    return 'COT-CORP-000'.$parte_entera + 1;
                                                })
                                                ->disabled()
                                                ->dehydrated()
                                                ->maxLength(255),

                                        ])->columnSpanFull(),
                                    Grid::make(4)
                                        ->schema([
                                            TextInput::make('full_name')
                                                ->label('Nombre de la Empresa')
                                                ->prefixIcon('heroicon-m-user')
                                                ->required()
                                                ->validationMessages([
                                                    'required' => 'Campo requerido',
                                                ])
                                                ->maxLength(255)->afterStateUpdated(function (Set $set, $state) {
                                                    $set('full_name', strtoupper($state));
                                                })
                                                ->live(onBlur: true),

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
                                            TextInput::make('email')
                                                ->label('Correo electrónico')
                                                ->prefixIcon('heroicon-m-user')
                                                ->validationMessages([
                                                    'required' => 'Campo requerido',
                                                ])
                                                ->maxLength(255),
                                        ])->columnSpanFull(),
                                    Grid::make(1)
                                        ->schema([
                                            Textarea::make('observation_dress_tailor')
                                                ->label('Especificaciones de la cotización')
                                                ->helperText('Por favor, describa las especificaciones de la cotización de forma detallada del tipo de plan, beneficios, coberturas y rango de edades que debe estar asociados a la solicitud.')
                                                ->required()
                                                ->autosize()
                                                ->hidden(fn (Get $get) => $get('type') == 'BASICO'),

                                        ])->columnSpanFull(),
                                    Fieldset::make('Asignación de Cotización')
                                        ->schema([
                                            Select::make('code_agency_jerarchy')
                                                ->label('Agencia General')
                                                ->helperText('Si desea asignar la cotización a una agencia que pertenezca a su estructura, seleccione la agencia.')
                                                ->searchable()
                                                ->live()
                                                ->options(function () {
                                                    return Agency::where('owner_code', Auth::user()->code_agency)->get()->pluck('code', 'id');
                                                }),
                                            Select::make('agent_id')
                                                ->label('Agente')
                                                ->helperText('Si desea asignar la cotización a un agente que pertenezca a su estructura, seleccione la agente. Si el agente pertenece a una agencia general debe seleccionar la agencia y el agente')
                                                ->searchable()
                                                ->live()
                                                ->options(function () {
                                                    return Agent::where('owner_code', Auth::user()->code_agency)->pluck('name', 'id');
                                                }),
                                        ])->columnSpanFull()->columns(2),

                                    /**
                                     * Campos referenciales para jerarquia
                                     * -----------------------------------------------------------------
                                     */
                                    Hidden::make('ownerAccountManagers')->default(function () {
                                        $agency = Auth::user()->code_agency;

                                        return Agency::where('code', $agency)->first()->ownerAccountManagers;
                                    }),
                                    Hidden::make('status')->default('PRE-APROBADA'),
                                    Hidden::make('created_by')->default(Auth::user()->name),
                                    Hidden::make('code_agency')->default(function (Get $get) {
                                        if ($get('code_agency_jerarchy') == null) {
                                            return Auth::user()->code_agency;
                                        }
                                        if ($get('code_agency_jerarchy') != null) {
                                            return $get('code_agency');
                                        }
                                    }),
                                    Hidden::make('owner_code')->default(function (Get $get) {
                                        if ($get('code_agency_jerarchy') != null) {
                                            return Agency::where('owner_code', Auth::user()->code_agency)->first()->owner_code;
                                        }
                                        if ($get('code_agency_jerarchy') == null) {
                                            return Auth::user()->code_agency;
                                        }
                                    }),
                                    /**---------------------------------------------------------------- */
                                ])
                                ->columns(3)
                                ->columnSpanFull(),
                        ]),
                    Step::make('PLANES A COTIZAR')
                        ->hidden(fn (Get $get) => $get('type') == 'DRESS-TAILOR')
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
                                            $planesConBeneficios = Plan::where('type', 'BASICO')->where('status', 'ACTIVO')->get()->pluck('description', 'id');

                                            // agregar el plan livewire
                                            $planesConBeneficios->put('CM', 'COTIZACIÓN MULTIPLE');

                                            return $planesConBeneficios;
                                        })
                                        ->descriptions([
                                            1 => 'Edad: 0 a +99 años/ilimitado.',
                                            2 => 'Edad: 0 a 85 años.',
                                            3 => 'Edad: 0 a 85 años.',
                                            'CM' => 'Seleccione más de dos (2) planes.',
                                        ]),
                                ]),
                        ]),
                    Step::make('RANGO DE EDAD')
                        ->hidden(fn (Get $get) => $get('type') == 'DRESS-TAILOR')
                        ->description('Rango de edad y/o población:')
                        ->schema([
                            Section::make('age_range')
                                ->heading('¡Listo para el último paso! 🏁')
                                // ->description(new HtmlString(Blade::render(<<<BLADE
                                //         <div class="fi-section-header-description">
                                //             Por favor, selecciona el rango de edades de los beneficiarios. Al hacerlo, habrás finalizado la configuración principal de la cotización y estarás a un clic de generar el resultado final.
                                //             <br>
                                //             ¡Gracias por tu gran trabajo!
                                //         </div>
                                //     BLADE))
                                // )
                                ->schema([

                                    /**
                                     * REPETER PLAN INICIAl
                                     */
                                    Repeater::make('details_quote_plan_inicial')
                                        ->grid(4)
                                        ->label('Indique edad y número de afiliados al plan:')
                                        ->defaultItems(fn (Get $get) => AgeRange::where('plan_id', 1)->count())
                                        ->addable(false)
                                        ->rules(self::requireQuoteDetailsRule())
                                        ->hidden(function (Get $get) {
                                            if ($get('plan') == 1) {
                                                return false;
                                            }

                                            return true;
                                        })
                                        ->table([
                                            TableColumn::make('Rango de Edad')->width('150px'),
                                            TableColumn::make('Total de personas'),
                                        ])
                                        ->schema([
                                            Hidden::make('plan_id')->default(1),
                                            Radio::make('age_range_id')
                                                ->label(false)

                                                ->inLine()

                                                ->disableOptionWhen(function ($value, $state, Get $get) {
                                                    return collect($get('../*.age_range_id'))
                                                        ->reject(fn ($id) => $id == $state)
                                                        ->filter()
                                                        ->contains($value);
                                                })
                                                ->options(function (Get $get) {
                                                    return AgeRange::where('plan_id', 1)->pluck('range', 'id');
                                                })->columnSpan(4),
                                            TextInput::make('total_persons')
                                                ->placeholder('Cantidad de personas')
                                                ->numeric()
                                                ->minValue(1)
                                                ->validationMessages([
                                                    'min' => 'La cantidad de personas debe ser como mínimo una (1) persona.',
                                                ]),
                                        ])->columns(2),

                                    /**
                                     * REPETER PLAN IDEAL
                                     */
                                    Repeater::make('details_quote_plan_ideal')
                                        ->grid(2)
                                        ->label('Indique edad y número de afiliados al plan:')
                                        ->defaultItems(fn (Get $get) => AgeRange::where('plan_id', 2)->count())
                                        ->addable(false)
                                        ->rules(self::requireQuoteDetailsRule())
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
                                                        ->reject(fn ($id) => $id == $state)
                                                        ->filter()
                                                        ->contains($value);
                                                })
                                                ->options(function (Get $get) {
                                                    return AgeRange::where('plan_id', 2)->pluck('range', 'id');
                                                })->columnSpan(4),
                                            TextInput::make('total_persons')
                                                ->placeholder('Cantidad de personas')
                                                ->numeric()
                                                ->minValue(1)
                                                ->validationMessages([
                                                    'min' => 'La cantidad de personas debe ser como mínimo una (1) persona.',
                                                ]),
                                        ])->columns(4),

                                    /**
                                     * REPETER PLAN ESPECIAL
                                     */
                                    Repeater::make('details_quote_plan_especial')
                                        ->grid(2)
                                        ->label('Indique edad y número de afiliados al plan:')
                                        ->defaultItems(fn (Get $get) => AgeRange::where('plan_id', 3)->count())
                                        ->addable(false)
                                        ->rules(self::requireQuoteDetailsRule())
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
                                                        ->reject(fn ($id) => $id == $state)
                                                        ->filter()
                                                        ->contains($value);
                                                })
                                                ->options(function (Get $get) {
                                                    return AgeRange::where('plan_id', 3)->pluck('range', 'id');
                                                })->columnSpan(4),
                                            TextInput::make('total_persons')
                                                ->placeholder('Cantidad de personas')
                                                ->numeric()
                                                ->minValue(1)
                                                ->validationMessages([
                                                    'min' => 'La cantidad de personas debe ser como mínimo una (1) persona.',
                                                ]),
                                        ])->columns(2),

                                    /**
                                     * REPETER PLAN MULTIPLE
                                     */
                                    Repeater::make('details_quote')
                                        ->label('Indique los planes, la edad y número de personas:')
                                        ->addActionLabel('Añadir plan')
                                        ->rules(self::requireQuoteDetailsRule())
                                        ->hidden(function (Get $get) {
                                            if ($get('plan') == 'CM') {
                                                return false;
                                            }

                                            return true;
                                        })
                                        ->schema([
                                            Select::make('plan_id')
                                                ->label('Seleccione una opción y añadir plan:')
                                                // ->inLine()
                                                ->live()
                                                ->options(function (Get $get) {

                                                    $planes = Plan::where('status', 'ACTIVO')->where('type', 'BASICO')->get()->pluck('description', 'id')->toArray();
                                                    $agenciaId = Agency::where('code', Auth::user()->code_agency)->first()->id;
                                                    $planesAsignados = AgencyPlan::where('agency_id', $agenciaId)->get()->pluck('description', 'plan_id')->toArray();

                                                    $planesUnidos = $planes + $planesAsignados;
                                                    // Log::info($planesUnidos);

                                                    return $planesUnidos;
                                                    // return Plan::where('type', 'BASICO')->where('status', 'ACTIVO')->pluck('description', 'id');
                                                }),
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
                                                        ->reject(fn ($id) => $id == $state)
                                                        ->filter()
                                                        ->contains($value);
                                                })
                                                ->validationMessages([
                                                    'required' => 'Campo Requerido',
                                                ])
                                                ->preload(),
                                            TextInput::make('total_persons')
                                                ->label('Cantidad de personas')
                                                ->placeholder('Cantidad de personas')
                                                ->numeric()
                                                ->minValue(1)
                                                ->validationMessages([
                                                    'min' => 'La cantidad de personas debe ser como mínimo una (1) persona.',
                                                ]),
                                        ])->columns(3),
                                ]),
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
                            <span>Calculando Cotización y Generando PDF...</span>
                        </span>
                    </x-filament::button>
                BLADE)))
                    ->hiddenOn('edit')
                    ->columnSpanFull(),
            ]);
    }
}
