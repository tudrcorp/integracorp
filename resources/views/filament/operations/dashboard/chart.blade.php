{{-- Gráfico del Dashboard de Operaciones con altura explícita (ver partials/chart-canvas). --}}
<x-filament-widgets::widget class="fi-wi-chart">
    <x-filament::section :heading="$this->getHeading()" :description="$this->getDescription()">
        @include('filament.operations.dashboard.partials.chart-canvas')
    </x-filament::section>
</x-filament-widgets::widget>
