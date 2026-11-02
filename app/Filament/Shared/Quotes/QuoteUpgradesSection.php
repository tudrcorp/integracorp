<?php

declare(strict_types=1);

namespace App\Filament\Shared\Quotes;

use App\Models\UpgradeCoverage;
use App\Support\PlanGenerators\PlanGeneratorUpgrades;
use App\Support\Quotes\IndividualQuoteUpgrades;
use App\Support\Quotes\QuoteAgeRangeSelection;
use App\Support\Upgrades\UpgradeCardCatalog;
use App\Support\Upgrades\UpgradeNetworkAudience;
use App\Support\Upgrades\UpgradePricing;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Upgrades opcionales del formulario de cotización, individual y corporativa, en Negocios y en la
 * red comercial. El usuario elige upgrade y cobertura; el servidor calcula rango por rango con las
 * reglas del generador de planes ({@see IndividualQuoteUpgrades}). Solo al crear: la edición no
 * recalcula las tarifas ni el PDF.
 *
 * Con `$network` solo se ofrecen los upgrades visibles para quien cotiza
 * ({@see UpgradeNetworkAudience}), y los rangos se leen de los repetidores de esos formularios.
 */
final class QuoteUpgradesSection
{
    /** Repetidor de los formularios de la red para cada plan básico. */
    private const NETWORK_PLAN_REPEATERS = [
        1 => 'details_quote_plan_inicial',
        2 => 'details_quote_plan_ideal',
        3 => 'details_quote_plan_especial',
    ];

    /** Repetidor de los formularios de la red para cualquier otro plan. */
    public const OTHER_PLAN_REPEATER = 'details_quote_plan_otro';

