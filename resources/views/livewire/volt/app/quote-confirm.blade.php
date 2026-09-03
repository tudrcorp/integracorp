<?php

declare(strict_types=1);

use App\Support\Storefront\StorefrontAuth;
use App\Support\Storefront\StorefrontCatalog;
use App\Support\Storefront\StorefrontPlanNarrative;
use App\Support\Storefront\StorefrontPlanView;
use App\Support\Storefront\StorefrontQuoteCreator;
use App\Support\Storefront\StorefrontQuoteDraft;
use App\Support\Storefront\StorefrontQuotePricer;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.storefront')] #[Title('Confirmar')] class extends Component
{
    public int $planId;

    public string $planTitle = '';

    public bool $asAgent = false;

    public string $fullName = '';

    public string $email = '';

    public string $phone = '';

    public string $headline = '';

    public string $coverage = '';

    public string $displayName = '';

    public string $displayPhone = '';

    public string $errorMessage = '';

    /** @var list<array{label: string, persons: int}> */
    public array $groups = [];

    public function mount(int $plan): void
    {
        $model = StorefrontCatalog::findActiveBasic($plan);
        abort_unless($model !== null, 404);

        $this->planId = (int) $model->getKey();
        $this->planTitle = (string) StorefrontPlanView::make($model)['narrative']['title'];
        $this->asAgent = StorefrontAuth::currentIsAgent();

        $draft = StorefrontQuoteDraft::forPlan($this->planId);

        if ($draft['people'] === [] && $draft['ranges'] === []) {
            $this->redirect(route('storefront.quote.people', $this->planId), navigate: true);

            return;
        }

        if (! StorefrontQuoteDraft::hasContact($draft)) {
            $this->redirect(route('storefront.quote.details', $this->planId), navigate: true);

            return;
        }

        $this->fullName = (string) $draft['full_name'];
        $this->email = (string) $draft['email'];
        $this->phone = (string) $draft['phone'];
        $this->displayName = StorefrontPlanNarrative::personName($this->fullName);
        $this->displayPhone = StorefrontPlanNarrative::phoneLabel($this->phone);

        $ageRanges = StorefrontPlanView::ageRangeModels($model);
        $this->groups = StorefrontQuoteDraft::groupSummary($this->planId, $ageRanges, $this->asAgent);

        try {
            $entries = StorefrontQuoteDraft::entries($this->planId, $ageRanges, $this->asAgent);
            $quote = StorefrontQuotePricer::quoteForPlan($this->planId, $entries);
            $this->headline = StorefrontQuotePricer::amountLabel($quote);
            $this->coverage = StorefrontQuotePricer::coverageLabel($quote);
        } catch (\Throwable) {
            $this->headline = 'Revisa las personas cubiertas';
            $this->coverage = '';
        }
    }

    public function submit()
    {
        $this->errorMessage = '';
        $model = StorefrontCatalog::findActiveBasic($this->planId);
        abort_unless($model !== null, 404);

        $draft = StorefrontQuoteDraft::forPlan($this->planId);

        try {
            $entries = StorefrontQuoteDraft::entries($this->planId, StorefrontPlanView::ageRangeModels($model), $this->asAgent);
            $created = StorefrontQuoteCreator::create(
                $model,
                $entries,
                (string) $draft['full_name'],
                (string) $draft['email'],
                (string) $draft['phone'],
                StorefrontAuth::user(),
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\RuntimeException $exception) {
            $this->errorMessage = $exception->getMessage();

            return;
        }

        StorefrontQuoteDraft::clear();

        $payload = json_encode([
            'code' => $created['code'],
            'asAgent' => $this->asAgent,
            'url' => route('storefront.quote.proposal', ['code' => $created['code']]),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $this->js('window.dispatchEvent(new CustomEvent("storefront-quote-success", { detail: '.$payload.' }));');
    }
}; ?>

<div class="sf-quote sf-quote--confirm">
    @include('storefront.partials.quote-steps', ['step' => 3, 'planId' => $planId])

    <section class="sf-hero sf-hero--compact">
        <h1 class="sf-title">Revisa y confirma</h1>
        <p class="sf-lead">Si algo no cuadra, corrige los datos antes de generarla.</p>
    </section>

    <article class="sf-review sf-review--compact sf-glass">
        <div class="sf-review__top">
            <div>
                <p class="sf-review__kicker">Plan</p>
                <h2 class="sf-review__plan">{{ StorefrontPlanNarrative::planLabel($planTitle) }}</h2>
            </div>
            <div class="sf-review__price">
                <span>Estimado</span>
                <strong>{{ $headline }}</strong>
                @if ($coverage !== '')
                    <em>{{ $coverage }}</em>
                @endif
            </div>
        </div>

        <dl class="sf-review__facts">
            <div>
                <dt>{{ $asAgent ? 'Cliente' : 'Titular' }}</dt>
                <dd>{{ $displayName }}</dd>
            </div>
            @if ($groups !== [])
                <div>
                    <dt>Grupo</dt>
                    <dd>
                        @foreach ($groups as $group)
                            {{ $group['persons'] }} {{ $group['persons'] === 1 ? 'persona' : 'personas' }} · {{ $group['label'] }}@if (! $loop->last)<br>@endif
                        @endforeach
                    </dd>
                </div>
            @endif
            <div>
                <dt>Correo</dt>
                <dd>{{ $email }}</dd>
            </div>
            <div>
                <dt>Teléfono</dt>
                <dd>{{ $displayPhone }}</dd>
            </div>
        </dl>

        @if ($asAgent)
            <p class="sf-review__note">Se registrará a tu nombre como agente.</p>
        @endif
        @if ($errorMessage !== '')
            <p class="sf-error">{{ $errorMessage }}</p>
        @endif
    </article>

    <div class="sf-sticky-cta">
        <button type="button" class="sf-btn" wire:click="submit" wire:loading.attr="disabled" wire:target="submit" wire:loading.class="is-busy">
            @include('storefront.partials.btn-loading', ['target' => 'submit', 'label' => 'Generar cotización', 'wait' => 'Generando tu cotización…'])
        </button>
        <a href="{{ route('storefront.quote.details', $planId) }}" wire:navigate class="sf-btn sf-btn-ghost">Corregir datos</a>
    </div>
</div>
