@php
    $summary ??= [];
    $total = (int) ($summary['total'] ?? 0);

    $stats = [
        ['label' => 'En curso', 'value' => (int) ($summary['open'] ?? 0), 'icon' => 'heroicon-m-arrow-path', 'classes' => 'bg-sky-500/10 text-sky-700 ring-sky-600/25 dark:text-sky-300 dark:ring-sky-400/30', 'hint' => 'Tickets que Operaciones aún está atendiendo'],
        ['label' => 'Plazo vencido', 'value' => (int) ($summary['overdue'] ?? 0), 'icon' => 'heroicon-m-clock', 'classes' => ($summary['overdue'] ?? 0) > 0 ? 'bg-rose-500/10 text-rose-700 ring-rose-600/25 dark:text-rose-300 dark:ring-rose-400/30' : 'bg-gray-500/10 text-gray-600 ring-gray-500/20 dark:text-gray-300 dark:ring-white/15', 'hint' => 'Tickets abiertos que superaron el tiempo de respuesta o de solución'],
        ['label' => 'Resueltos', 'value' => (int) ($summary['done'] ?? 0), 'icon' => 'heroicon-m-check-circle', 'classes' => 'bg-emerald-500/10 text-emerald-700 ring-emerald-600/20 dark:text-emerald-300 dark:ring-emerald-400/30', 'hint' => 'Tickets que Operaciones marcó como terminados'],
    ];
@endphp

<div class="flex min-w-0 items-center gap-4 py-1">
    <span
        class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-[#0a74d6] to-[#005ca9] text-white shadow-md shadow-[#005ca9]/25"
        aria-hidden="true"
    >
        <x-filament::icon icon="heroicon-o-ticket" class="size-7" />
    </span>

    <div class="min-w-0 space-y-1.5">
        <span class="text-xs font-semibold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">Soporte de Operaciones</span>

        <p class="text-xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-2xl">
            Mis tickets
            <span class="ms-1 align-middle text-base font-semibold text-gray-400 dark:text-gray-500">{{ $total }}</span>
        </p>

        <p class="max-w-3xl text-sm font-normal text-gray-600 dark:text-gray-300">
            Solicitudes que usted envió al equipo de Operaciones. Abra una para ver su estado y las notas de seguimiento.
        </p>

        @if ($total > 0)
            <ul class="flex flex-wrap items-center gap-2 pt-1" aria-label="Resumen de tickets">
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
