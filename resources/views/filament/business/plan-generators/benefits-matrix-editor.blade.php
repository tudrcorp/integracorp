@php
    /** @var array<int, array<string, mixed>> $columns */
    /** @var array<string, array<string, mixed>> $rows */
    $columns = (array) ($columns ?? []);
    $rows = (array) ($rows ?? []);
    $columnCount = count($columns);
@endphp

<div class="space-y-3">
    <div class="flex flex-wrap items-center gap-2">
        <x-filament::button type="button" size="sm" wire:click="addMatrixRow" icon="heroicon-m-plus">
            Agregar beneficio
        </x-filament::button>
        @if ($columnCount === 0)
            <span class="text-xs text-amber-600 dark:text-amber-400">
                Agregue al menos una columna antes de configurar la matriz.
            </span>
        @endif
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200/80 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950/40">
        @if ($columnCount === 0 && $rows === [])
            <p class="p-6 text-center text-sm text-slate-500 dark:text-slate-400">
                Agregue columnas y beneficios para configurar la matriz.
            </p>
        @else
            <table class="min-w-full border-collapse text-xs leading-snug text-slate-800 dark:text-slate-100">
                <thead>
                    <tr class="bg-[#1d4ed8] text-white">
                        <th class="border border-[#1e40af] px-3 py-2.5 text-left font-bold uppercase tracking-wide min-w-[220px]">
                            Beneficios del Plan
                        </th>
                        @foreach ($columns as $column)
                            <th class="border border-[#1e40af] px-2 py-2.5 text-center font-bold uppercase min-w-[140px]">
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
                    @forelse ($rows as $rowKey => $row)
                        <tr class="{{ $loop->even ? 'bg-white dark:bg-slate-900/40' : 'bg-slate-50/80 dark:bg-white/[0.03]' }}">
                            <td class="border border-slate-200 px-2 py-2 align-top dark:border-white/10">
                                <div class="flex gap-2">
                                    <span class="mt-2 shrink-0 font-semibold text-slate-500">{{ $loop->iteration }}.</span>
                                    <input
                                        type="text"
                                        wire:model.live.debounce.300ms="data.rows.{{ $rowKey }}.benefit_label"
                                        placeholder="Definición del beneficio"
                                        class="block w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-900 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-white/15 dark:bg-slate-900 dark:text-white"
                                    />
                                </div>
                            </td>
                            @foreach ($columns as $column)
                                @php
                                    $columnKey = (string) ($column['column_key'] ?? '');
                                    $isSelected = (bool) data_get($row, "cells.{$columnKey}.is_selected", false);
                                @endphp
                                <td class="border border-slate-200 px-2 py-2 align-top dark:border-white/10">
                                    <div class="flex flex-col items-center gap-2">
                                        <label class="flex cursor-pointer items-center gap-1.5">
                                            <input
                                                type="checkbox"
                                                wire:model.live="data.rows.{{ $rowKey }}.cells.{{ $columnKey }}.is_selected"
                                                class="size-4 rounded border-slate-300 text-[#1d4ed8] focus:ring-[#1d4ed8] dark:border-white/20 dark:bg-slate-900"
                                            />
                                            <span class="text-[10px] font-medium text-slate-500 dark:text-slate-400">Incluido</span>
                                        </label>
                                        <input
                                            type="text"
                                            wire:model.live.debounce.300ms="data.rows.{{ $rowKey }}.cells.{{ $columnKey }}.coverage_amount"
                                            placeholder="Cobertura US $"
                                            @disabled(! $isSelected)
                                            class="block w-full min-w-[100px] rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-center text-xs text-slate-900 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 dark:border-white/15 dark:bg-slate-900 dark:text-white dark:disabled:bg-white/5"
                                        />
                                    </div>
                                </td>
                            @endforeach
                            @if ($columnCount === 0)
                                <td class="border border-slate-200 px-3 py-2 text-center text-slate-400 dark:border-white/10">—</td>
                            @endif
                            <td class="border border-slate-200 px-1 py-2 text-center align-top dark:border-white/10">
                                <button
                                    type="button"
                                    wire:click="removeMatrixRow('{{ $rowKey }}')"
                                    class="rounded-lg px-2 py-1 text-[10px] font-semibold text-rose-600 transition hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-500/10"
                                    title="Eliminar beneficio"
                                >
                                    Eliminar
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                class="border border-slate-200 px-4 py-6 text-center text-slate-500 dark:border-white/10 dark:text-slate-400"
                                colspan="{{ max(1, $columnCount) + 2 }}">
                                Sin beneficios. Use «Agregar beneficio».
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        @endif
    </div>

    <p class="text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">
        Marque «Incluido» para activar el beneficio en esa columna. Si aplica monto de cobertura, indíquelo en el campo de la celda.
    </p>
</div>
