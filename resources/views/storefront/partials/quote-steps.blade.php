@props([
    'step' => 1,
    'planId' => null,
])

<nav class="sf-steps" aria-label="Pasos de la cotización">
    @foreach ([1 => 'Personas', 2 => 'Datos', 3 => 'Confirmar'] as $number => $label)
        @php
            $isCurrent = (int) $step === $number;
            $isDone = (int) $step > $number;
            $route = match ($number) {
                1 => $planId ? route('storefront.quote.people', $planId) : null,
                2 => $planId ? route('storefront.quote.details', $planId) : null,
                default => null,
            };
        @endphp
        @if ($route && ($isDone || $isCurrent))
            <a
                href="{{ $route }}"
                wire:navigate
                @class(['is-on' => $isCurrent || $isDone])
                @if ($isCurrent) aria-current="step" @endif
            >
                <i></i>
                {{ $label }}
            </a>
        @else
            <span @class(['is-on' => $isCurrent])>
                <i></i>
                {{ $label }}
            </span>
        @endif
    @endforeach
</nav>
