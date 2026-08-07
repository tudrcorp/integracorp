<x-filament-panels::page>
    @php
        $shell = $this->getMetricsShellData();
    @endphp

    <div class="fi-metrics-module" data-metrics-module="{{ $shell['key'] }}">
        <p class="fi-metrics-module__eyebrow">{{ $shell['eyebrow'] }}</p>
        <h1 class="fi-metrics-module__title">{{ $shell['title'] }}</h1>
        <p class="fi-metrics-module__subtitle">{{ $shell['subtitle'] }}</p>

        <div class="fi-metrics-module__grid">
            <article class="fi-metrics-liquid-kpi">
                <span class="fi-metrics-liquid-kpi__label">Estado</span>
                <strong class="fi-metrics-liquid-kpi__value">Listo</strong>
                <span class="fi-metrics-liquid-kpi__hint">Shell Liquid Glass activo</span>
            </article>
            <article class="fi-metrics-liquid-kpi">
                <span class="fi-metrics-liquid-kpi__label">Fuente</span>
                <strong class="fi-metrics-liquid-kpi__value">API</strong>
                <span class="fi-metrics-liquid-kpi__hint">integracorp-api · pendiente de endpoints</span>
            </article>
            <article class="fi-metrics-liquid-kpi">
                <span class="fi-metrics-liquid-kpi__label">Módulo</span>
                <strong class="fi-metrics-liquid-kpi__value">{{ $shell['key'] }}</strong>
                <span class="fi-metrics-liquid-kpi__hint">Navegación lateral conectada</span>
            </article>
        </div>
    </div>
</x-filament-panels::page>
