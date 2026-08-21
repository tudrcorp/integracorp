<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Plans\Schemas;

use App\Enums\PlanPricingMode;
use App\Models\Benefit;
use App\Models\BusinessUnit;
use App\Models\Plan;
use App\Support\Plans\PlanCodeGenerator;
use App\Support\Plans\PlanStructureMatrix;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Enums\GridDirection;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Asistente de armado de planes.
 *
 * El orden de los pasos no es decorativo: las coberturas del plan son las
 * columnas de las dos matrices que vienen después (costos límite por beneficio
 * y tarifas por rango de edad), así que tienen que existir antes de que el
 * analista tenga dónde escribir.
 *
 *   1. Identidad y forma de cobro
 *   2. Coberturas del plan            (solo si cobra por cobertura)
 *   3. Beneficios y costos límite
 *   4. Rangos de edad y tarifas
 *
 * Las matrices no se arman con un schema dinámico sino con repetidores de filas
 * controladas (`addable(false)`): al salir del paso de coberturas se sincroniza
 * una celda por cobertura, emparejando por `coverage_key` para no perder lo ya
 * escrito ni correr los valores de columna cuando se agrega o quita un monto.
 *
 * @see \App\Support\Plans\PlanStructureMatrix
 * @see \App\Support\Plans\PlanStructurePersistence
 */
class PlanWizardForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Wizard::make([
                self::identityStep(),
                self::coveragesStep(),
                self::benefitsStep(),
                self::ageRangesStep(),
            ])
                ->skippable(false)
                ->persistStepInQueryString('paso')
                ->columnSpanFull(),
        ]);
    }

    private static function identityStep(): Step
    {
        return Step::make('Identidad')
            ->label('Identidad del plan')
            ->description('Nombre, unidad de negocio y forma de cobro')
            ->icon(Heroicon::Identification)
            ->schema([
                Grid::make(2)->schema([
                    TextInput::make('code')
                        ->label('Código del plan')
                        ->prefixIcon('heroicon-m-clipboard-document-check')
                        ->default(fn (): string => PlanCodeGenerator::next())
                        ->disabled()
                        ->dehydrated()
                        ->required()
                        ->maxLength(255),

                    TextInput::make('description')
                        ->label('Nombre del plan')
                        ->placeholder('Ej: Plan Platinum Global')
                        ->required()
                        ->maxLength(255),

                    Select::make('business_unit_id')
                        ->label('Unidad de negocio')
                        ->options(fn (): array => BusinessUnit::query()->pluck('definition', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->default(1),

                    Select::make('type')
                        ->label('Categoría del plan')
                        ->options([
                            'BASICO' => 'BASICO',
                            'DRESS-TAILOR' => 'DRESS-TAILOR',
                        ])
                        ->default('BASICO')
                        ->required(),
                ]),

                Radio::make('pricing_mode')
                    ->label('¿Cómo se arma este plan?')
                    ->helperText('Esta elección define los pasos siguientes. Se puede cambiar mientras el plan no esté guardado.')
                    ->options(PlanPricingMode::options())
                    ->descriptions(array_map(
                        static fn (PlanPricingMode $mode): string => $mode->description(),
                        collect(PlanPricingMode::cases())
                            ->keyBy(static fn (PlanPricingMode $mode): string => $mode->value)
                            ->all(),
                    ))
                    ->default(PlanPricingMode::Coberturas->value)
                    ->required()
                    ->live()
                    ->columnSpanFull(),

                Hidden::make('status')->default('ACTIVO'),
                Hidden::make('created_by')->default(fn (): string => (string) (Auth::user()?->name ?? 'sistema')),
                Hidden::make('structure_version')->default(Plan::STRUCTURE_VERSION_WIZARD),
            ]);
    }

    /**
     * Paso 2. Al salir se sincronizan las dos matrices contra las coberturas
     * recién definidas, que es lo que hace que los pasos 3 y 4 tengan columnas.
     */
    private static function coveragesStep(): Step
    {
        return Step::make('Coberturas')
            ->label('Coberturas del plan')
            ->description('Los montos que cubre el plan')
            ->icon(Heroicon::Banknotes)
            ->visible(fn (Get $get): bool => self::usesCoverages($get))
            ->afterValidation(function (Get $get, Set $set): void {
                self::syncMatrices($get, $set);
            })
            ->schema([
                Repeater::make('plan_coverages')
                    ->label('Montos de cobertura')
                    ->helperText('Cada monto será una columna en los costos límite y en las tarifas por edad. Ej: 1.000, 3.000, 5.000, 10.000.')
                    ->addActionLabel('Agregar cobertura')
                    ->defaultItems(1)
                    ->minItems(1)
                    ->reorderable(false)
                    ->itemLabel(fn (array $state): string => is_numeric($state['price'] ?? null)
                        ? PlanStructureMatrix::columnLabel((float) $state['price'])
                        : 'Nueva cobertura')
                    ->schema([
                        Hidden::make('id'),
                        Hidden::make('coverage_key')
                            ->default(fn (): string => (string) Str::uuid()),
                        TextInput::make('price')
                            ->label('Monto de la cobertura')
                            ->prefix('$')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->live(onBlur: true),
                    ])
                    ->grid(3)
                    ->columnSpanFull(),
            ]);
    }

    private static function benefitsStep(): Step
    {
        return Step::make('Beneficios')
            ->label('Beneficios y costos límite')
            ->description('Qué cubre el plan y hasta cuánto')
            ->icon(Heroicon::QueueList)
            ->afterValidation(function (Get $get, Set $set): void {
                self::syncMatrices($get, $set);
            })
            ->schema([
                // Paquete de beneficios: van como un todo, sin límite por cobertura.
                CheckboxList::make('package_benefit_ids')
                    ->label('Beneficios incluidos en el paquete')
                    ->helperText('El paquete se cobra como un todo: los beneficios no llevan costo límite por cobertura.')
                    ->options(fn (): array => Benefit::query()->orderBy('description')->pluck('description', 'id')->all())
                    ->searchable()
                    ->bulkToggleable()
                    ->columns(2)
                    ->gridDirection(GridDirection::Row)
                    ->required()
                    ->visible(fn (Get $get): bool => ! self::usesCoverages($get))
                    ->columnSpanFull(),

                Repeater::make('plan_benefits')
                    ->label('Beneficios del plan')
                    ->addActionLabel('Agregar beneficio')
                    ->defaultItems(1)
                    ->minItems(1)
                    ->collapsible()
                    ->visible(fn (Get $get): bool => self::usesCoverages($get))
                    ->itemLabel(fn (array $state): string => (string) (
                        Benefit::query()->find($state['benefit_id'] ?? null)?->description ?? 'Nuevo beneficio'
                    ))
                    ->schema([
                        Select::make('benefit_id')
                            ->label('Beneficio')
                            ->options(fn (): array => Benefit::query()->orderBy('description')->pluck('description', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->distinct()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                            ->createOptionForm([
                                TextInput::make('description')
                                    ->label('Nombre del beneficio')
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->createOptionUsing(fn (array $data): int => (int) Benefit::query()->create([
                                'description' => $data['description'],
                                'status' => 'ACTIVO',
                                'created_by' => Auth::user()?->name ?? 'sistema',
                            ])->id)
                            ->columnSpanFull(),

                        Repeater::make('limits')
                            ->label('Costo límite por cobertura')
                            ->helperText('Deje la casilla vacía si el beneficio no tiene límite en esa cobertura.')
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->default(fn (Get $get): array => PlanStructureMatrix::syncCells(
                                PlanStructureMatrix::columns((array) ($get('../../plan_coverages') ?? [])),
                                [],
                                'limit',
                            ))
                            ->schema([
                                Hidden::make('coverage_key'),
                                Hidden::make('coverage_price'),
                                TextInput::make('limit')
                                    ->label(fn (Get $get): string => PlanStructureMatrix::columnLabel(
                                        (float) ($get('coverage_price') ?? 0),
                                    ))
                                    ->prefix('$')
                                    ->numeric()
                                    ->minValue(0)
                                    ->placeholder('Sin límite')
                                    ->nullable(),
                            ])
                            ->grid(4)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function ageRangesStep(): Step
    {
        return Step::make('Tarifas')
            ->label('Rangos de edad y tarifas')
            ->description('Cuánto cuesta según la edad')
            ->icon(Heroicon::CurrencyDollar)
            ->schema([
                Repeater::make('plan_age_ranges')
                    ->label('Rangos de edad')
                    ->helperText('Los rangos son propios de este plan. Cada uno lleva una tarifa por cobertura.')
                    ->addActionLabel('Agregar rango de edad')
                    ->defaultItems(1)
                    ->minItems(1)
                    ->collapsible()
                    ->visible(fn (Get $get): bool => self::usesCoverages($get))
                    ->itemLabel(fn (array $state): string => filled($state['range'] ?? null)
                        ? $state['range'].' años'
                        : 'Nuevo rango de edad')
                    ->schema([
                        ...self::ageRangeFields(),

                        Repeater::make('rates')
                            ->label('Tarifa por cobertura')
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->default(fn (Get $get): array => PlanStructureMatrix::syncCells(
                                PlanStructureMatrix::columns((array) ($get('../../plan_coverages') ?? [])),
                                [],
                                'rate',
                            ))
                            ->schema([
                                Hidden::make('coverage_key'),
                                Hidden::make('coverage_price'),
                                TextInput::make('rate')
                                    ->label(fn (Get $get): string => PlanStructureMatrix::columnLabel(
                                        (float) ($get('coverage_price') ?? 0),
                                    ))
                                    ->prefix('$')
                                    ->numeric()
                                    ->minValue(0)
                                    ->required(),
                            ])
                            ->grid(4)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Repeater::make('package_age_ranges')
                    ->label('Rangos de edad y tarifa')
                    ->helperText('El paquete se cobra con una sola tarifa por rango de edad.')
                    ->addActionLabel('Agregar rango de edad')
                    ->defaultItems(1)
                    ->minItems(1)
                    ->collapsible()
                    ->visible(fn (Get $get): bool => ! self::usesCoverages($get))
                    ->itemLabel(fn (array $state): string => filled($state['range'] ?? null)
                        ? $state['range'].' años'
                        : 'Nuevo rango de edad')
                    ->schema([
                        ...self::ageRangeFields(),

                        TextInput::make('rate')
                            ->label('Tarifa anual')
                            ->prefix('$')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Campos comunes de un rango de edad. La etiqueta se arma sola desde las
     * edades para que no se desincronice de los límites reales, que son los que
     * usa el cálculo de tarifas.
     *
     * @return list<\Filament\Forms\Components\Field>
     */
    private static function ageRangeFields(): array
    {
        return [
            TextInput::make('age_init')
                ->label('Edad inicial')
                ->numeric()
                ->minValue(0)
                ->maxValue(120)
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Get $get, Set $set): mixed => $set(
                    'range',
                    self::ageRangeLabel($get('age_init'), $get('age_end')),
                )),

            TextInput::make('age_end')
                ->label('Edad final')
                ->numeric()
                ->minValue(0)
                ->maxValue(120)
                ->required()
                ->gte('age_init')
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Get $get, Set $set): mixed => $set(
                    'range',
                    self::ageRangeLabel($get('age_init'), $get('age_end')),
                )),

            TextInput::make('range')
                ->label('Etiqueta')
                ->helperText('Se arma sola con las edades.')
                ->required()
                ->readOnly()
                ->dehydrated(),

            Hidden::make('id'),
        ];
    }

    private static function ageRangeLabel(mixed $init, mixed $end): string
    {
        if (blank($init) || blank($end)) {
            return '';
        }

        return ((int) $init).' a '.((int) $end);
    }

    private static function usesCoverages(Get $get): bool
    {
        $mode = PlanPricingMode::fromStored($get('pricing_mode') ?? $get('../../pricing_mode'));

        return ($mode ?? PlanPricingMode::Coberturas)->usesCoverages();
    }

    /**
     * Reescribe las celdas de las dos matrices contra las coberturas vigentes.
     * Conserva lo ya cargado y no depende del orden en que el analista recorra
     * los pasos.
     */
    private static function syncMatrices(Get $get, Set $set): void
    {
        $columns = PlanStructureMatrix::columns((array) ($get('plan_coverages') ?? []));

        $set('plan_benefits', PlanStructureMatrix::syncRows(
            $columns,
            $get('plan_benefits'),
            'limits',
            'limit',
        ));

        $set('plan_age_ranges', PlanStructureMatrix::syncRows(
            $columns,
            $get('plan_age_ranges'),
            'rates',
            'rate',
        ));
    }
}
