@php
    $planLabel = $planLabel ?? (string) ($quote['plan'] ?? 'Plan');
    $personsLabel = $personsLabel ?? (string) ($quote['persons_label'] ?? '');
    $showPersons = $showPersons ?? (($quote['persons'] ?? 0) > 0 || $personsLabel !== '');
@endphp
<div class="sf-quote-card__row">
    <p class="sf-quote-card__plan">
        {{ $planLabel }}
        @if ($showPersons && $personsLabel !== '')
            <span class="sf-quote-card__sep" aria-hidden="true">·</span>
            {{ $personsLabel }}
        @endif
    </p>
    @include('storefront.partials.quote-amount', ['quote' => $quote])
</div>
