@php
    /**
     * @var array<string, array<string, int>> $matrix tipo de convenio => tipo de proveedor => total
     * @var array<string, array{label: string, plural: string, color: string}> $types
     * @var array<string, array{label: string, color: string, preferential: bool}> $agreements
     */
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <div x-data="{ shown: false }" x-intersect.once="shown = true">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">General y preferencial</h3>
            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Tipo de convenio de cada proveedor · clic para ver el listado.</p>

            <div class="mt-5 space-y-5">
                @foreach ($types as $type => $meta)
                    @php
                        $total = $totals[$type] ?? 0;
                        $general = $matrix['GENERAL'][$type] ?? 0;
                        $preferential = 0;
                        foreach ($agreements as $key => $agreement) {
                            $preferential += $agreement['preferential'] ? ($matrix[$key][$type] ?? 0) : 0;
                        }
                    @endphp
                    @continue($total === 0)
                    <div>
                        <div class="flex items-baseline justify-between gap-2">
                            <p class="text-sm font-semibold" style="color: {{ $meta['color'] }}">{{ $meta['plural'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                <button type="button" class="font-semibold text-gray-950 underline decoration-dotted underline-offset-2 dark:text-white" x-on:click="$wire.dispatch('provider-network-detail', { filters: { type: @js($type), agreement: 'GENERAL' }, title: @js('Convenio general · '.$meta['plural']) })">General {{ number_format($general, 0, ',', '.') }}</button>
                                ·
                                <button type="button" class="font-semibold text-gray-950 underline decoration-dotted underline-offset-2 dark:text-white" x-on:click="$wire.dispatch('provider-network-detail', { filters: { type: @js($type), preferential: true }, title: @js('Convenio preferencial · '.$meta['plural']) })">Preferencial {{ number_format($preferential, 0, ',', '.') }}</button>
                            </p>
                        </div>
                        <div class="mt-2 flex h-4 overflow-hidden rounded-full" style="background: var(--ops-track)">
                            @foreach ($agreements as $key => $agreement)
                                @php $value = $matrix[$key][$type] ?? 0; @endphp
                                @if ($value > 0)
                                    <button
                                        type="button"
                                        class="ops-bar-fill h-full transition hover:opacity-80"
                                        x-bind:style="'width: ' + (shown ? {{ $value * 100 / max($total, 1) }} : 0) + '%; background: {{ $agreement['color'] }}'"
                                        x-tooltip="{ content: @js($agreement['label'].': '.number_format($value, 0, ',', '.').' ('.number_format($value * 100 / max($total, 1), 1, ',', '.').' %)'), theme: $store.theme }"
                                        x-on:click="$wire.dispatch('provider-network-detail', { filters: { type: @js($type), agreement: @js($key) }, title: @js($agreement['label'].' · '.$meta['plural']) })"
                                        aria-label="{{ $agreement['label'] }}: {{ $value }}"
                                    ></button>
                                @endif
                            @endforeach
                        </div>
                        <div class="mt-1.5 flex flex-wrap gap-x-3 gap-y-0.5 text-[11px] text-gray-500 dark:text-gray-400">
                            @foreach ($agreements as $key => $agreement)
                                @php $value = $matrix[$key][$type] ?? 0; @endphp
                                @if ($value > 0)
                                    <span class="inline-flex items-center gap-1"><span class="size-2 rounded-full" style="background: {{ $agreement['color'] }}"></span>{{ $agreement['label'] }} {{ number_format($value, 0, ',', '.') }}</span>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
