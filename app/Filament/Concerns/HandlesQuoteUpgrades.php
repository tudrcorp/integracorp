<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Filament\Business\Resources\PlanGenerators\Pages\Concerns\InteractsWithUpgradeRanges;
use App\Filament\Shared\Quotes\QuoteUpgradesSection;
use App\Models\CorporateQuote;
use App\Models\IndividualQuote;
use App\Models\Plan;
use App\Models\UpgradeCoverage;
use App\Support\PlanGenerators\PlanGeneratorUpgrades;
use App\Support\Quotes\IndividualQuoteUpgrades;
use App\Support\Upgrades\UpgradeCardCatalog;
use App\Support\Upgrades\UpgradeNetworkAudience;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Upgrades en una página de crear cotización: tarjetas, validación al guardar y escritura junto
 * con la cotización. La sección del formulario es {@see QuoteUpgradesSection}.
 */
trait HandlesQuoteUpgrades
{
    use InteractsWithUpgradeRanges;

    /**
     * Upgrades validados antes de crear, que se guardan junto con la cotización.
     *
     * @var array{state: array<int|string, mixed>, rate_rows: list<array{age_range_label: string, population: int}>}|null
     */
    private ?array $pendingUpgrades = null;

    /**
     * Si la página es de la red comercial: solo ofrece y acepta los upgrades visibles para quien cotiza.
     */
    protected function quotesForNetwork(): bool
    {
        return false;
    }

    /**
     * @return list<array{age_range_label: string, population: int}>
     */
    protected function upgradeRateRows(): array
    {
        return QuoteUpgradesSection::rateRows(fn (string $field): mixed => $this->data[$field] ?? null, $this->quotesForNetwork());
    }

    /**
     * Al crear no hay upgrades guardados: todos salen del catálogo vigente.
     *
     * @return list<array<string, mixed>>
     */
    protected function upgradeStoredLines(): array
    {
        return [];
    }

    /**
     * Tarjeta de upgrade: si ya está en la cotización la quita; si no, la agrega con su primera
     * cobertura. Solo acepta upgrades ofrecidos a quien cotiza y respeta el tope por cotización.
     */
    public function toggleQuoteUpgrade(int $upgradeId): void
    {
        $card = QuoteUpgradesSection::cards($this->quotesForNetwork())[$upgradeId] ?? null;
        $items = array_filter((array) ($this->data['upgrades'] ?? []), 'is_array');

        foreach ($items as $key => $item) {
            $itemUpgrade = is_numeric($item['upgrade_id'] ?? null) ? (int) $item['upgrade_id'] : UpgradeCardCatalog::upgradeIdForCoverage($item['upgrade_coverage_id'] ?? null);

            if ($itemUpgrade === $upgradeId) {
                unset($items[$key]);
                $this->data['upgrades'] = $items;

                return;
            }
        }

        if ($card === null) {
            Notification::make()->warning()->title('Ese upgrade ya no está disponible')->body('Actualice la página: el catálogo cambió.')->send();

            return;
        }

        if (count($items) >= PlanGeneratorUpgrades::MAX_LINES) {
            Notification::make()->warning()->title('Máximo de upgrades')->body('Una cotización admite hasta '.PlanGeneratorUpgrades::MAX_LINES.' upgrades.')->send();

            return;
        }

        $items[(string) Str::uuid()] = [
            'line_key' => (string) Str::uuid(),
            'upgrade_id' => $upgradeId,
            'upgrade_coverage_id' => $card['default_coverage_id'],
            'excluded_ranges' => [],
            'manual_ranges' => [],
        ];

        $this->data['upgrades'] = $items;
    }

