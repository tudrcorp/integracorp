@php
    /**
     * @var array<string, array<string, int>> $quality
     * @var array<string, array{label: string, plural: string, color: string}> $types
     */
    $items = [
        'no_location' => ['label' => 'Sin estado registrado', 'hint' => 'No aparecen en el mapa.', 'filters' => ['missing_location' => true]],
        'no_status' => ['label' => 'Sin estatus de convenio', 'hint' => 'No se sabe en qué etapa está el convenio.', 'filters' => ['status' => 'SIN ESTATUS']],
        'no_agreement' => ['label' => 'Sin tipo de convenio', 'hint' => 'No se sabe si es general o preferencial.', 'filters' => ['agreement' => 'SIN TIPO']],
        'other_status' => ['label' => 'Estatus fuera del catálogo', 'hint' => 'Escrito a mano; conviene elegirlo de la lista.', 'filters' => ['status' => 'OTRO']],
    ];
@endphp

<x-filament-widgets::widget class="ops-hide-in-presentation">
    <x-filament::section>
        <h3 class="text-base font-semibold text-gray-950 dark:text-white">Datos por completar</h3>
        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Lo que falta para que el dashboard muestre la red completa. No se ve en modo presentación.</p>

        <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($items as $key => $item)
                @php $total = array_sum($quality[$key] ?? []); @endphp
                <div class="rounded-xl p-3 ring-1 ring-gray-950/5 dark:ring-white/10 {{ $total > 0 ? 'bg-amber-50 dark:bg-amber-500/10' : 'bg-gray-50 dark:bg-white/5' }}">
                    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $item['label'] }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $item['hint'] }}</p>
                    <div class="mt-2 flex flex-wrap gap-1.5 text-xs">
                        @foreach ($types as $type => $meta)
                            @php $value = $quality[$key][$type] ?? 0; @endphp
                            @if ($value > 0)
                                <button type="button" class="rounded-lg bg-white px-2 py-1 text-gray-700 ring-1 ring-gray-950/5 transition hover:bg-gray-100 dark:bg-gray-900 dark:text-gray-200 dark:ring-white/10"
                                    x-on:click="$wire.dispatch('provider-network-detail', { filters: @js([...$item['filters'], 'type' => $type]), title: @js($item['label'].' · '.$meta['plural']) })">
                                    {{ $meta['plural'] }} <span class="font-semibold tabular-nums">{{ number_format($value, 0, ',', '.') }}</span>
                                </button>
                            @endif
                        @endforeach
                        @if ($total === 0)
                            <span class="text-gray-500 dark:text-gray-400">Todo completo.</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
