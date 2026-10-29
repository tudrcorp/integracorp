<x-filament-widgets::widget>
    <div
        x-data
        x-on:provider-network-detail-opened.window="$nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
    >
        @if ($open)
            <x-filament::section>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Detalle</p>
                        <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ $detailTitle }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ number_format($total, 0, ',', '.') }} proveedor(es){{ $total > $limit ? ' · se muestran los primeros '.$limit : '' }} · clic en un nombre para abrir su ficha.
                        </p>
                    </div>
                    <x-filament::button color="gray" size="sm" icon="heroicon-m-x-mark" wire:click="closeDetail">Cerrar</x-filament::button>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500 dark:border-white/10 dark:text-gray-400">
                                <th class="py-2 pe-3 font-medium">Proveedor</th>
                                <th class="px-2 py-2 font-medium">Tipo</th>
                                <th class="px-2 py-2 font-medium">Ubicación</th>
                                <th class="px-2 py-2 font-medium">Estatus</th>
                                <th class="px-2 py-2 font-medium">Convenio</th>
                                <th class="px-2 py-2 font-medium">Clasificación</th>
                                <th class="px-2 py-2 font-medium">Carta</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr class="border-b border-gray-100 dark:border-white/5" wire:key="pn-{{ $row['type'] }}-{{ $row['id'] }}">
                                    <td class="py-2 pe-3">
                                        @if ($row['url'])
                                            <a href="{{ $row['url'] }}" target="_blank" class="font-medium text-gray-950 hover:underline dark:text-white">{{ $row['name'] }}</a>
                                        @else
                                            <span class="font-medium text-gray-950 dark:text-white">{{ $row['name'] }}</span>
                                        @endif
                                    </td>
                                    <td class="px-2 py-2"><span class="inline-flex items-center gap-1.5 text-gray-700 dark:text-gray-200"><span class="size-2 rounded-full" style="background: {{ $row['type_color'] }}"></span>{{ $row['type_label'] }}</span></td>
                                    <td class="px-2 py-2 text-gray-700 dark:text-gray-200">{{ $row['place'] }}</td>
                                    <td class="px-2 py-2"><span class="inline-flex items-center gap-1.5 text-gray-700 dark:text-gray-200"><span class="size-2 rounded-full" style="background: {{ $row['status_color'] }}"></span>{{ $row['status_label'] }}</span></td>
                                    <td class="px-2 py-2 text-gray-700 dark:text-gray-200">{{ $row['agreement_label'] }}</td>
                                    <td class="px-2 py-2 text-gray-700 dark:text-gray-200">{{ $row['class'] }}</td>
                                    <td class="px-2 py-2 text-gray-700 dark:text-gray-200">{{ $row['letter'] === null ? 'No aplica' : ($row['letter'] ? 'Sí' : 'No') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="py-10 text-center text-gray-500 dark:text-gray-400">Ningún proveedor cumple este criterio.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-widgets::widget>
