<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\PlanGenerators\Pages\Concerns;

use App\Models\PlanGenerator;
use App\Support\PlanGenerators\PlanGeneratorGroupTotalCalculator;
use App\Support\PlanGenerators\PlanGeneratorMatrixState;
use App\Support\PlanGenerators\PlanGeneratorQuotationState;
use App\Support\PlanGenerators\PlanGeneratorUpgrades;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Puente entre el editor de matrices y el guardado de una página del recurso.
 *
 * Las acciones del editor (agregar/quitar beneficios, rangos y columnas) viven
 * en `InteractsWithPlanGeneratorMatrixRows`, porque también las necesita la
 * tabla para derivar una cotización en una modal. Acá queda solo lo que es
 * propio de un formulario de página: normalizar el estado y prepararlo para
 * `PlanGeneratorPersistence`.
 */
trait InteractsWithPlanGeneratorMatrix
{
    use InteractsWithPlanGeneratorMatrixRows;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeMatrixFormState(array $data): array
    {
        $columns = (array) ($data['columns'] ?? []);

        foreach ($columns as $index => $column) {
            if (! is_array($column)) {
                continue;
            }

            if (! filled($column['column_key'] ?? null)) {
                $columns[$index]['column_key'] = (string) Str::uuid();
            }
        }

        $data['columns'] = PlanGeneratorMatrixState::normalizeColumns($columns);
        $data['rows'] = PlanGeneratorMatrixState::ensureRowsHaveCells(
            (array) ($data['rows'] ?? []),
            $columns,
        );
        $data['rate_rows'] = PlanGeneratorMatrixState::ensureRateRowsHaveCells(
            (array) ($data['rate_rows'] ?? []),
            $columns,
        );
        $data['group_total_hidden_rows'] = PlanGeneratorGroupTotalCalculator::normalizeHiddenRows(
            $data['group_total_hidden_rows'] ?? [],
        );

        $pageCount = filled($data['quotation_page_count'] ?? null)
            ? (int) $data['quotation_page_count']
            : 0;

        $planPageNumber = filled($data['plan_page_number'] ?? null)
            ? (int) $data['plan_page_number']
            : null;

        $data['quotation_pages'] = PlanGeneratorQuotationState::syncImagePagesForQuotation(
            (array) ($data['quotation_pages'] ?? []),
            $pageCount,
            $planPageNumber,
        );

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function matrixFormStateForPersistence(): array
    {
        $state = $this->form->getState();

        $state['columns'] = (array) ($this->data['columns'] ?? $state['columns'] ?? []);
        $state['rows'] = (array) ($this->data['rows'] ?? $state['rows'] ?? []);
        $state['rate_rows'] = (array) ($this->data['rate_rows'] ?? $state['rate_rows'] ?? []);
        $state['quotation_pages'] = (array) ($this->data['quotation_pages'] ?? $state['quotation_pages'] ?? []);
        $state['upgrades'] = (array) ($this->data['upgrades'] ?? $state['upgrades'] ?? []);

        return $this->normalizeMatrixFormState($state);
    }

    /**
     * Incluye o saca un rango de edad de la población de un upgrade. Solo acepta
     * rutas de una fila del repetidor `upgrades` del formulario de la página.
     */
    public function togglePlanGeneratorUpgradeRange(string $itemStatePath, string $label): void
    {
        $item = $this->planGeneratorUpgradeItem($itemStatePath);
        $key = PlanGeneratorUpgrades::rangeKey($label);

        if ($item === null || $key === '') {
            return;
        }

        $excluded = PlanGeneratorUpgrades::normalizeExcluded($item['excluded_ranges'] ?? []);
        $excluded = in_array($key, $excluded, true)
            ? array_values(array_diff($excluded, [$key]))
            : [...$excluded, $key];

        data_set($this, $itemStatePath.'.excluded_ranges', $excluded);
    }

    /**
     * Incluye a mano un rango que el upgrade cubre solo en parte: el analista
     * declara cuántas personas del rango tienen las edades cubiertas y por qué.
     * El precio es el del upgrade (fijo). Al aprobar, el upgrade solo llega a
     * quien tenga de verdad esa edad. Quién y cuándo los fija el servidor.
     */
    public function includeUpgradeRangeManuallyAction(): Action
    {
        return Action::make('includeUpgradeRangeManually')
            ->label('Incluir a mano')
            ->icon(Heroicon::HandRaised)
            ->color('warning')
            ->modalIcon(Heroicon::HandRaised)
            ->modalWidth(Width::Large)
            ->modalHeading(fn (array $arguments): string => 'Incluir parte de «'.($arguments['label'] ?? 'rango').'»')
            ->modalDescription(function (array $arguments): string {
                $context = $this->planGeneratorUpgradeRangeContext($arguments);

                if ($context === null) {
                    return 'El upgrade cambió mientras abría esta ventana. Ciérrela y vuelva a intentarlo.';
                }

                return '«'.$context['upgrade'].'» cubre '.$context['covers'].'. En el rango «'.$context['label'].'» solo pueden recibirlo las personas de '
                    .$context['covered'].', a '.PlanGeneratorUpgrades::money($context['price']).' por persona (precio del upgrade, no se cambia). '
                    .'Indique cuántas de las '.$context['population'].' personas del rango tienen esa edad y explique por qué. Al aprobar, el sistema solo se lo asigna a quien tenga la edad.';
            })
            ->modalSubmitActionLabel('Incluir personas')
            // «Cambiar» abre la misma ventana con lo ya declarado.
            ->fillForm(function (array $arguments): array {
                $line = $this->planGeneratorUpgradeLine((string) ($arguments['path'] ?? ''));
                $current = $line !== null ? PlanGeneratorUpgrades::manualEntryFor($line, (string) ($arguments['label'] ?? '')) : null;

                return [
                    'persons' => $current['persons'] ?? null,
                    'note' => $current['note'] ?? '',
                ];
            })
            ->schema(function (array $arguments): array {
                $context = $this->planGeneratorUpgradeRangeContext($arguments);
                $population = max(1, (int) ($context['population'] ?? 1));
                $price = (float) ($context['price'] ?? 0);

                return [
                    TextInput::make('persons')
                        ->label('¿Cuántas personas tienen '.($context['covered'] ?? 'la edad del upgrade').'?')
                        ->helperText('Entre 1 y '.$population.': las personas cotizadas en el rango «'.($context['label'] ?? '').'».')
                        ->integer()
                        ->minValue(1)
                        ->maxValue($population)
                        ->required()
                        ->live(onBlur: true)
                        ->suffix('personas')
                        ->validationMessages([
                            'required' => 'Indique cuántas personas tienen la edad del upgrade.',
                            'min' => 'Debe ser al menos 1 persona.',
                            'max' => 'No puede ser más que las '.$population.' personas cotizadas en el rango.',
                            'integer' => 'Escriba un número entero de personas.',
                        ]),
                    Placeholder::make('subtotal')
                        ->label('Subtotal anual del upgrade en este rango')
                        ->content(function (Get $get) use ($price): string {
                            $persons = (int) ($get('persons') ?? 0);

                            return $persons < 1 || $price <= 0
                                ? 'Indique la cantidad de personas.'
                                : $persons.' × '.PlanGeneratorUpgrades::money($price).' = '.PlanGeneratorUpgrades::money($persons * $price).' al año';
                        }),
                    Textarea::make('note')
                        ->label('¿Por qué se incluye?')
                        ->placeholder('Ej.: según el padrón del cliente, 10 de las 13 personas de este rango tienen entre 18 y 30 años.')
                        ->helperText('Mínimo '.PlanGeneratorUpgrades::MANUAL_NOTE_MIN_LENGTH.' caracteres. Queda en la cotización y en la auditoría.')
                        ->rows(3)
                        ->required()
                        ->minLength(PlanGeneratorUpgrades::MANUAL_NOTE_MIN_LENGTH)
                        ->maxLength(PlanGeneratorUpgrades::MANUAL_NOTE_MAX_LENGTH)
                        ->validationMessages([
                            'required' => 'Explique por qué se incluye este rango.',
                            'min' => 'La nota necesita al menos '.PlanGeneratorUpgrades::MANUAL_NOTE_MIN_LENGTH.' caracteres: explique el motivo.',
                        ]),
                ];
            })
            ->action(function (array $data, array $arguments): void {
                $path = (string) ($arguments['path'] ?? '');
                $context = $this->planGeneratorUpgradeRangeContext($arguments);
                $item = $this->planGeneratorUpgradeItem($path);
                $persons = (int) ($data['persons'] ?? 0);

                if ($context === null || $item === null) {
                    Notification::make()->warning()->title('No se pudo incluir el rango')->body('El upgrade o el rango cambiaron mientras escribía. Vuelva a intentarlo.')->send();

                    return;
                }

                if ($persons < 1 || $persons > $context['population']) {
                    Notification::make()->danger()->title('Cantidad no válida')->body('Indique entre 1 y '.$context['population'].' personas.')->send();

                    return;
                }

                $manual = array_values(array_filter(
                    PlanGeneratorUpgrades::normalizeManual($item['manual_ranges'] ?? []),
                    static fn (array $entry): bool => $entry['key'] !== $context['key'],
                ));
                $manual[] = [
                    'key' => $context['key'],
                    'label' => $context['label'],
                    'persons' => $persons,
                    'note' => trim((string) $data['note']),
                    'by' => Auth::user()?->name,
                    'at' => now()->toIso8601String(),
                ];

                data_set($this, $path.'.manual_ranges', $manual);

                Notification::make()
                    ->success()
                    ->title('Personas incluidas a mano')
                    ->body($persons.' de '.$context['population'].' personas de «'.$context['label'].'» ('.$context['covered'].') a '.PlanGeneratorUpgrades::money($context['price']).'. Se registra al guardar la cotización.')
                    ->send();
            });
    }

    /**
     * Datos del rango que se va a incluir a mano, leídos del estado actual en el
     * servidor. Null si el rango no está cubierto en parte por el upgrade.
     *
     * @param  array<string, mixed>  $arguments
     * @return array{key: string, label: string, upgrade: string, covers: string, covered: string, price: float, population: int}|null
     */
    private function planGeneratorUpgradeRangeContext(array $arguments): ?array
    {
        $line = $this->planGeneratorUpgradeLine((string) ($arguments['path'] ?? ''));
        $label = trim((string) ($arguments['label'] ?? ''));

        if ($line === null || $label === '') {
            return null;
        }

        $partial = PlanGeneratorUpgrades::partialCoverage((array) $line['age_prices'], $label);

        if ($partial === null) {
            return null;
        }

        $population = 0;

        foreach ((array) ($this->data['rate_rows'] ?? []) as $rateRow) {
            if (is_array($rateRow) && PlanGeneratorUpgrades::rangeKey((string) ($rateRow['age_range_label'] ?? '')) === PlanGeneratorUpgrades::rangeKey($label)) {
                $population = max(0, (int) ($rateRow['population'] ?? 0));

                break;
            }
        }

        if ($population < 1) {
            return null;
        }

        return [
            'key' => PlanGeneratorUpgrades::rangeKey($label),
            'label' => $label,
            'upgrade' => (string) $line['upgrade_name'],
            'covers' => PlanGeneratorUpgrades::coverageSpansLabel((array) $line['age_prices']),
            'covered' => $partial['covered'],
            'price' => (float) $partial['price'],
            'population' => $population,
        ];
    }

    /**
     * Quita la inclusión manual de un rango: vuelve a «No aplica».
     */
    public function removePlanGeneratorUpgradeManualRange(string $itemStatePath, string $label): void
    {
        $item = $this->planGeneratorUpgradeItem($itemStatePath);
        $key = PlanGeneratorUpgrades::rangeKey($label);

        if ($item === null || $key === '') {
            return;
        }

        data_set($this, $itemStatePath.'.manual_ranges', array_values(array_filter(
            PlanGeneratorUpgrades::normalizeManual($item['manual_ranges'] ?? []),
            static fn (array $entry): bool => $entry['key'] !== $key,
        )));
    }

    /**
     * Línea del upgrade (con sus precios) de una fila del repetidor.
     *
     * @return array<string, mixed>|null
     */
    private function planGeneratorUpgradeLine(string $itemStatePath): ?array
    {
        $item = $this->planGeneratorUpgradeItem($itemStatePath);

        if ($item === null) {
            return null;
        }

        $record = property_exists($this, 'record') && $this->record instanceof PlanGenerator ? $this->record : null;

        return PlanGeneratorUpgrades::resolveLines($record, [$item])['lines'][0] ?? null;
    }

    /**
     * Vuelve a aplicar el upgrade a todos los rangos que tienen precio.
     */
    public function includeAllPlanGeneratorUpgradeRanges(string $itemStatePath): void
    {
        if ($this->planGeneratorUpgradeItem($itemStatePath) === null) {
            return;
        }

        data_set($this, $itemStatePath.'.excluded_ranges', []);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function planGeneratorUpgradeItem(string $itemStatePath): ?array
    {
        if (preg_match('/^data\.upgrades\.[A-Za-z0-9_-]+$/', $itemStatePath) !== 1) {
            return null;
        }

        $item = data_get($this, $itemStatePath);

        return is_array($item) ? $item : null;
    }

    /**
     * Bloquea el guardado si algún upgrade no se puede cotizar con los rangos y
     * la población de la cotización. Se valida antes de escribir nada.
     *
     * @param  array<string, mixed>  $data
     */
    protected function assertPlanGeneratorUpgradesArePriceable(array $data, ?PlanGenerator $record = null): void
    {
        $errors = PlanGeneratorUpgrades::validationErrors(
            $record,
            (array) ($this->data['upgrades'] ?? $data['upgrades'] ?? []),
            (array) ($data['rate_rows'] ?? []),
        );

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'data.upgrades' => $errors,
            ]);
        }
    }
}