    /**
     * Rangos y personas de un plan que no sea Inicial, Ideal o Especial, en la red comercial. Esos
     * tres tienen su repetidor fijo; sin este, ningún otro plan se podía cotizar.
     */
    public static function otherPlanRanges(): Repeater
    {
        return Repeater::make(self::OTHER_PLAN_REPEATER)
            ->label('Indique edad y número de afiliados al plan:')
            ->addActionLabel('Añadir rango de edad')
            ->defaultItems(1)
            ->visible(fn (Get $get): bool => self::isOtherPlan($get('plan')))
            ->rules([
                fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                    $rows = collect($value ?? [])->filter(fn (mixed $row): bool => is_array($row) && filled($row['age_range_id'] ?? null));

                    if ($rows->isEmpty()) {
                        $fail('Debe seleccionar al menos un (1) rango de edad para crear la cotización.');
                    } elseif ($rows->contains(fn (array $row): bool => ! is_numeric($row['total_persons'] ?? null) || (int) $row['total_persons'] < 1)) {
                        $fail('La cantidad de personas debe ser como mínimo una (1) persona.');
                    }
                },
            ])
            ->schema([
                Select::make('age_range_id')
                    ->label('Rango de edad')
                    ->placeholder('Rango de edad')
                    ->options(fn (Get $get): array => QuoteAgeRangeSelection::optionsForPlan((int) $get('../../plan')))
                    ->disableOptionWhen(fn ($value, $state, Get $get): bool => collect($get('../*.age_range_id'))
                        ->reject(fn ($id) => $id == $state)
                        ->filter()
                        ->contains($value))
                    ->live()
                    ->required(),
                TextInput::make('total_persons')
                    ->label('Cantidad de personas')
                    ->placeholder('Cantidad de personas')
                    ->numeric()
                    ->minValue(1)
                    ->required()
                    ->validationMessages(['min' => 'La cantidad de personas debe ser como mínimo una (1) persona.']),
            ])
            ->columns(2)
            ->columnSpanFull();
    }

    public static function isOtherPlan(mixed $plan): bool
    {
        return is_numeric($plan) && ! isset(self::NETWORK_PLAN_REPEATERS[(int) $plan]);
    }

    public static function make(bool $network = false): Section
    {
        return Section::make('Upgrades (opcional)')
            ->description('Toque las tarjetas de los upgrades que quiere sumar. En cada uno revise la cobertura y a qué rangos de edad se aplica (puede sacar rangos o incluir a mano, con nota, parte de un rango cubierto solo en algunas edades). El total se suma a la tarifa del plan.')
            ->icon(Heroicon::Sparkles)
            ->collapsible()
            ->columnSpanFull()
            ->visible(fn (Get $get, string $operation): bool => $operation === 'create'
                && filled($get('plan'))
                && ! self::isTaylorMade($get('type')))
            ->schema([
                View::make('filament.business.individual-quotes.partials.upgrade-cards')
                    ->viewData(function (Get $get) use ($network): array {
                        $items = array_values(array_filter((array) ($get('upgrades') ?? []), 'is_array'));

                        return [
                            'cards' => self::cards($network),
                            'selected' => array_values(array_filter(array_map(
                                static fn (array $item): ?int => is_numeric($item['upgrade_id'] ?? null) ? (int) $item['upgrade_id'] : UpgradeCardCatalog::upgradeIdForCoverage($item['upgrade_coverage_id'] ?? null),
                                $items,
                            ))),
                            'limitReached' => count($items) >= PlanGeneratorUpgrades::MAX_LINES,
                            'emptyMessage' => $network
                                ? 'No tiene upgrades disponibles para cotizar.'
                                : 'No hay upgrades activos. Se administran en Configuración → Upgrades.',
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
                    ->itemLabel(fn (array $state): string => UpgradeCardCatalog::cards()[(int) ($state['upgrade_id'] ?? 0)]['name'] ?? self::coverageLabel($state['upgrade_coverage_id'] ?? null) ?? 'Upgrade')
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
                            ->options(fn (Get $get): array => collect(self::cards($network)[(int) ($get('upgrade_id') ?? 0)]['coverages'] ?? [])
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
                                ]], self::rateRows(fn (string $field): mixed => $get('../../'.$field), $network)),
                                'populationUnitLabel' => 'Personas',
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                View::make('filament.business.individual-quotes.partials.upgrades-total')
                    ->visible(fn (Get $get): bool => filled($get('upgrades')))
                    ->viewData(fn (Get $get): array => [
                        'upgradeQuote' => IndividualQuoteUpgrades::quote((array) ($get('upgrades') ?? []), self::rateRows(fn (string $field): mixed => $get($field), $network)),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Paso del asistente de la red comercial. No aparece si quien cotiza no tiene ningún upgrade
     * disponible: su formulario queda como siempre.
     */
    public static function networkStep(): Step
    {
        return Step::make('UPGRADES')
            ->description('Upgrades opcionales:')
            ->visible(fn (Get $get, string $operation): bool => $operation === 'create'
                && ! self::isTaylorMade($get('type'))
                && self::cards(network: true) !== [])
            ->schema([self::make(network: true)]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function cards(bool $network): array
    {
        return UpgradeNetworkAudience::cardsFor(Auth::user(), $network);
    }

    /**
     * Rangos de la cotización con su población, leídos del formulario.
     *
     * @param  callable(string): mixed  $read
     * @return list<array{age_range_label: string, population: int}>
     */
    public static function rateRows(callable $read, bool $network): array
    {
        $plan = $read('plan');

        if ($network) {
            $repeater = self::NETWORK_PLAN_REPEATERS[is_numeric($plan) ? (int) $plan : 0] ?? self::OTHER_PLAN_REPEATER;

            return IndividualQuoteUpgrades::rateRows(
                $plan,
                (array) ($read($repeater) ?? []),
                (array) ($read('details_quote') ?? []),
            );
        }

        return IndividualQuoteUpgrades::rateRows(
            $plan,
            (array) ($read('details_quote') ?? []),
            (array) ($read('details_quote_multiple') ?? []),
        );
    }

    /**
     * Taylor Made no lleva upgrades: se cotiza a la medida.
     */
    public static function isTaylorMade(mixed $type): bool
    {
        return is_string($type) && $type !== '' && QuoteAgeRangeSelection::normalizeType($type) === QuoteAgeRangeSelection::TYPE_DRESS_TAILOR;
    }

    /**
     * Etiqueta de una cobertura ya elegida, aunque ya no esté activa en el catálogo.
     */
    private static function coverageLabel(mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        $coverage = UpgradeCoverage::query()->with('upgrade')->find((int) $value);

        return $coverage !== null ? UpgradePricing::optionLabel($coverage) : null;
    }
}
