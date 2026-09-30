@php
    $summary ??= [];
    $scopeLabel ??= 'Sus casos asignados';
    $total = (int) ($summary['total'] ?? 0);

    $muted = 'bg-gray-500/10 text-gray-600 ring-gray-500/20 dark:text-gray-300 dark:ring-white/15';

    $stats = [
        ['label' => 'Por atender', 'value' => (int) ($summary['assigned'] ?? 0), 'icon' => 'heroicon-m-bell-alert', 'classes' => ($summary['assigned'] ?? 0) > 0 ? 'bg-amber-500/10 text-amber-700 ring-amber-600/25 dark:text-amber-300 dark:ring-amber-400/30' : $muted, 'hint' => 'Casos recién asignados, pendientes de iniciar seguimiento'],
        ['label' => 'En seguimiento', 'value' => (int) ($summary['follow_up'] ?? 0), 'icon' => 'heroicon-m-arrow-path', 'classes' => 'bg-sky-500/10 text-sky-700 ring-sky-600/25 dark:text-sky-300 dark:ring-sky-400/30', 'hint' => 'Casos con consultas en curso'],
        ['label' => 'Urgentes', 'value' => (int) ($summary['urgent'] ?? 0), 'icon' => 'heroicon-m-bolt', 'classes' => ($summary['urgent'] ?? 0) > 0 ? 'bg-rose-500/10 text-rose-700 ring-rose-600/25 dark:text-rose-300 dark:ring-rose-400/30' : $muted, 'hint' => 'Casos con prioridad Urgencia, Emergencia o Crítico'],
        ['label' => 'Nuevos hoy', 'value' => (int) ($summary['today'] ?? 0), 'icon' => 'heroicon-m-sparkles', 'classes' => ($summary['today'] ?? 0) > 0 ? 'bg-[#005ca9]/10 text-[#005ca9] ring-[#005ca9]/25 dark:bg-sky-400/10 dark:text-sky-300 dark:ring-sky-400/30' : $muted, 'hint' => 'Casos abiertos hoy'],
    ];
@endphp

<div class="flex min-w-0 items-center gap-4 py-1">
    <span
        class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-[#0a74d6] to-[#005ca9] text-white shadow-md shadow-[#005ca9]/25"
        aria-hidden="true"
    >
        <x-filament::icon icon="heroicon-o-clipboard-document-list" class="size-7" />
    </span>

    <div class="min-w-0 space-y-1.5">
        <span class="text-xs font-semibold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">{{ $scopeLabel }}</span>

        <p class="text-xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-2xl">
            Gestión de casos
            <span class="ms-1 align-middle text-base font-semibold text-gray-400 dark:text-gray-500" title="Casos activos en esta lista">{{ $total }}</span>
        </p>

        <p class="max-w-3xl text-sm font-normal text-gray-600 dark:text-gray-300">
            Casos activos de telemedicina. Abra uno para registrar consultas, documentos y seguimiento; al dar el alta médica sale de esta lista.
        </p>

        @if ($total > 0)
            <ul class="flex flex-wrap items-center gap-2 pt-1" aria-label="Resumen de casos">
                @foreach ($stats as $stat)
                    <li
                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $stat['classes'] }}"
                        title="{{ $stat['hint'] }}"
                    >
                        <x-filament::icon :icon="$stat['icon']" class="size-3.5" />
                        <span>{{ $stat['label'] }}</span>
                        <span class="tabular-nums">{{ $stat['value'] }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