    /**
     * Saca los upgrades de los datos de la cotización y los valida en el servidor. Si algo no
     * cuadra, avisa y detiene el guardado.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function captureQuoteUpgrades(array $data): array
    {
        $upgradeState = array_values(array_filter((array) ($data['upgrades'] ?? []), 'is_array'));
        unset($data['upgrades']);

        $this->rejectPlansOutOfReach($data);

        if ($upgradeState === [] || QuoteUpgradesSection::isTaylorMade($data['type'] ?? null)) {
            return $this->rememberOtherPlanDetails($data);
        }

        $errors = [];

        if ($this->quotesForNetwork()) {
            foreach (UpgradeNetworkAudience::rejectedNames(Auth::user(), $upgradeState) as $name) {
                $errors[] = '«'.$name.'» ya no está disponible para usted: quítelo de la cotización.';
            }
        }

        $rateRows = QuoteUpgradesSection::rateRows(fn (string $field): mixed => $data[$field] ?? null, $this->quotesForNetwork());
        $errors = [
            ...$errors,
            ...$this->upgradesAlreadyQuotedAsPlan($upgradeState, $data),
            ...IndividualQuoteUpgrades::quote($upgradeState, $rateRows)['errors'],
        ];

        if ($errors !== []) {
            Notification::make()
                ->title('No se guardó la cotización')
                ->body('Revise los upgrades: '.implode(' ', $errors))
                ->danger()
                ->persistent()
                ->send();

            $this->halt();
        }

        $this->pendingUpgrades = ['state' => $upgradeState, 'rate_rows' => $rateRows];

        return $this->rememberOtherPlanDetails($data);
    }

    /**
     * Planes de la cotización, de un solo plan o de varios.
     *
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    private function quotedPlanIds(array $data): array
    {
        $plan = $data['plan'] ?? null;

        if (is_numeric($plan)) {
            return [(int) $plan];
        }

        $rows = (array) ($data[$this->quotesForNetwork() ? 'details_quote' : 'details_quote_multiple'] ?? []);

        return array_values(array_unique(array_map('intval', array_filter(array_column(array_filter($rows, 'is_array'), 'plan_id'), 'is_numeric'))));
    }

    /**
     * El plan nacido de un upgrade hereda su visibilidad: también se revisa al guardar.
     *
     * @param  array<string, mixed>  $data
     */
    private function rejectPlansOutOfReach(array $data): void
    {
        if (! $this->quotesForNetwork()) {
            return;
        }

        $rejected = UpgradeNetworkAudience::rejectedPlanNames(Auth::user(), $this->quotedPlanIds($data));

        if ($rejected !== []) {
            Notification::make()
                ->title('No se guardó la cotización')
                ->body('Estos planes ya no están disponibles para usted: '.implode(', ', $rejected).'.')
                ->danger()
                ->persistent()
                ->send();

            $this->halt();
        }
    }

    /**
     * Un upgrade no se suma sobre su propio plan: se cobraría dos veces lo mismo.
     *
     * @param  list<array<string, mixed>>  $upgradeState
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function upgradesAlreadyQuotedAsPlan(array $upgradeState, array $data): array
    {
        $coverageIds = array_filter(array_column($upgradeState, 'upgrade_coverage_id'), 'is_numeric');

        if ($coverageIds === []) {
            return [];
        }

        return Plan::query()
            ->whereIn('id', $this->quotedPlanIds($data) ?: [0])
            ->whereIn('source_upgrade_id', UpgradeCoverage::query()->whereIn('id', $coverageIds)->select('upgrade_id'))
            ->pluck('description')
            ->map(static fn (mixed $name): string => '«'.$name.'» ya es el plan cotizado: quítelo de los upgrades.')
            ->all();
    }

    /**
     * Deja en sesión las filas de un plan sin repetidor fijo, como hacen las páginas con los demás.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function rememberOtherPlanDetails(array $data): array
    {
        $rows = (array) ($data[QuoteUpgradesSection::OTHER_PLAN_REPEATER] ?? []);
        unset($data[QuoteUpgradesSection::OTHER_PLAN_REPEATER]);

        if ($this->quotesForNetwork() && QuoteUpgradesSection::isOtherPlan($data['plan'] ?? null)) {
            $planId = (int) $data['plan'];

            session()->put('details_quote', collect($rows)
                ->filter(fn (mixed $row): bool => is_array($row) && filled($row['age_range_id'] ?? null))
                ->map(fn (array $row): array => [...$row, 'plan_id' => $planId])
                ->values()
                ->all());
        }

        return $data;
    }

    /**
     * Los upgrades se guardan antes de las líneas y el PDF, y fuera de su try: si fallan, la
     * excepción deshace toda la cotización (transacción del panel) en lugar de dejarla sin los
     * upgrades que se eligieron.
     */
    protected function persistQuoteUpgrades(IndividualQuote|CorporateQuote $quote): void
    {
        if ($this->pendingUpgrades !== null) {
            IndividualQuoteUpgrades::persist($quote, $this->pendingUpgrades['state'], $this->pendingUpgrades['rate_rows']);
        }
    }
}
