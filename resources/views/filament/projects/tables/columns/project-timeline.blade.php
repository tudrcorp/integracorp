@php
    use App\Support\Filament\ProjectManagement\ProjectManagementProjectTable;

    $record = $getRecord();
    $timeline = ProjectManagementProjectTable::timelineMeta($record);

    $statusGradient = 'linear-gradient(90deg, #22c55e 0%, #eab308 50%, #ef4444 100%)';
@endphp

<div class="min-w-[12rem] space-y-2 py-1">
    <div class="flex items-center justify-between gap-2 text-[11px]">
        <span class="font-medium text-gray-600 dark:text-gray-300">{{ $timeline['label'] }}</span>
        @if ($timeline['percent'] !== null)
            <span class="font-semibold text-gray-950 dark:text-white">{{ $timeline['percent'] }}%</span>
        @endif
    </div>

    <div
        class="relative h-2.5 overflow-hidden rounded-full bg-gray-200/80 shadow-inner dark:bg-white/10"
        role="progressbar"
        @if ($timeline['percent'] !== null)
            aria-valuenow="{{ $timeline['percent'] }}"
            aria-valuemin="0"
            aria-valuemax="100"
        @endif
        aria-label="Avance del cronograma"
    >
        <div
            class="absolute inset-0 rounded-full opacity-25 dark:opacity-30"
            style="background: {{ $statusGradient }};"
        ></div>

        @if ($timeline['percent'] !== null)
            <div
                class="relative h-full rounded-full transition-all duration-500 ease-out"
                style="width: {{ $timeline['percent'] }}%; background: {{ $statusGradient }}; box-shadow: 0 0 10px color-mix(in srgb, #22c55e 35%, transparent);"
            ></div>
        @else
            <div
                class="relative h-full rounded-full opacity-70"
                style="width: 35%; background: {{ $statusGradient }};"
            ></div>
        @endif
    </div>

    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 text-[11px] text-gray-500 dark:text-gray-400">
        <div class="flex flex-wrap gap-x-3 gap-y-1">
            <span class="inline-flex items-center gap-1">
                <x-filament::icon icon="heroicon-m-play" class="size-3.5" />
                {{ $timeline['start_label'] ?? '—' }}
            </span>
            <span class="inline-flex items-center gap-1">
                <x-filament::icon icon="heroicon-m-flag" class="size-3.5" />
                {{ $timeline['end_label'] ?? '—' }}
            </span>
        </div>

        <span class="inline-flex items-center gap-2 text-[10px] font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
            <span class="inline-flex items-center gap-1">
                <span class="size-1.5 rounded-full bg-emerald-500"></span>
                En tiempo
            </span>
            <span class="inline-flex items-center gap-1">
                <span class="size-1.5 rounded-full bg-amber-400"></span>
                Riesgo
            </span>
            <span class="inline-flex items-center gap-1">
                <span class="size-1.5 rounded-full bg-red-500"></span>
                Crítico
            </span>
        </span>
    </div>
</div>
