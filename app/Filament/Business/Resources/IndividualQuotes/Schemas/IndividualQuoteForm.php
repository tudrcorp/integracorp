<?php

namespace App\Filament\Business\Resources\IndividualQuotes\Schemas;

use App\Http\Controllers\UtilsController;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\IndividualQuote;
use App\Models\UpgradeCoverage;
use App\Support\PlanGenerators\PlanGeneratorUpgrades;
use App\Support\Plans\PlanQuotability;
use App\Support\Quotes\IndividualQuoteUpgrades;
use App\Support\Quotes\QuoteAgeRangeSelection;
use App\Support\Upgrades\UpgradeCardCatalog;
use App\Support\Upgrades\UpgradePricing;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class IndividualQuoteForm
{
    /** Tope de personas por rango de edad en una cotización individual (el mismo de antes). */
    private const MAX_PERSONS_PER_RANGE = 10;

    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

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

    private static function syncQuoteDetailsForPlan(Set $set, mixed $plan): void
    {
        $planId = QuoteAgeRangeSelection::selectedPlanId($plan);

        if ($planId === null) {
            $set('details_quote', []);

            return;
        }

        $set('details_quote', QuoteAgeRangeSelection::presetRowsForPlan($planId));
    }

    /**
     * Cotizador de un plan: las filas ya traen su rango; basta con que haya personas en uno.
     *
     * @return array<int, \Closure(): \Closure(string, mixed, \Closure): void>
     */
    private static function requirePersonsByRangeRule(): array
    {
        return [
            function (): \Closure {
                return function (string $attribute, mixed $value, \Closure $fail): void {
                    $rows = collect(is_array($value) ? $value : [])->filter(fn (mixed $row): bool => is_array($row));

                    if ($rows->contains(fn (array $row): bool => filled($row['total_persons'] ?? null) && (! is_numeric($row['total_persons']) || (int) $row['total_persons'] < 0 || (int) $row['total_persons'] > self::MAX_PERSONS_PER_RANGE))) {
                        $fail('La cantidad de personas de cada rango debe estar entre 0 y '.self::MAX_PERSONS_PER_RANGE.'.');

                        return;
                    }

                    if (! $rows->contains(fn (array $row): bool => is_numeric($row['total_persons'] ?? null) && (int) $row['total_persons'] >= 1)) {
                        $fail('Indique cuántas personas hay en al menos un rango de edad.');
                    }
                };
            },
        ];
    }

    /**
     * «3 personas en 2 rangos de edad»: resumen en vivo debajo de los rangos.
     */
    private static function personsSummary(mixed $rows): string
    {
        $counted = collect(is_array($rows) ? $rows : [])
            ->filter(fn (mixed $row): bool => is_array($row) && is_numeric($row['total_persons'] ?? null) && (int) $row['total_persons'] > 0);
        $persons = (int) $counted->sum(fn (array $row): int => (int) $row['total_persons']);
        $ranges = $counted->count();

        return $persons === 0
            ? 'Todavía no hay personas. Marque cuántas hay en cada rango de edad.'
            : $persons.' '.($persons === 1 ? 'persona' : 'personas').' en '.$ranges.' '.($ranges === 1 ? 'rango de edad' : 'rangos de edad').'.';
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('individualQuoteFormTabs')
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
                                                TextInput::make('code')
                                                    ->label('Nro. de cotización')
                                                    ->prefixIcon('heroicon-m-clipboard-document-check')
                                                    ->default(function () {
                                                        if (IndividualQuote::max('id') == null) {
                                                            $parte_entera = 0;
                                                        } else {
                                                            $parte_entera = IndividualQuote::max('id');
                                                        }

                                                        return 'COT-IND-000'.$parte_entera + 1;
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
                                                    ->options(fn () => UtilsController::getCountries())
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
                            ->schema([
                                Section::make('plans')
                                    ->heading('¿Qué plan vas a cotizar?')
                                    ->description('Elija un plan, o «Cotización múltiple» para combinar varios en la misma propuesta. Debajo de cada plan se ven sus rangos de edad.')
                                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                                    ->schema([
                                        Radio::make('plan')
                                            ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                                            ->label('Plan')
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(function (Set $set, mixed $state): void {
                                                self::syncQuoteDetailsForPlan($set, $state);
                                            })
                                            ->options(fn () => PlanQuotability::optionsForIndividual(includeMultiple: true)->all())
                                            ->descriptions(fn (): array => PlanQuotability::descriptionsForIndividual())
                                            ->validationMessages(['required' => 'Elija el plan que va a cotizar.']),
                                        Placeholder::make('plan_next_step')
                                            ->hiddenLabel()
                                            ->visible(fn (Get $get): bool => filled($get('plan')))
                                            ->content(fn (Get $get): string => $get('plan') === 'CM'
                                                ? 'Siguiente: en «Rango de edad» agregue cada plan con su rango y cantidad de personas.'
                                                : 'Siguiente: en «Rango de edad» marque cuántas personas hay en cada rango.'),
                                    ]),
                            ]),
                        Tab::make('RANGO DE EDAD')
                            ->icon(Heroicon::OutlinedUsers)
                            ->schema([
                                Section::make('age_range')
                                    ->heading('Personas por rango de edad')
                                    ->description(fn (Get $get): string => $get('plan') === 'CM'
                                        ? 'Agregue cada plan con su rango de edad y la cantidad de personas.'
                                        : 'Marque cuántas personas hay en cada rango. Deje en 0 los rangos que no se cotizan.')
                                    ->icon(Heroicon::OutlinedUsers)
                                    ->schema([
                                        Placeholder::make('age_range_pick_plan')
                                            ->hiddenLabel()
                                            ->visible(fn (Get $get): bool => blank($get('plan')))
                                            ->content('Primero elija el plan en la pestaña «Planes a cotizar».'),

                                        Repeater::make('details_quote')
                                            ->hiddenLabel()
                                            ->defaultItems(0)
                                            ->addable(false)
                                            ->deletable(false)
                                            ->reorderable(false)
                                            ->live()
                                            ->rules(self::requirePersonsByRangeRule())
                                            ->visible(fn (Get $get): bool => QuoteAgeRangeSelection::selectedPlanId($get('plan')) !== null)
                                            ->afterStateHydrated(function (Get $get, Set $set): void {
                                                $rows = QuoteAgeRangeSelection::presetRowsIfMissing($get('plan'), $get('details_quote'));

                                                if ($rows === null) {
                                                    return;
                                                }

                                                $set('details_quote', $rows);
                                            })
                                            ->table([
                                                TableColumn::make('Rango de edad')->width('220px'),
                                                TableColumn::make('Personas'),
                                            ])
                                            ->schema([
                                                TextInput::make('range_label')
                                                    ->hiddenLabel()
                                                    ->disabled()
                                                    ->dehydrated()
                                                    ->suffix('años'),
                                                ToggleButtons::make('total_persons')
                                                    ->hiddenLabel()
                                                    ->options(array_combine(range(0, self::MAX_PERSONS_PER_RANGE), array_map('strval', range(0, self::MAX_PERSONS_PER_RANGE))))
                                                    ->inline()
                                                    ->grouped()
                                                    ->live(),
                                                Hidden::make('age_range_id'),
                                                Hidden::make('plan_id'),
                                            ]),

                                        Placeholder::make('persons_summary')
                                            ->hiddenLabel()
                                            ->visible(fn (Get $get): bool => QuoteAgeRangeSelection::selectedPlanId($get('plan')) !== null)
                                            ->content(fn (Get $get): string => self::personsSummary($get('details_quote'))),

                                        Repeater::make('details_quote_multiple')
                                            ->label('Indique los planes, la edad y número de personas:')
                                            ->addActionLabel('Añadir plan')
                                            ->rules(self::requireQuoteDetailsRule())
                                            ->visible(fn (Get $get): bool => $get('plan') === 'CM')
                                            ->schema([
                                                Radio::make('plan_id')
                                                    ->label('Seleccione una opción y añadir plan:')

                                                    ->inLine()
                                                    ->live()
                                                    ->options(fn () => PlanQuotability::optionsForIndividual()->all())->columnSpan(3),
                                                Select::make('age_range_id')
                                                    ->label('Rango de edad')
                                                    ->placeholder('Rango de edad')
                                                    ->options(function (Get $get) {
                                                        $planId = QuoteAgeRangeSelection::selectedPlanId($get('plan_id'));

                                                        return $planId === null
                                                            ? []
                                                            : QuoteAgeRangeSelection::optionsForPlan($planId);
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
                                                    ->live(onBlur: true)
                                                    ->validationMessages([
                                                        'min' => 'La cantidad de personas debe ser como mínimo una (1) persona.',
                                                    ]),
                                            ])->columns(2),

                                        self::upgradesSection(),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    /**
     * Upgrades opcionales: el analista elige el upgrade y su cobertura; el servidor calcula rango
     * por rango con las reglas del generador de planes ({@see IndividualQuoteUpgrades}). Solo al
     * crear: la edición no recalcula las tarifas ni el PDF, así que no se ofrece ahí.
     */
    private static function upgradesSection(): Section
    {
        return Section::make('Upgrades (opcional)')
            ->description('Toque las tarjetas de los upgrades que quiere sumar. En cada uno revise la cobertura y a qué rangos de edad se aplica (puede sacar rangos o incluir a mano, con nota, parte de un rango cubierto solo en algunas edades). El total se suma a la tarifa del plan.')
            ->icon(Heroicon::Sparkles)
            ->collapsible()
            ->columnSpanFull()
            ->visible(fn (Get $get, string $operation): bool => $operation === 'create' && filled($get('plan')))
            ->schema([
                View::make('filament.business.individual-quotes.partials.upgrade-cards')
                    ->viewData(function (Get $get): array {
                        $items = array_values(array_filter((array) ($get('upgrades') ?? []), 'is_array'));

                        return [
                            'cards' => UpgradeCardCatalog::cards(),
                            'selected' => array_values(array_filter(array_map(
                                static fn (array $item): ?int => is_numeric($item['upgrade_id'] ?? null) ? (int) $item['upgrade_id'] : UpgradeCardCatalog::upgradeIdForCoverage($item['upgrade_coverage_id'] ?? null),
                                $items,
                            ))),
                            'limitReached' => count($items) >= PlanGeneratorUpgrades::MAX_LINES,
                        ];
                    })
                    ->columnSpanFull(),
                Repeater::make('upgrades')
                    ->hiddenLabel()
                    ->addable(false)
                    ->defaultItems(0)
                    ->maxItems(PlanGeneratorUpgrades::MAX_LINES)
                    ->collapsible()
                    ->live()
                    ->deleteAction(fn ($action) => $action->tooltip('Quitar este upgrade'))
                    ->itemLabel(fn (array $state): string => UpgradeCardCatalog::cards()[(int) ($state['upgrade_id'] ?? 0)]['name'] ?? self::upgradeCoverageLabel($state['upgrade_coverage_id'] ?? null) ?? 'Upgrade')
                    ->schema([
                        Hidden::make('line_key')
                            ->default(fn (): string => (string) Str::uuid()),
                        Hidden::make('upgrade_id'),
                        Hidden::make('excluded_ranges')
                            ->default([])
                            ->dehydrated(),
                        Hidden::make('manual_ranges')
                            ->default([])
                            ->dehydrated(),
                        ToggleButtons::make('upgrade_coverage_id')
                            ->label('Cobertura')
                            ->options(fn (Get $get): array => collect(UpgradeCardCatalog::cards()[(int) ($get('upgrade_id') ?? 0)]['coverages'] ?? [])
                                ->mapWithKeys(fn (array $coverage): array => [$coverage['id'] => $coverage['label'].' · '.$coverage['ages']])
                                ->all())
                            ->inline()
                            ->required()
                            ->live()
                            // Otra cobertura tiene otros precios: la población elegida se rehace.
                            ->afterStateUpdated(function (Set $set): void {
                                $set('excluded_ranges', []);
                                $set('manual_ranges', []);
                            })
                            ->validationMessages(['required' => 'Elija la cobertura del upgrade.']),
                        View::make('filament.business.plan-generators.partials.upgrade-population')
                            ->visible(fn (Get $get): bool => filled($get('upgrade_coverage_id')))
                            ->viewData(fn (Get $get): array => [
                                'upgradeQuote' => IndividualQuoteUpgrades::quote([[
                                    'line_key' => $get('line_key'),
                                    'upgrade_coverage_id' => $get('upgrade_coverage_id'),
                                    'excluded_ranges' => $get('excluded_ranges') ?? [],
                                    'manual_ranges' => $get('manual_ranges') ?? [],
                                ]], self::rateRowsFrom($get, '../../')),
                                'populationUnitLabel' => 'Personas',
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                View::make('filament.business.individual-quotes.partials.upgrades-total')
                    ->visible(fn (Get $get): bool => filled($get('upgrades')))
                    ->viewData(fn (Get $get): array => [
                        'upgradeQuote' => IndividualQuoteUpgrades::quote((array) ($get('upgrades') ?? []), self::rateRowsFrom($get)),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Rangos de la cotización con su población, leídos del formulario.
     *
     * @return list<array{age_range_label: string, population: int}>
     */
    private static function rateRowsFrom(Get $get, string $prefix = ''): array
    {
        return IndividualQuoteUpgrades::rateRows(
            $get($prefix.'plan'),
            (array) ($get($prefix.'details_quote') ?? []),
            (array) ($get($prefix.'details_quote_multiple') ?? []),
        );
    }

    /**
     * Etiqueta de una cobertura ya elegida, aunque ya no esté activa en el catálogo.
     */
    private static function upgradeCoverageLabel(mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        $coverage = UpgradeCoverage::query()->with('upgrade')->find((int) $value);

        return $coverage !== null ? UpgradePricing::optionLabel($coverage) : null;
    }
}
