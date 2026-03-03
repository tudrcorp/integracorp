<?php

namespace App\Filament\Business\Resources\Plans\Schemas;

use App\Models\AgeRange;
use App\Models\Benefit;
use App\Models\Coverage;
use App\Models\Limit;
use App\Models\Plan;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Icon;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                // SECCIÓN 1: INFORMACIÓN GENERAL DEL PLAN
                Section::make('Configuración General del Plan')
                    ->description('Defina la identidad principal y el tipo de plan que está creando.')
                    ->icon('heroicon-o-identification')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('plan_name')
                                    ->label('Nombre del Plan')
                                    ->placeholder('Ej: Plan Platinum Global')
                                    ->required()
                                    ->columnSpan(1),

                                Select::make('category')
                                    ->label('Categoría del Plan')
                                    ->options([
                                        'BASICO' => 'BASICO',
                                        'DRESS-TYLOR' => 'DRESS-TYLOR',
                                    ])
                                    ->required(),

                                TextInput::make('description')
                                    ->label('Descripción Corta')
                                    ->placeholder('Resumen de lo que incluye este plan...')
                                    ->columnSpanFull(),
                            ]),
                    ])->collapsible()->columnSpanFull(),

                // SECCIÓN 2: ESTRUCTURA MAESTRA (BENEFICIOS -> COBERTURAS -> EDADES)
                Section::make('Arquitectura de Beneficios y Tarifas')
                    ->description('Agregue beneficios y desglose sus coberturas y rangos de edad con sus respectivas tarifas.')
                    ->icon('heroicon-o-puzzle-piece')
                    ->schema([
                        Repeater::make('benefits')
                            ->label('Lista de Beneficios')
                            ->addActionLabel('Añadir Nuevo Beneficio')
                            ->collapsible()
                            ->cloneable()
                            ->itemLabel(
                                fn (array $state): ?string => Benefit::find($state['benefit_id'] ?? null)?->description ?? 'Nuevo Beneficio'
                            )
                            ->schema([
                                // Selección del Beneficio
                                Grid::make(2)
                                    ->schema([

                                        Select::make('benefit_id')
                                            ->label('Seleccionar Beneficio Base')
                                            ->options(Benefit::all()->pluck('description', 'id'))
                                            ->searchable()
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(function (Set $set, $state) {
                                                if ($state == null) {
                                                    $set('benefit_pvp', 0);

                                                    return;
                                                }
                                                $benefit = Benefit::find($state);
                                                $set('benefit_pvp', Limit::where('id', $benefit->limit_id)->first()->cuota ?? 0);
                                            })
                                            ->hint(function (Get $get) {
                                                $benefit = Benefit::find($get('benefit_id'));

                                                return 'Cuota: $'.number_format((float) ($benefit?->pvp ?? 0), 2);
                                            })
                                            ->hintColor('primary')
                                            ->belowContent(Schema::between([
                                                Flex::make([
                                                    Icon::make(Heroicon::InformationCircle)
                                                        ->grow(false),
                                                    'No existe en lista?, Créalo aquí!.',
                                                ]),
                                                Action::make('create_benefit')
                                                    ->label('Crear Beneficio')
                                                    ->icon('heroicon-o-plus')
                                                    ->color('success')
                                                    ->modal()
                                                    ->form([
                                                        TextInput::make('description')
                                                            ->label('Descripción')
                                                            ->required(),
                                                        Hidden::make('status')->default('ACTIVO'),
                                                        Hidden::make('created_by')->default(Auth::user()->name),
                                                    ])
                                                    ->action(function (array $data) {
                                                        dd($data);
                                                    }),
                                            ])),

                                        Select::make('benefit_pvp')
                                            ->options(Limit::all()->pluck('description', 'id'))
                                            ->label('Límite del Beneficio')
                                            ->required()
                                            ->searchable()
                                            ->belowContent(Schema::between([
                                                Flex::make([
                                                    Icon::make(Heroicon::InformationCircle)
                                                        ->grow(false),
                                                    'No existe en lista?, Créalo aquí!.',
                                                ]),
                                                Action::make('create_limit')
                                                    ->label('Crear Limite')
                                                    ->icon('heroicon-o-plus')
                                                    ->color('success')
                                                    ->modal()
                                                    ->form([
                                                        Fieldset::make('Formulario para Crear Limite')
                                                            ->schema([
                                                                TextInput::make('description')
                                                                    ->label('Descripción del Limite')
                                                                    ->columns(8)
                                                                    ->required(),
                                                                TextInput::make('cuota')
                                                                    ->label('Cuota del Limite')
                                                                    ->columns(4)
                                                                    ->required()
                                                                    ->numeric(),
                                                                Hidden::make('status')->default('ACTIVO'),
                                                                Hidden::make('created_by')->default(Auth::user()->name),
                                                            ])->columnSpanFull(),
                                                    ])
                                                    ->action(function (array $data) {
                                                        dd($data);
                                                    }),

                                            ])),

                                    ]),

                                // REPETIDOR ANIDADO: COBERTURAS (Relación 1 a N con Límites/Coberturas)
                                Repeater::make('coverages')
                                    ->label('Configuración de Coberturas')
                                    ->addActionLabel('Agregar Cobertura')
                                    ->collapsible()
                                    ->collapsed()
                                    ->itemLabel(
                                        fn (array $state): ?string => 'US $'.Coverage::find($state['coverage_id'] ?? null)?->price ?? 'Nueva Cobertura'
                                    )
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                Select::make('coverage_id')
                                                    ->label('Cobertura')
                                                    ->options(Coverage::all()->pluck('price', 'id'))
                                                    ->belowContent(Schema::between([
                                                        Flex::make([
                                                            Icon::make(Heroicon::InformationCircle)
                                                                ->grow(false),
                                                            'Para vincular una cobertura debes crearla aquí.',
                                                        ]),
                                                        Action::make('create_coverage')
                                                            ->label('Crear Cobertura')
                                                            ->icon('heroicon-o-plus')
                                                            ->color('success')
                                                            ->modal()
                                                            ->modalWidth(Width::Medium)
                                                            ->form([
                                                                Fieldset::make('Formulario para Crear Cobertura')
                                                                    ->schema([
                                                                        TextInput::make('price')
                                                                            ->label('Valor de la Cobertura')
                                                                            ->numeric()
                                                                            ->prefix('$')
                                                                            ->required(),
                                                                        Hidden::make('status')->default('ACTIVO'),
                                                                        Hidden::make('created_by')->default(Auth::user()->name),
                                                                    ])->columnSpanFull()->columns(1),
                                                            ])
                                                            ->action(function (array $data) {
                                                                dd($data);
                                                            }),
                                                    ]))
                                                    ->live()
                                                    ->required()
                                                    ->searchable(),
                                            ]),

                                        // REPETIDOR ANIDADO: RANGOS DE EDAD Y TARIFAS
                                        Repeater::make('age_rates')
                                            ->label('Rango de Edad y Tarifa para esta Cobertura')
                                            ->addActionLabel('Agregar Rango de Edad y Tarifa')
                                            ->itemLabel(
                                                fn (array $state): ?string => 'Rango: '.AgeRange::find($state['age_range_id'] ?? null)?->range.' años' ?? 'Nuevo Rango de Edad y Tarifa'
                                            )
                                            ->columns(2)
                                            ->grid(2)
                                            ->schema([
                                                Select::make('age_range_id')
                                                    ->label('Rango de Edad')
                                                    ->live()
                                                    ->options(AgeRange::all()->pluck('range', 'id'))
                                                    ->required()
                                                    ->belowContent(Schema::between([
                                                        Action::make('create_age_range')
                                                            ->label('Crear Rango de Edad')
                                                            ->icon('heroicon-o-plus')
                                                            ->color('success')
                                                            ->modal()
                                                            ->form([
                                                                Fieldset::make('Formulario para Crear Limite')
                                                                    ->schema([
                                                                        TextInput::make('description')
                                                                            ->label('Descripción del Limite')
                                                                            ->columns(8)
                                                                            ->required(),
                                                                        TextInput::make('cuota')
                                                                            ->label('Cuota del Limite')
                                                                            ->columns(4)
                                                                            ->required()
                                                                            ->numeric(),
                                                                        Hidden::make('status')->default('ACTIVO'),
                                                                        Hidden::make('created_by')->default(Auth::user()->name),
                                                                    ])->columnSpanFull(),
                                                            ])
                                                            ->action(function (array $data) {
                                                                dd($data);
                                                            }),

                                                    ])),

                                                TextInput::make('rate')
                                                    ->label('Tarifa/Prima ($)')
                                                    ->numeric()
                                                    ->prefix('$')
                                                    ->required(),
                                            ])
                                            ->defaultItems(1),
                                    ])
                                    ->grid(2)
                                    ->defaultItems(1)
                                    ->columnSpan(1),
                            ])
                            // ->grid(2)
                            ->columnSpanFull(),
                    ])->collapsible()->columnSpanFull(),

                // SECCIÓN 3: RESUMEN Y AJUSTES GLOBALES
                Section::make('Ajustes Finales')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->compact()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('tax_percent')
                                    ->label('Impuestos (%)')
                                    ->numeric()
                                    ->default(0)
                                    ->suffix('%'),

                                TextInput::make('admin_fee')
                                    ->label('Gasto Administrativo ($)')
                                    ->numeric()
                                    ->default(0)
                                    ->prefix('$'),

                                ToggleButtons::make('status')
                                    ->label('Estado Inicial')
                                    ->inline()
                                    ->options([
                                        'draft' => 'Borrador',
                                        'active' => 'Publicar',
                                    ])
                                    ->colors([
                                        'draft' => 'gray',
                                        'active' => 'success',
                                    ])
                                    ->default('draft'),
                            ]),
                    ])->collapsible(),

            ]);
    }
}
