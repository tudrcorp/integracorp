<?php

declare(strict_types=1);

use App\Support\Plans\PlanStructureSummary;
use App\Support\Storefront\StorefrontAuth;
use App\Support\Storefront\StorefrontCatalog;
use App\Support\Storefront\StorefrontPlanView;
use App\Support\Storefront\StorefrontQuoteDraft;
use App\Support\Storefront\StorefrontQuotePricer;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.storefront')] #[Title('Cotizar')] class extends Component
{
    public int $planId;

    public string $planTitle = '';

    public bool $asAgent = false;

    /** @var list<array{age: int|null, quantity: int}> */
    public array $people = [];

    /** @var list<array{age_range_id: int, label: string, total_persons: int}> */
    public array $ranges = [];

    public function mount(int $plan): void
    {
        $model = StorefrontCatalog::findActiveBasic($plan);
        abort_unless($model !== null, 404);

        $this->planId = (int) $model->getKey();
        $this->planTitle = (string) StorefrontPlanView::make($model)['narrative']['title'];
        $this->asAgent = StorefrontAuth::currentIsAgent();

        $draft = StorefrontQuoteDraft::forPlan($this->planId);

        if ($this->asAgent) {
            $this->ranges = StorefrontPlanView::ageRangeModels($model)
                ->unique(fn ($range): string => sprintf('%d-%d', (int) $range->age_init, (int) $range->age_end))
                ->map(function ($range) use ($draft): array {
                    $saved = collect($draft['ranges'])->firstWhere('age_range_id', $range->id);

                    return [
                        'age_range_id' => (int) $range->getKey(),
                        'label' => PlanStructureSummary::ageRangeLabel($range),
                        'total_persons' => (int) ($saved['total_persons'] ?? 0),
                    ];
                })
                ->values()
                ->all();

            return;
        }

        $this->people = $draft['people'] !== []
            ? $draft['people']
            : [['age' => null, 'quantity' => 1]];
    }

    public function addPerson(): void
    {
        $this->people[] = ['age' => null, 'quantity' => 1];
    }

    public function removePerson(int $index): void
    {
        unset($this->people[$index]);
        $this->people = array_values($this->people);

        if ($this->people === []) {
            $this->people = [['age' => null, 'quantity' => 1]];
        }
    }

    public function bumpRange(int $index, int $delta): void
    {
        if (! isset($this->ranges[$index])) {
            return;
        }

        $current = (int) $this->ranges[$index]['total_persons'];
        $this->ranges[$index]['total_persons'] = max(0, min(999, $current + $delta));
    }

    public function bumpQuantity(int $index, int $delta): void
    {
        if (! isset($this->people[$index])) {
            return;
        }

        $current = (int) ($this->people[$index]['quantity'] ?? 1);
        $this->people[$index]['quantity'] = max(1, min(99, $current + $delta));
    }

    public function headline(): string
    {
        try {
            $price = $this->currentPrice();
        } catch (\Throwable) {
            return 'Completa las edades para ver el precio';
        }

        return StorefrontQuotePricer::headline($price);
    }

    public function continue()
    {
        $model = StorefrontCatalog::findActiveBasic($this->planId);
        abort_unless($model !== null, 404);

        $ageRanges = StorefrontPlanView::ageRangeModels($model);

        try {
            if ($this->asAgent) {
                StorefrontQuoteDraft::saveRanges($this->planId, $this->ranges);
                StorefrontQuoteDraft::entries($this->planId, $ageRanges, true);
            } else {
                $people = [];

                foreach ($this->people as $row) {
                    if (! is_numeric($row['age'] ?? null)) {
                        continue;
                    }

                    $people[] = [
                        'age' => (int) $row['age'],
                        'quantity' => (int) ($row['quantity'] ?? 1),
                    ];
                }

                StorefrontQuoteDraft::savePeople($this->planId, $people);
                StorefrontQuoteDraft::entries($this->planId, $ageRanges, false);
            }
        } catch (ValidationException $exception) {
            throw $exception;
        }

        return $this->redirect(route('storefront.quote.details', $this->planId), navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    private function currentPrice(): array
    {
        $model = StorefrontCatalog::findActiveBasic($this->planId);

        if ($model === null) {
            return StorefrontQuotePricer::quote([], collect());
        }

        $ageRanges = StorefrontPlanView::ageRangeModels($model);

        if ($this->asAgent) {
            $entries = StorefrontQuoteDraft::normalizeRanges($this->ranges);
        } else {
            $people = [];

            foreach ($this->people as $row) {
                if (! is_numeric($row['age'] ?? null)) {
                    continue;
                }

                $people[] = [
                    'age' => (int) $row['age'],
                    'quantity' => (int) ($row['quantity'] ?? 1),
                ];
            }

            $entries = StorefrontQuoteDraft::entriesFromPeople($this->planId, $people, $ageRanges);
        }

        if ($entries === []) {
            return StorefrontQuotePricer::quote([], collect());
        }

        return StorefrontQuotePricer::quoteForPlan($this->planId, $entries);
    }
}; ?>

<div class="sf-quote">
    @include('storefront.partials.quote-steps', ['step' => 1])

    <section class="sf-hero">
        <p class="sf-kicker">{{ $planTitle }}</p>
        <h1 class="sf-title">{{ $asAgent ? 'Grupo familiar del cliente' : '¿Quiénes se cubren?' }}</h1>
        <p class="sf-lead">
            @if ($asAgent)
                Indica cuántas personas van en cada rango. El precio se actualiza al instante.
            @else
                Agrega las edades. Sin formularios eternos: una persona tras otra, y ves el precio en vivo.
            @endif
        </p>
    </section>

    <div class="sf-live-total sf-glass">
        <span class="sf-kicker">Estimado</span>
        <strong>{{ $this->headline() }}</strong>
    </div>

    @if ($asAgent)
        <section class="sf-section sf-glass">
            @foreach ($ranges as $index => $range)
                <div class="sf-range" wire:key="range-{{ $range['age_range_id'] }}">
                    <div>
                        <div class="sf-rate__label">{{ $range['label'] }}</div>
                        <div class="sf-lead" style="font-size: 0.78rem;">Personas</div>
                    </div>
                    <div class="sf-stepper">
                        <button type="button" wire:click="bumpRange({{ $index }}, -1)" aria-label="Quitar">−</button>
                        <span>{{ $range['total_persons'] }}</span>
                        <button type="button" wire:click="bumpRange({{ $index }}, 1)" aria-label="Agregar">+</button>
                    </div>
                </div>
            @endforeach
            @error('ranges') <p class="sf-error">{{ $message }}</p> @enderror
        </section>
    @else
        <section class="sf-section sf-glass">
            @foreach ($people as $index => $person)
                <div class="sf-person" wire:key="person-{{ $index }}">
                    <div class="sf-field" style="margin-bottom: 0;">
                        <label>Edad</label>
                        <input type="number" inputmode="numeric" min="0" max="120" wire:model.live.debounce.250ms="people.{{ $index }}.age" placeholder="Años">
                    </div>
                    <div>
                        <label class="sf-kicker" style="display:block; margin-bottom: 0.4rem;">Personas</label>
                        <div class="sf-stepper">
                            <button type="button" wire:click="bumpQuantity({{ $index }}, -1)">−</button>
                            <span>{{ $person['quantity'] }}</span>
                            <button type="button" wire:click="bumpQuantity({{ $index }}, 1)">+</button>
                        </div>
                    </div>
                </div>
            @endforeach
            @error('people') <p class="sf-error">{{ $message }}</p> @enderror

            <button type="button" class="sf-btn sf-btn-ghost" style="margin-top: 0.4rem;" wire:click="addPerson">
                Agregar otra persona
            </button>
        </section>
    @endif

    <div class="sf-sticky-cta">
        <button type="button" class="sf-btn" wire:click="continue" wire:loading.attr="disabled" wire:target="continue" wire:loading.class="is-busy">
            @include('storefront.partials.btn-loading', ['target' => 'continue', 'label' => 'Continuar', 'wait' => 'Calculando…'])
        </button>
        <a href="{{ route('storefront.plan', $planId) }}" wire:navigate class="sf-btn sf-btn-ghost">Volver al plan</a>
    </div>
</div>
