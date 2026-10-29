@php
    /**
     * @var array<string, array{title: string, items: array<string, int>, rest: int, distinct: int, total: int}> $groups
     * @var array<string, array{label: string, plural: string, color: string}> $types
     */
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <div x-data="{ tab: 'juridico', shown: false }" x-intersect.once="shown = true">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">Tipos de proveedor</h3>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Los {{ \App\Filament\Operations\Widgets\ProviderNetwork\ProviderNetworkClasses::TOP }} más frecuentes · clic para ver el listado.</p>
                </div>
                <div class="inline-flex gap-1 rounded-xl bg-gray-100 p-1 dark:bg-white/5" role="tablist">
                    @foreach ($types as $type => $meta)
                        <button type="button" role="tab" x-on:click="tab = @js($type)" x-bind:aria-selected="tab === @js($type)"
                            x-bind:class="tab === @js($type) ? 'bg-white text-gray-950 shadow-sm dark:bg-white/10 dark:text-white' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                            class="rounded-lg px-3 py-1.5 text-xs font-medium transition">{{ $meta['plural'] }}</button>
                    @endforeach
                </div>
            </div>

            @foreach ($groups as $type => $group)
                @php $max = $group['items'] === [] ? 0 : max($group['items']); @endphp
                <div x-show="tab === @js($type)" x-cloak class="mt-4">
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $group['title'] }} · {{ number_format($group['distinct'], 0, ',', '.') }} distintos en {{ number_format($group['total'], 0, ',', '.') }} proveedores</p>
                    <ol class="mt-3 space-y-1.5">
                        @forelse ($group['items'] as $label => $value)
                            <li>
                                <button type="button" class="w-full rounded-lg px-2 py-1 text-start transition hover:bg-gray-50 dark:hover:bg-white/5"
                                    x-on:click="$wire.dispatch('provider-network-detail', { filters: { type: @js($type), class: @js($label) }, title: @js($label.' · '.$types[$type]['plural']) })">
                                    <div class="flex items-baseline gap-3 text-sm">
                                        <span class="w-5 shrink-0 text-xs tabular-nums text-gray-400">{{ $loop->iteration }}</span>
                                        <span class="min-w-0 flex-1 truncate text-gray-700 dark:text-gray-200">{{ $label }}</span>
                                        <span class="shrink-0 font-semibold tabular-nums text-gray-950 dark:text-white">{{ number_format($value, 0, ',', '.') }}</span>
                                    </div>
                                    <div class="ms-8 mt-1 h-1.5 overflow-hidden rounded-full" style="background: var(--ops-track)">
                                        <div class="ops-bar-fill h-full rounded-full" x-bind:style="'width: ' + (shown ? {{ $value * 100 / max($max, 1) }} : 0) + '%; background: {{ $types[$type]['color'] }}'"></div>
                                    </div>
                                </button>
                            </li>
                        @empty
                            <li class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">No hay proveedores de este tipo.</li>
                        @endforelse
                    </ol>
                    @if ($group['rest'] > 0)
                        <p class="mt-2 ps-10 text-xs text-gray-500 dark:text-gray-400">Y {{ number_format($group['rest'], 0, ',', '.') }} más en otras categorías.</p>
                    @endif
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
