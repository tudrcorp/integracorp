@php
    $steps = array_filter([
        ['1', 'Qué buscó el sistema', $explanation['searched'] ?? null],
        ['2', 'Cómo comparó', $explanation['rule'] ?? null],
    ], fn (array $step): bool => filled($step[2]));
@endphp

<div class="space-y-5 text-sm">
    <div class="rounded-xl bg-gray-50 px-4 py-3 text-base font-semibold text-gray-950 ring-1 ring-gray-950/5 dark:bg-white/5 dark:text-white dark:ring-white/10">
        {{ $explanation['headline'] ?? '' }}
    </div>

    @foreach ($steps as [$number, $title, $text])
        <section class="space-y-1">
            <h3 class="flex items-center gap-2 font-semibold text-gray-950 dark:text-white">
                <span class="flex size-6 items-center justify-center rounded-full bg-primary-50 text-xs text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">{{ $number }}</span>
                {{ $title }}
            </h3>
            <p class="ps-8 text-gray-600 dark:text-gray-300">{{ $text }}</p>
        </section>
    @endforeach

    @if (! empty($explanation['fields']))
        <section class="space-y-2">
            <h3 class="flex items-center gap-2 font-semibold text-gray-950 dark:text-white">
                <span class="flex size-6 items-center justify-center rounded-full bg-primary-50 text-xs text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">{{ count($steps) + 1 }}</span>
                Resultado dato por dato
            </h3>
            @if (filled($explanation['compared_with'] ?? null))
                <p class="ps-8 text-xs text-gray-500 dark:text-gray-400">{{ $explanation['compared_with'] }}</p>
            @endif
            <div class="ps-8">
                @include('filament.business.resources.affiliation-corporates.partials.population-item-fields', ['fields' => $explanation['fields'], 'caption' => null, 'leftLabel' => $explanation['labels'][0] ?? 'Archivo', 'rightLabel' => $explanation['labels'][1] ?? 'Base'])
            </div>
        </section>
    @endif

    <section class="space-y-1">
        <h3 class="flex items-center gap-2 font-semibold text-gray-950 dark:text-white">
            <span class="flex size-6 items-center justify-center rounded-full bg-primary-50 text-xs text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">{{ count($steps) + (empty($explanation['fields']) ? 1 : 2) }}</span>
            Conclusión
        </h3>
        <p class="ps-8 text-gray-600 dark:text-gray-300">{{ $explanation['conclusion'] ?? '' }}</p>
    </section>

    @if (filled($explanation['next'] ?? null))
        <div class="rounded-xl bg-primary-50 px-4 py-3 text-primary-900 ring-1 ring-primary-600/20 dark:bg-primary-500/10 dark:text-primary-100 dark:ring-primary-400/30">
            <p class="font-semibold">Qué hacer</p>
            <p class="mt-1">{{ $explanation['next'] }}</p>
        </div>
    @endif

    @if (! empty($explanation['history']))
        <section class="space-y-1">
            <h3 class="font-semibold text-gray-950 dark:text-white">Bitácora de esta fila</h3>
            <ul class="list-disc space-y-1 ps-5 text-xs text-gray-600 dark:text-gray-300">
                @foreach ($explanation['history'] as $entry)
                    <li>{{ $entry }}</li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
