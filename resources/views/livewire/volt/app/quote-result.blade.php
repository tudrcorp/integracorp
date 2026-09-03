<?php

declare(strict_types=1);

use App\Models\IndividualQuote;
use App\Models\Plan;
use App\Support\Quotes\InteractiveIndividualQuoteView;
use App\Support\Storefront\StorefrontAuth;
use App\Support\Storefront\StorefrontPlanNarrative;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.storefront')] #[Title('Cotización lista')] class extends Component
{
    public string $code = '';

    public string $planTitle = 'Plan';

    public string $fullName = '';

    public string $email = '';

    public string $phone = '';

    public string $displayName = '';

    public string $displayPhone = '';

    public bool $asAgent = false;

    public string $headline = '';

    public string $personsLabel = '';

    /** @var list<array{label: string, persons: int}> */
    public array $groups = [];

    public function mount(string $code): void
    {
        $record = IndividualQuote::query()
            ->with(['detailsQuote.ageRange', 'detailsQuote.coverage'])
            ->where('code', $code)
            ->first();

        abort_unless($record instanceof IndividualQuote, 404);

        $this->code = (string) $record->code;
        $this->fullName = (string) $record->full_name;
        $this->email = (string) $record->email;
        $this->phone = (string) $record->phone;
        $this->displayName = StorefrontPlanNarrative::personName($this->fullName);
        $this->displayPhone = StorefrontPlanNarrative::phoneLabel($this->phone);
        $this->asAgent = StorefrontAuth::currentIsAgent();

        $plan = Plan::query()->find((int) $record->plan);
        $this->planTitle = $plan instanceof Plan
            ? StorefrontPlanNarrative::for($plan)['title']
            : 'Plan';

        if ($plan instanceof Plan) {
            $view = InteractiveIndividualQuoteView::from($record, $plan, $record->detailsQuote);
            $this->headline = (string) $view['headline'];
            $this->personsLabel = (string) $view['persons_label'];
            $this->groups = collect($view['ranges'])
                ->map(static fn (array $range): array => [
                    'label' => (string) $range['age_label'],
                    'persons' => (int) $range['persons'],
                ])
                ->all();
        }
    }
}; ?>

<div
    class="sf-quote sf-quote--result"
    x-data="{
        copied: false,
        async copyCode() {
            try {
                await navigator.clipboard.writeText(@js($code));
                this.copied = true;
                setTimeout(() => this.copied = false, 1800);
            } catch (error) {
                this.copied = false;
            }
        },
    }"
>
    <section class="sf-hero sf-hero--compact sf-hero--result">
        <span class="sf-ticket__seal" aria-hidden="true">✓</span>
        <h1 class="sf-title">Tu cotización está lista</h1>
        <p class="sf-lead">{{ $asAgent ? 'Quedó registrada a tu nombre.' : 'Guarda el código para afiliarte.' }}</p>
    </section>

    <article class="sf-ticket sf-ticket--compact sf-glass">
        <div class="sf-ticket__code-row">
            <div>
                <p class="sf-ticket__kicker">Código de cotización</p>
                <p class="sf-ticket__code" id="sf-quote-code">{{ $code }}</p>
            </div>
            <button type="button" class="sf-ticket__copy sf-ticket__copy--mini" x-on:click="copyCode()">
                <span x-text="copied ? 'Copiado' : 'Copiar'"></span>
            </button>
        </div>

        <dl class="sf-review__facts sf-review__facts--plain sf-ticket__facts">
            <div>
                <dt>Plan</dt>
                <dd>{{ StorefrontPlanNarrative::planLabel($planTitle) }}</dd>
            </div>
            @if ($headline !== '')
                <div>
                    <dt>Estimado</dt>
                    <dd>{{ $headline }}</dd>
                </div>
            @endif
            <div>
                <dt>{{ $asAgent ? 'Cliente' : 'A nombre de' }}</dt>
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
            @elseif ($personsLabel !== '')
                <div>
                    <dt>Personas</dt>
                    <dd>{{ $personsLabel }}</dd>
                </div>
            @endif
            @if ($email !== '')
                <div class="sf-ticket__fact--wide">
                    <dt>Correo</dt>
                    <dd>{{ $email }}</dd>
                </div>
            @endif
            @if ($displayPhone !== '')
                <div>
                    <dt>Teléfono</dt>
                    <dd>{{ $displayPhone }}</dd>
                </div>
            @endif
        </dl>
    </article>

    <div class="sf-sticky-cta">
        <a href="{{ route('storefront.quote.proposal', $code) }}" wire:navigate class="sf-btn">Ver propuesta</a>
        <a href="{{ route('storefront.home') }}" wire:navigate class="sf-btn sf-btn-ghost">Volver a planes</a>
    </div>
</div>
