@php
    $caseCode ??= null;
    $status ??= null;
    $priority ??= null;
    $managedBy ??= null;
    $patientName ??= null;
    $patientDetails ??= [];
    $doctorName ??= null;
    $reason ??= null;
    $openedAt ??= null;
    $openedAtHuman ??= null;
    $consultationsCount ??= null;

    $statusKey = mb_strtoupper(trim((string) $status));
    $statusClasses = match ($statusKey) {
        'EN SEGUIMIENTO' => 'bg-sky-500/10 text-sky-700 ring-sky-600/25 dark:text-sky-300 dark:ring-sky-400/30',
        'ALTA MEDICA', 'ALTA MÉDICA' => 'bg-emerald-500/10 text-emerald-700 ring-emerald-600/20 dark:text-emerald-300 dark:ring-emerald-400/30',
        'ASIGNADO' => 'bg-amber-500/10 text-amber-700 ring-amber-600/25 dark:text-amber-300 dark:ring-amber-400/30',
        default => 'bg-gray-500/10 text-gray-700 ring-gray-500/20 dark:text-gray-200 dark:ring-white/15',
    };
@endphp

<div class="flex min-w-0 items-start gap-4 py-1">
    <span
        class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-[#0a74d6] to-[#005ca9] text-white shadow-md shadow-[#005ca9]/25"
        aria-hidden="true"
    >
        <x-filament::icon icon="heroicon-o-folder-open" class="size-7" />
    </span>

    <div class="min-w-0 space-y-1.5">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-semibold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">Detalle del caso</span>

            @if (filled($status))
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $statusClasses }}">{{ $status }}</span>
            @endif

            @if (filled($priority))
                <span class="inline-flex items-center gap-1 rounded-full bg-rose-500/10 px-2.5 py-0.5 text-xs font-semibold text-rose-700 ring-1 ring-rose-600/20 dark:text-rose-300 dark:ring-rose-400/30">
                    <x-filament::icon icon="heroicon-m-bolt" class="size-3.5" />
                    {{ $priority }}
                </span>
            @endif

            @if (filled($managedBy))
                <span class="inline-flex items-center rounded-full bg-[#005ca9]/10 px-2.5 py-0.5 text-xs font-semibold text-[#005ca9] ring-1 ring-[#005ca9]/25 dark:bg-sky-400/10 dark:text-sky-300 dark:ring-sky-400/30">
                    {{ $managedBy }}
                </span>
            @endif
        </div>

        <p class="text-xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-2xl">
            Caso N.º {{ filled($caseCode) ? $caseCode : '—' }}
        </p>

        <p class="truncate text-base font-semibold text-gray-800 dark:text-gray-100" title="{{ $patientName }}">
            {{ filled($patientName) ? $patientName : 'Paciente sin nombre' }}
            @if ($patientDetails !== [])
                <span class="font-normal text-gray-500 dark:text-gray-400">· {{ implode(' · ', $patientDetails) }}</span>
            @endif
        </p>

        <dl class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-600 dark:text-gray-300">
            @if (filled($doctorName))
                <div class="flex items-center gap-1">
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Médico:</dt>
                    <dd class="font-semibold text-gray-800 dark:text-gray-100">Dr(a). {{ $doctorName }}</dd>
                </div>
            @endif

            @if (filled($openedAt))
                <div class="flex items-center gap-1">
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Abierto:</dt>
                    <dd class="font-semibold text-gray-800 dark:text-gray-100" title="{{ $openedAt }}">{{ $openedAt }}@if (filled($openedAtHuman)) <span class="font-normal text-gray-500 dark:text-gray-400">({{ $openedAtHuman }})</span>@endif</dd>
                </div>
            @endif

            @if ($consultationsCount !== null)
                <div class="flex items-center gap-1">
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Consultas:</dt>
                    <dd class="font-semibold text-gray-800 dark:text-gray-100">{{ (int) $consultationsCount }}</dd>
                </div>
            @endif
        </dl>

        @if (filled($reason))
            <p class="line-clamp-2 max-w-3xl text-sm text-gray-600 dark:text-gray-300" title="{{ $reason }}">
                <span class="font-medium text-gray-500 dark:text-gray-400">Motivo:</span> {{ $reason }}
            </p>
        @endif
    </div>
</div>
