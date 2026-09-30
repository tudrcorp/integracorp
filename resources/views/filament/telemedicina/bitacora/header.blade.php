@php
    $case ??= null;
    $gradient ??= 'from-[#0a74d6] to-[#005ca9] shadow-[#005ca9]/25';

    $value = static fn (mixed $text): ?string => filled($text) && trim((string) $text) !== '—' ? trim((string) $text) : null;

    $status = $value($case['status'] ?? null);
    $statusKey = mb_strtoupper((string) $status);
    $statusClasses = match ($statusKey) {
        'EN SEGUIMIENTO' => 'bg-sky-500/10 text-sky-700 ring-sky-600/25 dark:text-sky-300 dark:ring-sky-400/30',
        'ALTA MEDICA', 'ALTA MÉDICA' => 'bg-emerald-500/10 text-emerald-700 ring-emerald-600/20 dark:text-emerald-300 dark:ring-emerald-400/30',
        'ASIGNADO' => 'bg-amber-500/10 text-amber-700 ring-amber-600/25 dark:text-amber-300 dark:ring-amber-400/30',
        default => 'bg-gray-500/10 text-gray-700 ring-gray-500/20 dark:text-gray-200 dark:ring-white/15',
    };

    $details = $case === null ? [] : array_filter([
        'Médico' => $value($case['doctor'] ?? null) !== null ? 'Dr(a). '.$case['doctor'] : null,
        'Apertura' => $value($case['opened_at'] ?? null),
        'Última actualización' => $value($case['updated_at'] ?? null),
    ]);

    $searchHints = [
        ['icon' => 'heroicon-m-hashtag', 'label' => 'Código de caso'],
        ['icon' => 'heroicon-m-user', 'label' => 'Nombre del paciente'],
        ['icon' => 'heroicon-m-identification', 'label' => 'Cédula'],
    ];
@endphp

<div class="flex min-w-0 items-center gap-4 py-1">
    <span
        class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br text-white shadow-md {{ $gradient }}"
        aria-hidden="true"
    >
        <x-filament::icon icon="heroicon-o-clipboard-document-check" class="size-7" />
    </span>

    <div class="min-w-0 space-y-1.5">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-semibold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">Expediente clínico</span>

            @if ($status !== null)
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $statusClasses }}">{{ $status }}</span>
            @endif

            @if ($value($case['priority'] ?? null) !== null)
                <span class="inline-flex items-center gap-1 rounded-full bg-rose-500/10 px-2.5 py-0.5 text-xs font-semibold text-rose-700 ring-1 ring-rose-600/20 dark:text-rose-300 dark:ring-rose-400/30">
                    <x-filament::icon icon="heroicon-m-bolt" class="size-3.5" />
                    {{ $case['priority'] }}
                </span>
            @endif
        </div>

        @if ($case === null)
            <p class="text-xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-2xl">Bitácora de caso</p>

            <p class="max-w-3xl text-sm font-normal text-gray-600 dark:text-gray-300">
                Reúne todo el expediente del caso, desde la apertura hasta el alta médica: consultas, seguimientos, estudios, medicamentos y documentos.
            </p>

            <ul class="flex flex-wrap items-center gap-2 pt-1" aria-label="Puede buscar por">
                <li class="text-xs font-medium text-gray-500 dark:text-gray-400">Busque por:</li>
                @foreach ($searchHints as $hint)
                    <li class="inline-flex items-center gap-1.5 rounded-full bg-[#005ca9]/10 px-2.5 py-1 text-xs font-semibold text-[#005ca9] ring-1 ring-[#005ca9]/25 dark:bg-sky-400/10 dark:text-sky-300 dark:ring-sky-400/30">
                        <x-filament::icon :icon="$hint['icon']" class="size-3.5" />
                        {{ $hint['label'] }}
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-2xl">
                Bitácora · Caso N.º {{ $case['code'] }}
            </p>

            @if ($value($case['patient'] ?? null) !== null)
                <p class="truncate text-base font-semibold text-gray-800 dark:text-gray-100" title="{{ $case['patient'] }}">
                    {{ $case['patient'] }}
                    @if ($value($case['document'] ?? null) !== null)
                        <span class="font-normal text-gray-500 dark:text-gray-400">· {{ $case['document'] }}</span>
                    @endif
                </p>
            @endif

            @if ($details !== [])
                <dl class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-600 dark:text-gray-300">
                    @foreach ($details as $label => $detail)
                        <div class="flex items-center gap-1">
                            <dt class="font-medium text-gray-500 dark:text-gray-400">{{ $label }}:</dt>
                            <dd class="font-semibold text-gray-800 dark:text-gray-100">{{ $detail }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        @endif
    </div>
</div>
