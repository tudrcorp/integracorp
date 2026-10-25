@php
    /**
     * Encabezado compacto de los dashboards (Operaciones y Métricas): contexto y período a la
     * izquierda; filtros compactos y acciones a la derecha, en una sola barra.
     *
     * @var \App\Support\Operations\Dashboard\OperationsDashboardPeriod $period
     */
@endphp

<header class="corp-page-header flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
    <div class="min-w-0">
        <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-primary-600 dark:text-primary-400">
            <x-filament::icon :icon="$eyebrowIcon" class="size-4" />
            {{ $eyebrow }}
        </p>

        <h1 class="fi-header-heading mt-1 text-2xl font-bold tracking-tight text-gray-950 sm:text-3xl dark:text-white">
            {{ $heading }}
        </h1>

        <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-950/5 px-3 py-1 font-medium text-gray-950 dark:bg-white/10 dark:text-white">
                <x-filament::icon icon="heroicon-m-calendar-days" class="size-4 text-gray-500 dark:text-gray-400" />
                {{ $period->label() }}
            </span>

            @if ($period->compare)
                <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-gray-500 ring-1 ring-gray-950/10 dark:text-gray-400 dark:ring-white/15">
                    <x-filament::icon icon="heroicon-m-arrows-right-left" class="size-4" />
                    vs {{ $period->previousLabel() }}
                </span>
            @endif

            <span class="ops-hide-in-presentation inline-flex items-center gap-1.5 text-xs text-gray-400 dark:text-gray-500">
                <x-filament::icon icon="heroicon-m-cursor-arrow-rays" class="size-4" />
                Clic en cualquier gráfico para ver el detalle
            </span>
        </div>
    </div>

    <div class="ops-hide-in-presentation flex flex-col gap-3 sm:flex-row sm:items-center">
        <div
            class="corp-header-filters w-fit rounded-lg bg-white px-1.5 py-1 shadow-sm ring-1 ring-gray-950/5 transition-opacity dark:bg-white/5 dark:ring-white/10"
            wire:loading.class="opacity-60"
            wire:target="filters"
        >
            {{ $this->getSchema('filtersForm') }}
        </div>

        @if (filled($actions))
            <x-filament::actions :actions="$actions" class="shrink-0" />
        @endif
    </div>
</header>
