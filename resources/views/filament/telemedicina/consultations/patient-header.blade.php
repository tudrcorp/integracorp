@php
    $patientName ??= null;
    $document ??= null;
    $age ??= null;
    $sex ??= null;
    $caseCode ??= null;
    $caseStatus ??= null;
    $managedBy ??= null;

    $initials = collect(preg_split('/\s+/u', trim((string) $patientName), -1, PREG_SPLIT_NO_EMPTY) ?: [])
        ->filter()
        ->pipe(fn ($parts) => $parts->count() > 1
            ? mb_substr($parts->first(), 0, 1).mb_substr($parts->last(), 0, 1)
            : mb_substr((string) $parts->first(), 0, 2));

    $details = array_values(array_filter([
        filled($document) ? ['label' => 'Cédula', 'value' => $document] : null,
        filled($age) ? ['label' => 'Edad', 'value' => $age.' '.((int) $age === 1 ? 'año' : 'años')] : null,
        filled($sex) ? ['label' => 'Sexo', 'value' => $sex] : null,
        filled($managedBy) ? ['label' => 'Gestiona', 'value' => $managedBy] : null,
    ]));
@endphp

<div class="flex min-w-0 items-center gap-4 py-1">
    <span
        class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-[#0a74d6] to-[#005ca9] text-lg font-semibold uppercase text-white shadow-md shadow-[#005ca9]/25"
        aria-hidden="true"
    >
        {{ mb_strtoupper($initials !== '' ? $initials : '?') }}
    </span>

    <div class="min-w-0 space-y-1.5">
        <div class="flex flex-wrap items-center gap-2">
            @if (filled($caseCode))
                <span class="inline-flex items-center gap-1 rounded-full bg-[#005ca9]/10 px-2.5 py-0.5 text-xs font-semibold tracking-wide text-[#005ca9] ring-1 ring-[#005ca9]/25 dark:bg-sky-400/10 dark:text-sky-300 dark:ring-sky-400/30">
                    <x-filament::icon icon="heroicon-m-folder-open" class="size-3.5" />
                    Caso N.º {{ $caseCode }}
                </span>
            @endif

            @if (filled($caseStatus))
                <span class="inline-flex items-center rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-600/20 dark:text-emerald-300 dark:ring-emerald-400/30">
                    {{ $caseStatus }}
                </span>
            @endif
        </div>

        <p class="truncate text-xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-2xl" title="{{ $patientName }}">
            {{ filled($patientName) ? $patientName : 'Paciente sin nombre' }}
        </p>

        @if ($details !== [])
            <dl class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-600 dark:text-gray-300">
                @foreach ($details as $detail)
                    <div class="flex items-center gap-1">
                        <dt class="font-medium text-gray-500 dark:text-gray-400">{{ $detail['label'] }}:</dt>
                        <dd class="font-semibold text-gray-800 dark:text-gray-100">{{ $detail['value'] }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </div>
</div>
