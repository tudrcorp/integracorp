<?php

namespace App\Filament\Business\Resources\Plans\Schemas;

use App\Models\AgeRange;
use App\Models\Benefit;
use App\Models\BusinessUnit;
use App\Models\Coverage;
use App\Models\Limit;
use App\Models\Plan;
use App\Support\PlanCreationPersistence;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Icon;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\GridDirection;
use Filament\Support\Enums\TextSize;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class PlanForm
{
    private const IOS_SECTION_CLASS = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private static function currentPlanId(mixed $livewire): ?int
    {
        if (! is_object($livewire) || ! method_exists($livewire, 'getRecord')) {
            return null;
        }

        $record = $livewire->getRecord();

        return $record instanceof Plan ? (int) $record->getKey() : null;
    }

    /**
     * @return array<int|string, mixed>
     */
    private static function availableCatalogCoverages(?int $planId): array
    {
        return Coverage::query()
            ->where('status', 'ACTIVO')
            ->where(function ($query) use ($planId): void {
                $query->whereNull('plan_id');

                if ($planId !== null) {
                    $query->orWhere('plan_id', $planId);
                }
            })
            ->pluck('price', 'id')
            ->all();
    }

    /**
     * @return array<int|string, mixed>
     */
    private static function availableCatalogAgeRanges(?int $planId): array
    {
        return AgeRange::query()
            ->where('status', 'ACTIVO')
            ->where(function ($query) use ($planId): void {
                $query->whereNull('plan_id')->orWhere('plan_id', 0);

                if ($planId !== null) {
                    $query->orWhere('plan_id', $planId);
                }
            })
            ->pluck('range', 'id')
            ->all();
    }

    private static function isPackageMode(Get $get): bool
    {
        return (bool) ($get('is_package') ?? false);
    }

    /**
     * Etiqueta del ítem del repetidor de coberturas generales: cobertura(s) elegida(s) y rangos de edad configurados.
     *
     * @param  array<string, mixed>  $state
     */
    private static function generalCoverageRepeaterItemLabel(array $state): string
    {
        $rawIds = $state['coverage_id'] ?? null;

        $ids = match (true) {
            is_array($rawIds) => array_values(array_filter($rawIds, static fn ($id): bool => filled($id))),
            filled($rawIds) => [(string) $rawIds],
            default => [],
        };

        if ($ids === []) {
            return 'Nueva cobertura general — seleccione una o más coberturas';
        }

        $coverageLabels = [];
        foreach ($ids as $id) {
            $coverage = Coverage::find($id);
            if ($coverage !== null) {
                $coverageLabels[] = 'US $'.number_format((float) $coverage->price, 2).' (#'.$coverage->id.')';
            } else {
                $coverageLabels[] = 'ID '.$id;
            }
        }

        $line = count($coverageLabels) > 1
            ? 'Coberturas: '.implode(' · ', $coverageLabels)
            : 'Cobertura: '.$coverageLabels[0];

        $ageRates = $state['age_rates'] ?? [];
        $rangeLabels = [];
        if (is_array($ageRates)) {
            foreach ($ageRates as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $rangeId = $row['age_range_id'] ?? null;
                if (! filled($rangeId)) {
                    continue;
                }
                $range = AgeRange::find($rangeId);
                $rangeLabels[] = $range?->range !== null && $range->range !== ''
                    ? $range->range.' años'
                    : (string) $rangeId;
            }
        }

        if ($rangeLabels !== []) {
            $line .= ' — Rangos: '.implode(', ', array_unique($rangeLabels));
        }

        return $line;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                // SECCIÓN 1: INFORMACIÓN GENERAL DEL PLAN
                Section::make('Configuración General del Plan')
                    ->description('Defina la identidad principal y el tipo de plan que está creando.')
                    ->icon('heroicon-o-identification')
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('code')
                                    ->label('Código del plan')
                                    ->prefixIcon('heroicon-m-clipboard-document-check')
                                    ->default(fn (): string => PlanCreationPersistence::generatePlanCode())
                                    ->disabled()
                                    ->dehydrated()
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(1),

                                TextInput::make('description')
                                    ->label('Nombre del Plan')
                                    ->placeholder('Ej: Plan Platinum Global')
                                    ->required()
                                    ->columnSpan(1),

                                Select::make('business_unit_id')
                                    ->label('Unidad de negocio')
                                    ->options(fn (): array => BusinessUnit::query()->pluck('definition', 'id')->all())
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->default(1),

                                Select::make('type')
                                    ->label('Categoría del Plan')
                                    ->options([
                                        'BASICO' => 'BASICO',
                                        'DRESS-TAILOR' => 'DRESS-TAILOR',
                                    ])
                                    ->default('BASICO')
                                    ->required(),
                                Hidden::make('status')->default('ACTIVO'),
                                Hidden::make('created_by')->default(Auth::user()->name),
                            ]),
                    ])->collapsible()->columnSpanFull(),

                // Modo paquete vs armado detallado (UX tipo iOS)
                Section::make('¿Cómo desea crear este plan?')
                    ->description('Paquete: elija varios beneficios y luego defina coberturas globales por edad. Detallado: agregue cada beneficio con sus coberturas y rangos de edad.')
                    ->icon('heroicon-o-squares-2x2')
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make(1)
                            ->extraAttributes([
                                'class' => 'gap-4',
                            ])
                            ->schema([
                                Grid::make(1)
                                    ->extraAttributes([
                                        'class' => 'rounded-2xl border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5',
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 12])
                                            ->schema([
                                                Grid::make(1)
                                                    ->columnSpan(['default' => 1, 'lg' => 8])
                                                    ->schema([
                                                        Text::make('Crear como paquete')
                                                            ->weight('semibold')
                                                            ->size(TextSize::Large)
                                                            ->color('gray'),
                                                        Text::make('Un paquete agrupa beneficios y aplica coberturas comunes por edad. Activa el interruptor para configurar crear un paquete.')
                                                            ->color('gray')
                                                            ->size(TextSize::Small)
                                                            ->extraAttributes([
                                                                'class' => 'mt-1 max-w-prose leading-relaxed',
                                                            ]),
                                                    ]),
                                                Toggle::make('is_package')
                                                    ->label('Crear como paquete')
                                                    ->hiddenLabel()
                                                    ->inline(false)
                                                    ->live()
                                                    ->default(false)
                                                    ->onColor('success')
                                                    ->offColor('gray')
                                                    ->columnSpan(['default' => 1, 'lg' => 4])
                                                    ->extraFieldWrapperAttributes([
                                                        'class' => 'flex items-start justify-end lg:pt-1',
                                                    ]),
                                            ]),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Beneficios del paquete')
                    ->description('Seleccione los beneficios que incluirá este paquete. Luego configure las coberturas en la siguiente sección.')
                    ->icon('heroicon-o-queue-list')
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->visible(fn (Get $get): bool => self::isPackageMode($get))
                    ->schema([
                        CheckboxList::make('package_benefit_ids')
                            ->label('Beneficios incluidos')
                            ->options(fn (): array => Benefit::query()->orderBy('description')->pluck('description', 'id')->all())
                            ->searchable()
                            ->columns(2)
                            ->gridDirection(GridDirection::Row)
                            ->bulkToggleable()
                            ->required(fn (Get $get): bool => self::isPackageMode($get))
                            ->extraFieldWrapperAttributes([
                                'class' => 'rounded-2xl border border-slate-200/70 bg-white/70 p-3 dark:border-white/10 dark:bg-white/5 sm:p-4',
                            ]),
                    ])
                    ->columnSpanFull(),

                // SECCIÓN 2: ESTRUCTURA MAESTRA (BENEFICIOS -> COBERTURAS -> EDADES)
                Section::make('Arquitectura de Beneficios y Tarifas')
                    ->description('Agregue beneficios y, si aplica, coberturas por beneficio con rangos de edad. Use este modo cuando no esté creando un paquete.')
                    ->icon('heroicon-o-puzzle-piece')
                    ->visible(fn (Get $get): bool => ! self::isPackageMode($get))
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
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
                                                    ->action(function (array $data, Set $set): void {
                                                        $benefit = Benefit::query()->create([
                                                            'description' => $data['description'],
                                                            'status' => 'ACTIVO',
                                                            'created_by' => Auth::user()->name,
                                                        ]);
                                                        $set('benefit_id', $benefit->id);
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
                                                    ->action(function (array $data, Set $set): void {
                                                        $limit = Limit::query()->create([
                                                            'description' => $data['description'],
                                                            'cuota' => $data['cuota'],
                                                            'status' => 'ACTIVO',
                                                            'created_by' => Auth::user()->name,
                                                        ]);
                                                        $set('benefit_pvp', $limit->id);
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
                                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
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
                                                            ->action(function (array $data, Set $set): void {
                                                                $coverage = Coverage::query()->create([
                                                                    'price' => $data['price'],
                                                                    'status' => 'ACTIVO',
                                                                    'created_by' => Auth::user()->name,
                                                                ]);
                                                                $set('coverage_id', $coverage->id);
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
                                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                                    ->required()
                                                    ->belowContent(Schema::between([
                                                        Action::make('create_age_range')
                                                            ->label('Crear Rango de Edad')
                                                            ->icon('heroicon-o-plus')
                                                            ->color('success')
                                                            ->modal()
                                                            ->form([
                                                                Fieldset::make('Formulario para Crear Rango de Edad')
                                                                    ->schema([
                                                                        TextInput::make('range')
                                                                            ->label('Rango de Edad')
                                                                            ->required(),
                                                                        TextInput::make('age_init')
                                                                            ->label('Edad Inicial')
                                                                            ->required()
                                                                            ->numeric(),
                                                                        TextInput::make('age_end')
                                                                            ->label('Edad Final')
                                                                            ->required()
                                                                            ->numeric(),
                                                                        Hidden::make('status')->default('ACTIVO'),
                                                                        Hidden::make('created_by')->default(Auth::user()->name),
                                                                    ])->columnSpanFull(),
                                                            ])
                                                            ->action(function (array $data, Set $set): void {
                                                                $ageRange = AgeRange::query()->create([
                                                                    'range' => $data['range'],
                                                                    'age_init' => $data['age_init'],
                                                                    'age_end' => $data['age_end'],
                                                                    'status' => 'ACTIVO',
                                                                    'created_by' => Auth::user()->name,
                                                                ]);
                                                                $set('age_range_id', $ageRange->id);
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

                // COBERTURAS A NIVEL PLAN (sin pasar por beneficios)
                Section::make('Coberturas generales del plan')
                    ->description('Defina coberturas globales con rangos de edad y tarifas cuando trabaja en modo paquete. Si no está en modo paquete, use la arquitectura por beneficio.')
                    ->icon('heroicon-o-globe-alt')
                    ->visible(fn (Get $get): bool => self::isPackageMode($get))
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Repeater::make('general_coverages')
                            ->label('Coberturas generales')
                            ->addActionLabel('Agregar cobertura general')
                            ->collapsible()
                            ->cloneable()
                            ->defaultItems(1)
                            ->itemLabel(
                                fn (array $state): ?string => self::generalCoverageRepeaterItemLabel($state)
                            )
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        Select::make('coverage_id')
                                            ->label('Cobertura')
                                            ->options(fn (mixed $livewire): array => self::availableCatalogCoverages(self::currentPlanId($livewire)))
                                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                            ->belowContent(Schema::between([
                                                Flex::make([
                                                    Icon::make(Heroicon::InformationCircle)
                                                        ->grow(false),
                                                    'Para vincular una cobertura debe existir en catálogo o créala aquí.',
                                                ]),
                                                Action::make('create_general_plan_coverage')
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
                                                        $coverage = Coverage::create([
                                                            'price' => $data['price'],
                                                            'status' => 'ACTIVO',
                                                            'created_by' => Auth::user()->name,
                                                        ]);

                                                        return $coverage;
                                                    }),
                                            ]))
                                            ->live()
                                            ->preload()
                                            ->searchable(),
                                    ]),

                                Repeater::make('age_rates')
                                    ->label('Rangos de edad y tarifa (esta cobertura general)')
                                    ->addActionLabel('Agregar rango de edad y tarifa')
                                    ->itemLabel(
                                        fn (array $state): ?string => isset($state['age_range_id'])
                                            ? 'Rango: '.(AgeRange::find($state['age_range_id'])?->range ?? '—').' años'
                                            : 'Nuevo rango de edad y tarifa'
                                    )
                                    ->columns(2)
                                    ->grid(2)
                                    ->schema([
                                        Select::make('age_range_id')
                                            ->label('Rango de Edad')
                                            ->options(fn (mixed $livewire): array => self::availableCatalogAgeRanges(self::currentPlanId($livewire)))
                                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                            ->belowContent(Schema::between([
                                                Action::make('create_general_plan_age_range')
                                                    ->label('Crear Rango de Edad')
                                                    ->icon('heroicon-o-plus')
                                                    ->color('success')
                                                    ->modal()
                                                    ->form([
                                                        Fieldset::make('Formulario para Crear Rango de Edad')
                                                            ->schema([
                                                                TextInput::make('range')
                                                                    ->label('Rango de Edad')
                                                                    ->columns(8)
                                                                    ->required(),
                                                                TextInput::make('age_init')
                                                                    ->label('Edad Inicial')
                                                                    ->columns(4)
                                                                    ->required()
                                                                    ->numeric(),
                                                                TextInput::make('age_end')
                                                                    ->label('Edad Final')
                                                                    ->columns(4)
                                                                    ->required()
                                                                    ->numeric(),
                                                                Hidden::make('status')->default('ACTIVO'),
                                                                Hidden::make('created_by')->default(Auth::user()->name),
                                                            ])->columnSpanFull(),
                                                    ])
                                                    ->action(function (array $data) {
                                                        $ageRange = AgeRange::create([
                                                            'range' => $data['range'],
                                                            'age_init' => $data['age_init'],
                                                            'age_end' => $data['age_end'],
                                                            'status' => 'ACTIVO',
                                                            'created_by' => Auth::user()->name,
                                                        ]);

                                                        return $ageRange;
                                                    }),

                                            ]))
                                            ->live()
                                            ->preload()
                                            ->required()
                                            ->searchable(),

                                        TextInput::make('rate')
                                            ->label('Tarifa/Prima ($)')
                                            ->numeric()
                                            ->prefix('$')
                                            ->required(),
                                    ])
                                    ->defaultItems(1),
                            ])
                            ->grid(2)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->columnSpanFull(),
            ]);
    }
}
