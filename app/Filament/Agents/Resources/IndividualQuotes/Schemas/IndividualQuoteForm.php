<?php

namespace App\Filament\Agents\Resources\IndividualQuotes\Schemas;

use App\Models\Plan;
use App\Models\Agent;
use App\Models\State;
use App\Models\Agency;
use App\Models\Region;
use App\Models\AgeRange;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use App\Models\IndividualQuote;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Log;
use Filament\Forms\Components\Radio;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Blade;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Wizard;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
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
                        ->description('Datos del Cliente')
                        // ->icon(Heroicon::User)
                        // ->completedIcon(Heroicon::Check)
                        ->schema([
                            Section::make('data_client')
                            ->heading('¡Bienvenido/a de nuevo! 👋 ')
                            ->description('Estás a punto de comenzar a crear una nueva cotización, por favor ingresa la información del cliente para personalizarla. ¡Puede ver el avance del proceso en la barra de estatus!')
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
                                TextInput::make('full_name')
                                    ->label('Nombre Completo')
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
                                //fecha de nacimiento es un campo de fecha y no puede permitir fechas anteriores al dia actual
    
                                TextInput::make('email')
                                    ->label('Email')
                                    ->prefixIcon('heroicon-m-user')
                                    ->validationMessages([
                                        'required' => 'Campo requerido',
                                    ])
                                    ->maxLength(255),
                                Hidden::make('status')->default('PRE-APROBADA'),
                                Hidden::make('created_by')->default(Auth::user()->name),
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
                                        
                                    }else{
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
                            ->columns(3)                            
                            ->columnSpanFull()
                            
                        ]),
                    Step::make('PLANES A COTIZAR')
                        ->description('Plan(es) que desea cotizar:')
                        // ->icon(Heroicon::Swatch)
                        // ->completedIcon(Heroicon::Check)
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
                                        $planesConBeneficios = Plan::join('benefit_plans', 'plans.id', '=', 'benefit_plans.plan_id')
                                            ->select('plans.id as plan_id', 'plans.description as description')
                                            ->distinct() // Asegurarse de que no haya duplicados
                                            ->get()
                                            ->pluck('description', 'plan_id');
                                        //agregar el plan livewire
                                        $planesConBeneficios->put('CM', 'COTIZACIÓN MULTIPLE');
    
                                        return $planesConBeneficios;
                                    })
                                    ->descriptions([
                                        1    => 'Edad: 0 a 111 años/ilimitado.',
                                        2    => 'Edad: 0 a 85 años.',
                                        3    => 'Edad: 0 a 85 años.',
                                        'CM' => 'Seleccione más de dos (2) planes.'
                                    ])
                            ])
                        ]),
                    Step::make('RANGO DE EDAD')
                        ->description('Rango de edad y/o población:')
                        // ->icon(Heroicon::AdjustmentsVertical)
                        // ->completedIcon(Heroicon::Check)
                        ->schema([
                            Section::make('age_range')
                            ->heading('¡Listo para el ultimo paso! 🏁')
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
                                                Log::info($get('plan'));
                                                return Plan::join('benefit_plans', 'plans.id', '=', 'benefit_plans.plan_id')
                                                    ->select('plans.id as plan_id', 'plans.description as description')
                                                    ->distinct() // Asegurarse de que no haya duplicados
                                                    ->get()
                                                    ->pluck('description', 'plan_id');
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