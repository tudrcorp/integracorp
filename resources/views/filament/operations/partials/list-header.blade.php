@php
    /**
     * Encabezado de listados de Operaciones.
     *
     * @var string $icon
     * @var string $eyebrow
     * @var string $title
     * @var int|null $total
     * @var string|null $totalHint
     * @var string|null $description
     * @var list<array{label: string, value: int, icon: string, tone: string, hint: string}> $stats
     */
    $icon ??= 'heroicon-o-users';
    $eyebrow ??= null;
    $title ??= '';
    $total ??= null;
    $totalHint ??= null;
    $description ??= null;
    $stats ??= [];

    $tones = [
        'success' => 'bg-emerald-500/10 text-emerald-700 ring-emerald-600/20 dark:text-emerald-300 dark:ring-emerald-400/30',
        'warning' => 'bg-amber-500/10 text-amber-700 ring-amber-600/25 dark:text-amber-300 dark:ring-amber-400/30',
        'danger' => 'bg-rose-500/10 text-rose-700 ring-rose-600/25 dark:text-rose-300 dark:ring-rose-400/30',
        'info' => 'bg-sky-500/10 text-sky-700 ring-sky-600/25 dark:text-sky-300 dark:ring-sky-400/30',
        'primary' => 'bg-[#2d89ca]/10 text-[#1f6fae] ring-[#2d89ca]/25 dark:bg-sky-400/10 dark:text-sky-300 dark:ring-sky-400/30',
        'gray' => 'bg-gray-500/10 text-gray-600 ring-gray-500/20 dark:text-gray-300 dark:ring-white/15',
    ];
@endphp

<div class="flex min-w-0 items-center gap-4 py-1">
    <span
        class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-[#4aa3e3] to-[#1f6fae] text-white shadow-md shadow-[#2d89ca]/25"
        aria-hidden="true"
    >
        <x-filament::icon :icon="$icon" class="size-7" />
    </span>

    <div class="min-w-0 space-y-1.5">
        @if (filled($eyebrow))
            <span class="text-xs font-semibold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">{{ $eyebrow }}</span>
        @endif

        <p class="text-xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-2xl">
            {{ $title }}
            @if ($total !== null)
                <span class="ms-1 align-middle text-base font-semibold text-gray-400 dark:text-gray-500" @if (filled($totalHint)) title="{{ $totalHint }}" @endif>{{ number_format((int) $total, 0, ',', '.') }}</span>
            @endif
        </p>

        @if (filled($description))
            <p class="max-w-3xl text-sm font-normal text-gray-600 dark:text-gray-300">{{ $description }}</p>
        @endif

        @if ($stats !== [] && (int) $total > 0)
            <ul class="flex flex-wrap items-center gap-2 pt-1" aria-label="Resumen">
                @foreach ($stats as $stat)
                    <li
                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $tones[$stat['value'] > 0 ? $stat['tone'] : 'gray'] ?? $tones['gray'] }}"
                        title="{{ $stat['hint'] }}"
                    >
                        <x-filament::icon :icon="$stat['icon']" class="size-3.5" />
                        <span>{{ $stat['label'] }}</span>
                        <span class="tabular-nums">{{ number_format((int) $stat['value'], 0, ',', '.') }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
