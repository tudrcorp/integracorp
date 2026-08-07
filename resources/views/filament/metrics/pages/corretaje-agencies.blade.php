<x-filament-panels::page>
    @php
        $shell = $this->getMetricsShellData();
    @endphp

    @include('filament.metrics.partials.bar-value-labels-plugin')

    <div class="fi-metrics-module" data-metrics-module="{{ $shell['key'] }}">
        <p class="fi-metrics-module__eyebrow">{{ $shell['eyebrow'] }}</p>
        <h1 class="fi-metrics-module__title">{{ $shell['title'] }}</h1>
        <p class="fi-metrics-module__subtitle">{{ $shell['subtitle'] }}</p>
    </div>
</x-filament-panels::page>
