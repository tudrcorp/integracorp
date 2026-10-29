<?php

namespace App\Filament\Business\Resources\IndividualQuotes\Pages;

use App\Enums\QuoteWhatsAppNotification;
use App\Filament\Business\Resources\IndividualQuotes\IndividualQuoteResource;
use App\Filament\Business\Resources\PlanGenerators\Pages\Concerns\InteractsWithUpgradeRanges;
use App\Http\Controllers\UtilsController;
use App\Models\Agency;
use App\Support\PlanGenerators\PlanGeneratorUpgrades;
use App\Support\Quotes\IndividualQuoteUpgrades;
use App\Support\Quotes\QuoteWhatsAppDispatcher;
use App\Support\Upgrades\UpgradeCardCatalog;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CreateIndividualQuote extends CreateRecord
{
    use InteractsWithUpgradeRanges;

    /**
     * Upgrades validados antes de crear, que se guardan junto con la cotización.
     *
     * @var array{state: array<int|string, mixed>, rate_rows: list<array{age_range_label: string, population: int}>}|null
     */
    private ?array $pendingUpgrades = null;

    protected static string $resource = IndividualQuoteResource::class;

    protected static ?string $title = 'Formulario de Cotización Individual';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regresar')
                ->label('Regresar')
                ->button()
                ->icon('heroicon-s-arrow-left')
                ->color('warning')
                ->url(IndividualQuoteResource::getUrl('index')),
        ];
    }

    /**
     * @return list<array{age_range_label: string, population: int}>
     */
    protected function upgradeRateRows(): array
    {
        return IndividualQuoteUpgrades::rateRows(
            $this->data['plan'] ?? null,
            (array) ($this->data['details_quote'] ?? []),
            (array) ($this->data['details_quote_multiple'] ?? []),
        );
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
     * cobertura. Solo acepta upgrades activos del catálogo y respeta el tope por cotización.
     */
    public function toggleQuoteUpgrade(int $upgradeId): void
    {
        $card = UpgradeCardCatalog::cards()[$upgradeId] ?? null;

        if ($card === null) {
            Notification::make()->warning()->title('Ese upgrade ya no está disponible')->body('Actualice la página: el catálogo cambió.')->send();

            return;
        }

        $items = array_filter((array) ($this->data['upgrades'] ?? []), 'is_array');

        foreach ($items as $key => $item) {
            $itemUpgrade = is_numeric($item['upgrade_id'] ?? null) ? (int) $item['upgrade_id'] : UpgradeCardCatalog::upgradeIdForCoverage($item['upgrade_coverage_id'] ?? null);

            if ($itemUpgrade === $upgradeId) {
                unset($items[$key]);
                $this->data['upgrades'] = $items;

                return;
            }
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

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $upgradeState = array_values(array_filter((array) ($data['upgrades'] ?? []), 'is_array'));
        unset($data['upgrades']);

        if ($upgradeState !== []) {
            $rateRows = IndividualQuoteUpgrades::rateRows($data['plan'] ?? null, (array) ($data['details_quote'] ?? []), (array) ($data['details_quote_multiple'] ?? []));
            $errors = IndividualQuoteUpgrades::quote($upgradeState, $rateRows)['errors'];

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
        }

        if ($data['plan'] === 'CM') {
            session()->put('details_quote', $data['details_quote_multiple'] ?? []);
        } else {
            $planId = (int) $data['plan'];
            $details = collect($data['details_quote'] ?? [])
                // Solo los rangos con personas: «0» deja el rango fuera de la cotización.
                ->filter(fn (mixed $row): bool => is_array($row) && is_numeric($row['total_persons'] ?? null) && (int) $row['total_persons'] >= 1)
                ->map(fn (array $row): array => array_merge($row, ['plan_id' => $planId, 'total_persons' => (int) $row['total_persons']]))
                ->values()
                ->all();

            session()->put('details_quote', $details);
        }

        $data['code_agency'] = $data['code_agency'] == null ? 'TDG-100' : $data['code_agency'];
        $data['agent_id'] = $data['agent_id'] == null ? null : $data['agent_id'];

        if ($data['code_agency'] != 'TDG-100') {
            $data['owner_code'] = Agency::where('code', $data['code_agency'])->first()->owner_code;
        } else {
            $data['owner_code'] = 'TDG-100';
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        /**
         * Los upgrades se guardan antes de las líneas y el PDF, y fuera del try de abajo: si
         * fallan, la excepción deshace toda la cotización (transacción del panel) en lugar de
         * dejarla sin los upgrades que el analista eligió.
         */
        if ($this->pendingUpgrades !== null) {
            IndividualQuoteUpgrades::persist($this->getRecord(), $this->pendingUpgrades['state'], $this->pendingUpgrades['rate_rows']);
        }

        try {
            $detailsQuote = session()->get('details_quote', []);

            if ($detailsQuote === [] || ($detailsQuote[0]['plan_id'] ?? null) === null) {
                return;
            }

            $record = $this->getRecord();

            $res = UtilsController::storeDetailsIndividualQuote(
                $record,
                $record->toArray(),
                $detailsQuote,
                $detailsQuote,
            );

            if (! $res) {
                throw new \Exception('Error al guardar los detalles de la cotización.');
            }

            /**
             * El aviso al equipo de análisis sale por cola: son seis mensajes
             * a UltraMsg que antes retenían la pantalla —y la transacción del
             * panel— antes de redirigir al detalle.
             */
            QuoteWhatsAppDispatcher::queue(
                QuoteWhatsAppNotification::IndividualQuoteCreated,
                [
                    'code' => $record->code,
                    'agent' => Auth::user()->name,
                ],
                IndividualQuoteResource::getUrl('view', ['record' => $record->id], panel: 'business'),
            );
        } catch (\Throwable $th) {
            Notification::make()
                ->title('ERROR')
                ->body($th->getMessage())
                ->icon('heroicon-m-tag')
                ->iconColor('danger')
                ->danger()
                ->send();
        }
    }
}
