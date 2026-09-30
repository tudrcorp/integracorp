@php
    $fullName ??= null;
    $photoUrl ??= null;
    $specialty ??= null;
    $status ??= null;
    $details ??= [];
    $consultationsCount ??= null;

    $initials = collect(preg_split('/\s+/u', trim((string) $fullName), -1, PREG_SPLIT_NO_EMPTY) ?: [])
        ->pipe(fn ($parts) => $parts->count() > 1
            ? mb_substr($parts->first(), 0, 1).mb_substr($parts->last(), 0, 1)
            : mb_substr((string) $parts->first(), 0, 2));

    $isActive = in_array(mb_strtoupper((string) $status), ['ACTIVO', 'ACTIVA'], true);
@endphp

<div class="flex min-w-0 items-center gap-4 py-1">
    @if (filled($photoUrl))
        <img
            src="{{ $photoUrl }}"
            alt="Foto de {{ $fullName }}"
            class="size-16 shrink-0 rounded-2xl object-cover shadow-md ring-2 ring-white dark:ring-white/10"
            loading="lazy"
        />
    @else
        <span
            class="flex size-16 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-[#0a74d6] to-[#005ca9] text-xl font-semibold uppercase text-white shadow-md shadow-[#005ca9]/25"
            aria-hidden="true"
        >
            {{ mb_strtoupper($initials !== '' ? $initials : '?') }}
        </span>
    @endif

    <div class="min-w-0 space-y-1.5">
        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center gap-1 rounded-full bg-[#005ca9]/10 px-2.5 py-0.5 text-xs font-semibold tracking-wide text-[#005ca9] ring-1 ring-[#005ca9]/25 dark:bg-sky-400/10 dark:text-sky-300 dark:ring-sky-400/30">
                <x-filament::icon icon="heroicon-m-identification" class="size-3.5" />
                Perfil del médico
            </span>

            @if (filled($status))
                <span @class([
                    'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1',
                    'bg-emerald-500/10 text-emerald-700 ring-emerald-600/20 dark:text-emerald-300 dark:ring-emerald-400/30' => $isActive,
                    'bg-gray-500/10 text-gray-600 ring-gray-500/20 dark:text-gray-300 dark:ring-white/15' => ! $isActive,
                ])>
                    {{ $status }}
                </span>
            @endif

            @if ($consultationsCount !== null)
                <span class="inline-flex items-center gap-1 rounded-full bg-gray-500/10 px-2.5 py-0.5 text-xs font-semibold text-gray-700 ring-1 ring-gray-500/15 dark:text-gray-200 dark:ring-white/15">
                    <x-filament::icon icon="heroicon-m-clipboard-document-list" class="size-3.5" />
                    {{ number_format((int) $consultationsCount, 0, ',', '.') }} {{ (int) $consultationsCount === 1 ? 'consulta' : 'consultas' }}
                </span>
            @endif
        </div>

        <p class="truncate text-xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-2xl" title="{{ $fullName }}">
            Dr(a). {{ filled($fullName) ? $fullName : 'Sin nombre' }}
        </p>

        @if (filled($specialty))
            <p class="text-sm font-medium text-[#005ca9] dark:text-sky-300">{{ $specialty }}</p>
        @endif

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
