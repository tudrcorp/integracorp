@php
    $chart = is_array($getState()) ? $getState() : [];
    $labels = $chart['labels'] ?? [];
    $ideal = $chart['ideal'] ?? [];
    $remaining = $chart['remaining'] ?? [];
    $committed = (int) ($chart['committed_points'] ?? 0);
    $done = (int) ($chart['completed_points'] ?? 0);
    $left = (int) ($chart['remaining_points'] ?? 0);
    $maxPoints = max(1, $committed, ...(array_filter($remaining, fn ($value) => $value !== null) ?: [0]));
    $pointCount = max(1, count($labels));
    $width = 640;
    $height = 220;
    $padding = 24;

    $toX = function (int $index) use ($pointCount, $width, $padding): float {
        if ($pointCount === 1) {
            return $padding;
        }

        return $padding + (($width - (2 * $padding)) * ($index / ($pointCount - 1)));
    };

    $toY = function (float|int|null $value) use ($maxPoints, $height, $padding): ?float {
        if ($value === null) {
            return null;
        }

        return $padding + (($height - (2 * $padding)) * (1 - (min($maxPoints, max(0, (float) $value)) / $maxPoints)));
    };

    $idealPoints = [];
    $remainingPoints = [];

    foreach ($labels as $index => $label) {
        $idealY = $toY($ideal[$index] ?? 0);
        $idealPoints[] = $toX($index).','.$idealY;

        $remainingY = $toY($remaining[$index] ?? null);
        if ($remainingY !== null) {
            $remainingPoints[] = $toX($index).','.$remainingY;
        }
    }
@endphp

<div class="space-y-4">
    <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-slate-200/80 bg-white/80 px-4 py-3 dark:border-white/10 dark:bg-white/5">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Comprometidos</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums text-slate-900 dark:text-white">{{ $committed }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200/80 bg-white/80 px-4 py-3 dark:border-white/10 dark:bg-white/5">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Completados</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums text-emerald-600 dark:text-emerald-400">{{ $done }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200/80 bg-white/80 px-4 py-3 dark:border-white/10 dark:bg-white/5">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Restantes</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums text-amber-600 dark:text-amber-400">{{ $left }}</p>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200/80 bg-white/80 p-4 dark:border-white/10 dark:bg-white/5">
        <svg viewBox="0 0 {{ $width }} {{ $height }}" class="h-56 w-full" role="img" aria-label="Burndown del sprint">
            <polyline
                fill="none"
                stroke="rgb(148 163 184)"
                stroke-width="2"
                stroke-dasharray="6 4"
                points="{{ implode(' ', $idealPoints) }}"
            />
            @if ($remainingPoints !== [])
                <polyline
                    fill="none"
                    stroke="rgb(14 165 233)"
                    stroke-width="3"
                    points="{{ implode(' ', $remainingPoints) }}"
                />
            @endif
        </svg>
        <div class="mt-2 flex flex-wrap gap-4 text-xs text-slate-500 dark:text-slate-400">
            <span class="inline-flex items-center gap-2">
                <span class="h-0.5 w-5 border-t-2 border-dashed border-slate-400"></span>
                Ideal
            </span>
            <span class="inline-flex items-center gap-2">
                <span class="h-0.5 w-5 bg-sky-500"></span>
                Restante
            </span>
            @if ($labels !== [])
                <span>{{ reset($labels) }} → {{ end($labels) }}</span>
            @endif
        </div>
    </div>
</div>
