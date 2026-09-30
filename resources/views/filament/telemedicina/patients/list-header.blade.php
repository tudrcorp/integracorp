@php
    $summary ??= [];
    $linked ??= true;
    $patients = (int) ($summary['patients'] ?? 0);

    $stats = [
        ['label' => 'Por atender', 'value' => (int) ($summary['assigned'] ?? 0), 'icon' => 'heroicon-m-bell-alert', 'classes' => ($summary['assigned'] ?? 0) > 0 ? 'bg-amber-500/10 text-amber-700 ring-amber-600/25 dark:text-amber-300 dark:ring-amber-400/30' : 'bg-gray-500/10 text-gray-600 ring-gray-500/20 dark:text-gray-300 dark:ring-white/15', 'hint' => 'Casos recién asignados a usted, pendientes de iniciar seguimiento'],
        ['label' => 'En seguimiento', 'value' => (int) ($summary['follow_up'] ?? 0), 'icon' => 'heroicon-m-arrow-path', 'classes' => 'bg-sky-500/10 text-sky-700 ring-sky-600/25 dark:text-sky-300 dark:ring-sky-400/30', 'hint' => 'Casos con consultas en curso'],
        ['label' => 'Alta médica', 'value' => (int) ($summary['discharged'] ?? 0), 'icon' => 'heroicon-m-check-badge', 'classes' => 'bg-emerald-500/10 text-emerald-700 ring-emerald-600/20 dark:text-emerald-300 dark:ring-emerald-400/30', 'hint' => 'Casos a los que ya se les dio de alta'],
    ];
@endphp

<div class="flex min-w-0 items-center gap-4 py-1">
    <span
        class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-[#0a74d6] to-[#005ca9] text-white shadow-md shadow-[#005ca9]/25"
        aria-hidden="true"
    >
        <x-filament::icon icon="heroicon-o-user-group" class="size-7" />
    </span>

    <div class="min-w-0 space-y-1.5">
        <span class="text-xs font-semibold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">Gestión telemédica</span>

        <p class="text-xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-2xl">
            Ficha del paciente
            @if ($linked)
                <span class="ms-1 align-middle text-base font-semibold text-gray-400 dark:text-gray-500" title="Pacientes bajo su atención">{{ $patients }}</span>
            @endif
        </p>

        <p class="max-w-3xl text-sm font-normal text-gray-600 dark:text-gray-300">
            @if ($linked)
                Pacientes con casos bajo su atención. Abra la ficha para ver sus datos y plan, consultar la historia clínica o registrar una consulta.
            @else
                Su usuario aún no tiene un médico asociado. Contacte a Operaciones para ver a sus pacientes.
            @endif
        </p>

        @if ($linked && $patients > 0)
            <ul class="flex flex-wrap items-center gap-2 pt-1" aria-label="Resumen de sus casos">
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
