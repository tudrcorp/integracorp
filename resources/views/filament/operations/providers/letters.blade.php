@php
    /**
     * @var array{with: array<string, int>, without: array<string, int>, active_without: array<string, int>} $letters
     * @var array<string, array{label: string, plural: string, color: string}> $types
     */
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <div x-data="{ shown: false }" x-intersect.once="shown = true">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">Cartas de aceptación</h3>
            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Jurídicos y naturales. Los aliados corporativos no manejan carta.</p>

            <div class="mt-5 space-y-5">
                @foreach (['juridico', 'natural'] as $type)
                    @php
                        $with = $letters['with'][$type] ?? 0;
                        $without = $letters['without'][$type] ?? 0;
                        $base = $with + $without;
                        $pct = $base > 0 ? $with * 100 / $base : 0;
                        $pending = $letters['active_without'][$type] ?? 0;
                    @endphp
                    <div>
                        <div class="flex items-baseline justify-between gap-2">
                            <p class="text-sm font-semibold" style="color: {{ $types[$type]['color'] }}">{{ $types[$type]['plural'] }}</p>
                            <p class="text-sm tabular-nums"><span class="text-xl font-semibold text-gray-950 dark:text-white">{{ number_format($pct, 1, ',', '.') }} %</span> <span class="text-xs text-gray-500 dark:text-gray-400">con carta</span></p>
                        </div>
                        <div class="mt-2 flex h-3 overflow-hidden rounded-full" style="background: var(--ops-track)">
                            <div class="ops-bar-fill h-full rounded-full" x-bind:style="'width: ' + (shown ? {{ $pct }} : 0) + '%; background: var(--ops-good)'"></div>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-2 text-xs">
                            <button type="button" class="rounded-lg bg-gray-50 px-2 py-1 text-gray-700 ring-1 ring-gray-950/5 transition hover:bg-gray-100 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10" x-on:click="$wire.dispatch('provider-network-detail', { filters: { type: @js($type), letter: 'with' }, title: @js('Con carta de aceptación · '.$types[$type]['plural']) })">
                                Con carta <span class="font-semibold tabular-nums">{{ number_format($with, 0, ',', '.') }}</span>
                            </button>
                            <button type="button" class="rounded-lg bg-gray-50 px-2 py-1 text-gray-700 ring-1 ring-gray-950/5 transition hover:bg-gray-100 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10" x-on:click="$wire.dispatch('provider-network-detail', { filters: { type: @js($type), letter: 'without' }, title: @js('Sin carta de aceptación · '.$types[$type]['plural']) })">
                                Sin carta <span class="font-semibold tabular-nums">{{ number_format($without, 0, ',', '.') }}</span>
                            </button>
                            @if ($pending > 0)
                                <button type="button" class="rounded-lg px-2 py-1 font-medium ring-1 transition hover:opacity-80" style="color: var(--ops-bad-ink); --tw-ring-color: var(--ops-bad-ink)" x-on:click="$wire.dispatch('provider-network-detail', { filters: { type: @js($type), letter: 'active_without' }, title: @js('Convenio activo sin carta · '.$types[$type]['plural']) })">
                                    Convenio activo sin carta <span class="font-semibold tabular-nums">{{ number_format($pending, 0, ',', '.') }}</span>
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
