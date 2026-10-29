@php
    /**
     * @var array<string, array<string, int>> $matrix estatus => tipo => total
     * @var array<string, array{label: string, plural: string, color: string}> $types
     * @var array<string, array{label: string, color: string}> $statuses
     */
    $rows = [];
    foreach ($matrix as $status => $byType) {
        $rows[] = ['key' => $status, 'total' => array_sum($byType), 'types' => $byType];
    }
    $max = $rows === [] ? 0 : max(array_column($rows, 'total'));
    $grand = array_sum(array_column($rows, 'total'));
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <div x-data="{ shown: false }" x-intersect.once="shown = true">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">Estatus del convenio</h3>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Catálogo de Operaciones · foto a hoy · clic en una barra para ver los proveedores.</p>
                </div>
                <div class="flex flex-wrap gap-3 text-xs text-gray-500 dark:text-gray-400">
                    @foreach ($types as $type => $meta)
                        <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full" style="background: {{ $meta['color'] }}"></span>{{ $meta['plural'] }}</span>
                    @endforeach
                </div>
            </div>

            <ul class="mt-5 space-y-3">
                @forelse ($rows as $row)
                    <li>
                        <div class="flex items-baseline justify-between gap-3 text-sm">
                            <span class="inline-flex items-center gap-2 text-gray-700 dark:text-gray-200">
                                <span class="size-2.5 rounded-full" style="background: {{ $statuses[$row['key']]['color'] ?? 'var(--ops-muted)' }}"></span>
                                {{ $statuses[$row['key']]['label'] ?? $row['key'] }}
                            </span>
                            <span class="tabular-nums text-gray-500 dark:text-gray-400">
                                <span class="font-semibold text-gray-950 dark:text-white">{{ number_format($row['total'], 0, ',', '.') }}</span>
                                · {{ $grand > 0 ? number_format($row['total'] * 100 / $grand, 1, ',', '.') : 0 }} %
                            </span>
                        </div>
                        <div class="mt-1.5 flex h-3 overflow-hidden rounded-full" style="background: var(--ops-track)">
                            @foreach ($types as $type => $meta)
                                @php $value = $row['types'][$type] ?? 0; @endphp
                                @if ($value > 0)
                                    <button
                                        type="button"
                                        class="ops-bar-fill h-full transition hover:opacity-80"
                                        x-bind:style="'width: ' + (shown && {{ $max }} > 0 ? {{ $value * 100 / max($max, 1) }} : 0) + '%; background: {{ $meta['color'] }}'"
                                        x-tooltip="{ content: @js($meta['plural'].': '.number_format($value, 0, ',', '.')), theme: $store.theme }"
                                        x-on:click="$wire.dispatch('provider-network-detail', { filters: { type: @js($type), status: @js($row['key']) }, title: @js(($statuses[$row['key']]['label'] ?? $row['key']).' · '.$meta['plural']) })"
                                        aria-label="{{ $meta['plural'] }}: {{ $value }}"
                                    ></button>
                                @endif
                            @endforeach
                        </div>
                    </li>
                @empty
                    <li class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">Todavía no hay proveedores registrados.</li>
                @endforelse
            </ul>

            @if (! empty($other))
                <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                    «Otro» agrupa estatus escritos fuera del catálogo:
                    @foreach (array_slice($other, 0, 6, true) as $raw => $count)
                        <span class="font-medium text-gray-700 dark:text-gray-200">{{ $raw }}</span> ({{ $count }}){{ $loop->last ? '.' : ',' }}
                    @endforeach
                </p>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
