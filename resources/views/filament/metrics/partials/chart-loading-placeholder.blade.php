@props([
    'columnSpan' => [],
    'columnStart' => [],
    'height' => '28rem',
    'heading' => 'Cargando gráfico',
])

@php
    $bars = [38, 62, 48, 76, 54, 84, 46, 70, 58, 66];
@endphp

<div
    {{
        ($attributes ?? new \Illuminate\View\ComponentAttributeBag)
            ->gridColumn($columnSpan, $columnStart)
            ->class(['fi-metrics-chart-loading'])
            ->style(['min-height: ' . ($height ?? '28rem')])
    }}
    role="status"
    aria-live="polite"
    aria-busy="true"
>
    <div class="fi-metrics-chart-loading__shell">
        <div class="fi-metrics-chart-loading__header">
            <div class="fi-metrics-chart-loading__title-block">
                <span class="fi-metrics-chart-loading__eyebrow">Métricas</span>
                <p class="fi-metrics-chart-loading__title">{{ $heading }}</p>
                <span class="fi-metrics-chart-loading__line fi-metrics-chart-loading__line--wide"></span>
            </div>
            <div class="fi-metrics-chart-loading__chips" aria-hidden="true">
                <span class="fi-metrics-chart-loading__chip"></span>
                <span class="fi-metrics-chart-loading__chip"></span>
                <span class="fi-metrics-chart-loading__chip fi-metrics-chart-loading__chip--accent"></span>
            </div>
        </div>

        <div class="fi-metrics-chart-loading__canvas" aria-hidden="true">
            <div class="fi-metrics-chart-loading__bars">
                @foreach ($bars as $index => $heightPercent)
                    <span
                        class="fi-metrics-chart-loading__bar"
                        style="--fi-metrics-bar-h: {{ $heightPercent }}%; --fi-metrics-bar-delay: {{ $index * 70 }}ms;"
                    ></span>
                @endforeach
            </div>
            <div class="fi-metrics-chart-loading__pulse"></div>
        </div>

        <p class="fi-metrics-chart-loading__hint">
            <span class="fi-metrics-chart-loading__spinner" aria-hidden="true"></span>
            Preparando visualización…
        </p>
    </div>
</div>
