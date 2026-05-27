<?php

namespace App\Filament\Business\Resources\CorporateQuotes\Schemas;

use App\Http\Controllers\UtilsController;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgeRange;
use App\Models\CorporateQuote;
use App\Models\Plan;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CorporateQuoteForm
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('corporateQuoteFormTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('SOLICITANTE')
                            ->icon(Heroicon::OutlinedUserCircle)
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
                                                        // 'DRESS-TAILOR' => 'DRESS-TAYLOR / PLANES A LA MEDIDA',
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
                                                    ->maxLength(255)
                                                    ->afterStateUpdatedJs(<<<'JS'
                                                    $set('full_name', $state.toUpperCase());
                                                JS),

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
                                                    ->label('Correo Electrónico')
                                                    ->email()
                                                    ->rule('regex:/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/')
                                                    ->validationMessages([
                                                        'required' => 'Campo requerido',
                                                        'email' => 'El correo no es valido',
                                                        'regex' => 'El correo no debe contener mayúsculas, espacios, ñ, ni caracteres especiales no permitidos.',
                                                    ]),
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
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('PLANES A COTIZAR')
                            ->icon(Heroicon::OutlinedClipboardDocumentList)
                            ->hidden(fn (Get $get) => $get('type') == 'DRESS-TAILOR')
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
                        Tab::make('RANGO DE EDAD')
                            ->icon(Heroicon::OutlinedUsers)
                            ->hidden(fn (Get $get) => $get('type') == 'DRESS-TAILOR')
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
                                                    ->placeholder('Cantidad de personas'),
                                            ])->columns(2),

                                        /**
                                         * REPETER PLAN IDEAL
                                         */
                                        Repeater::make('details_quote_plan_ideal')
                                            ->grid(2)
                                            ->label('Indique edad y número de afiliados al plan:')
                                            ->defaultItems(fn (Get $get) => AgeRange::where('plan_id', 2)->count())
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
                                                            ->reject(fn ($id) => $id == $state)
                                                            ->filter()
                                                            ->contains($value);
                                                    })
                                                    ->options(function (Get $get) {
                                                        return AgeRange::where('plan_id', 2)->pluck('range', 'id');
                                                    })->columnSpan(4),
                                                TextInput::make('total_persons')
                                                    ->placeholder('Cantidad de personas'),
                                            ])->columns(4),

                                        /**
                                         * REPETER PLAN ESPECIAL
                                         */
                                        Repeater::make('details_quote_plan_especial')
                                            ->grid(2)
                                            ->label('Indique edad y número de afiliados al plan:')
                                            ->defaultItems(fn (Get $get) => AgeRange::where('plan_id', 3)->count())
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
                                                            ->reject(fn ($id) => $id == $state)
                                                            ->filter()
                                                            ->contains($value);
                                                    })
                                                    ->options(function (Get $get) {
                                                        return AgeRange::where('plan_id', 3)->pluck('range', 'id');
                                                    })->columnSpan(4),
                                                TextInput::make('total_persons')
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
                                                Select::make('plan_id')
                                                    ->label('Seleccione el plan:')
                                                    // ->inLine()
                                                    ->live()
                                                    ->options(function (Get $get) {
                                                        Log::info($get('plan'));

                                                        return Plan::where('status', 'ACTIVO')->get()->pluck('description', 'id');
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
                                                    ->numeric(),
                                            ])->columns(3),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
