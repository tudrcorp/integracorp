{{--
    Filament omite el aspect-ratio del contenedor cuando el widget declara maxHeight,
    y entonces el canvas se queda en la altura por defecto del elemento (150px).
    Le damos altura real al contenedor: Chart.js la respeta con maintainAspectRatio: false.
--}}
<div class="tdg-chart-tall">
    <style>
        .tdg-chart-tall .fi-wi-chart-canvas-ctn {
            height: 26rem;
        }

        @media (max-width: 640px) {
            .tdg-chart-tall .fi-wi-chart-canvas-ctn {
                height: 18rem;
            }
        }
    </style>

    @include('filament-widgets::chart-widget')
</div>
