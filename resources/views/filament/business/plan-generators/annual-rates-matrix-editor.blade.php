@php
    /** @var array<int, array<string, mixed>> $columns */
    /** @var array<string, array<string, mixed>> $rateRows */
    $columns = (array) ($columns ?? []);
    $rateRows = (array) ($rateRows ?? []);
    $columnCount = count($columns);
@endphp

<div class="space-y-3">
    <div class="flex flex-wrap items-center gap-2">
        <x-filament::button type="button" size="sm" wire:click="addRateRow" icon="heroicon-m-plus">
            Agregar rango etario
        </x-filament::button>
        @if ($columnCount === 0)
            <span class="text-xs text-amber-600 dark:text-amber-400">
                Agregue al menos una columna en la sección superior antes de configurar tarifas.
            </span>
        @endif
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200/80 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950/40">
        @if ($columnCount === 0 && $rateRows === [])
            <p class="p-6 text-center text-sm text-slate-500 dark:text-slate-400">
                Agregue columnas y rangos etarios para configurar la tarifa individual anual.
            </p>
        @else
            <table class="min-w-full border-collapse text-xs leading-snug text-slate-800 dark:text-slate-100">
                <thead>
                    <tr class="bg-[#1d4ed8] text-white">
                        <th class="border border-[#1e40af] px-3 py-2.5 text-left font-bold uppercase tracking-wide min-w-[200px]">
                            Tarifa individual Anual
                        </th>
                        <th class="border border-[#1e40af] px-3 py-2.5 text-center font-bold uppercase min-w-[100px]">
                            Población
                        </th>
                        @foreach ($columns as $column)
                            <th class="border border-[#1e40af] px-2 py-2.5 text-center font-bold uppercase min-w-[120px]">
                                {{ $column['header_label'] ?? '—' }}
                            </th>
                        @endforeach
                        @if ($columnCount === 0)
                            <th class="border border-[#1e40af] px-3 py-2.5 text-center font-bold uppercase text-blue-200">
                                Sin columnas
                            </th>
                        @endif
                        <th class="border border-[#1e40af] px-2 py-2.5 w-10"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rateRows as $rowKey => $rateRow)
                        <tr class="{{ $loop->even ? 'bg-white dark:bg-slate-900/40' : 'bg-slate-50/80 dark:bg-white/[0.03]' }}">
                            <td class="border border-slate-200 px-2 py-2 align-top dark:border-white/10">
                                <input
                                    type="text"
                                    wire:model.live.debounce.300ms="data.rate_rows.{{ $rowKey }}.age_range_label"
                                    placeholder="Ej: Rango etario 0 - 30"
                                    class="block w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-900 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-white/15 dark:bg-slate-900 dark:text-white"
                                />
                            </td>
                            <td class="border border-slate-200 px-2 py-2 align-top dark:border-white/10">
                                <input
                                    type="number"
                                    min="0"
                                    step="1"
                                    wire:model.live.debounce.300ms="data.rate_rows.{{ $rowKey }}.population"
                                    placeholder="0"
                                    class="block w-full min-w-[80px] rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-center text-xs text-slate-900 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-white/15 dark:bg-slate-900 dark:text-white"
                                />
                            </td>
                            @foreach ($columns as $column)
                                @php
                                    $columnKey = (string) ($column['column_key'] ?? '');
                                @endphp
                                <td class="border border-slate-200 px-2 py-2 align-top dark:border-white/10">
                                    <input
                                        type="text"
                                        wire:model.live.debounce.300ms="data.rate_rows.{{ $rowKey }}.cells.{{ $columnKey }}.rate_amount"
                                        placeholder="Tarifa"
                                        class="block w-full min-w-[90px] rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-center text-xs font-semibold text-slate-900 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-white/15 dark:bg-slate-900 dark:text-white"
                                    />
                                </td>
                            @endforeach
                            @if ($columnCount === 0)
                                <td class="border border-slate-200 px-3 py-2 text-center text-slate-400 dark:border-white/10">—</td>
                            @endif
                            <td class="border border-slate-200 px-1 py-2 text-center align-top dark:border-white/10">
                                <button
                                    type="button"
                                    wire:click="removeRateRow('{{ $rowKey }}')"
                                    class="rounded-lg px-2 py-1 text-[10px] font-semibold text-rose-600 transition hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-500/10"
                                    title="Eliminar rango etario"
                                >
                                    Eliminar
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                class="border border-slate-200 px-4 py-6 text-center text-slate-500 dark:border-white/10 dark:text-slate-400"
                                colspan="{{ max(1, $columnCount) + 3 }}">
                                Sin rangos etarios. Use «Agregar rango etario».
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        @endif
    </div>

    <p class="text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">
        Indique el rango de edad y la población por fila. En las celdas de intersección ingrese manualmente la tarifa anual correspondiente a cada columna del plan.
    </p>
</div>
