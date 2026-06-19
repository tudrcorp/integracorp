@php
    /** @var array<int, array<string, mixed>> $columns */
    /** @var array<string, array<string, mixed>> $rows */
    /** @var array<string, array<string, mixed>> $rateRows */
    use App\Support\PlanGenerators\PlanGeneratorMatrixState;

    $columns = PlanGeneratorMatrixState::normalizeColumns((array) ($columns ?? []));
    $columnsFingerprint = PlanGeneratorMatrixState::columnsFingerprint($columns);
    $rows = (array) ($rows ?? []);
    $rateRows = (array) ($rateRows ?? []);
    $columnCount = count($columns);
@endphp

<div class="pg-stacked-matrices space-y-4" wire:key="pg-stacked-editor-{{ $columnsFingerprint }}">
    @include('filament.business.plan-generators.partials.matrix-alignment-styles', ['columns' => $columns])

    <div class="flex flex-wrap items-center gap-2">
        <x-filament::button type="button" size="sm" wire:click="addMatrixRow" icon="heroicon-m-plus">
            Agregar beneficio
        </x-filament::button>
        @if ($columnCount === 0)
            <span class="text-xs text-amber-600 dark:text-amber-400">Agregue columnas del plan para alinear beneficios y tarifas.</span>
        @endif
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200/80 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950/40">
        <table class="pg-matrix-table min-w-full border-collapse text-xs leading-snug text-slate-800 dark:text-slate-100">
            @include('filament.business.plan-generators.partials.matrix-column-colgroup', ['columns' => $columns, 'type' => 'benefits'])
            <thead>
                <tr class="bg-[#1d4ed8] text-white">
                    <th colspan="2" class="border border-[#1e40af] px-3 py-2.5 text-left font-bold uppercase tracking-wide">
                        Beneficios del Plan
                    </th>
                    @include('filament.business.plan-generators.partials.matrix-plan-column-headers', ['columns' => $columns, 'type' => 'benefits'])
                    <th class="border border-[#1e40af] px-2 py-2.5 w-10"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $rowKey => $row)
                    <tr wire:key="pg-benefit-row-{{ $rowKey }}" class="{{ $loop->even ? 'bg-white dark:bg-slate-900/40' : 'bg-slate-50/80 dark:bg-white/[0.03]' }}">
                        <td colspan="2" class="border border-slate-200 px-2 py-2 align-top dark:border-white/10">
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
                            <td wire:key="pg-benefit-cell-{{ $rowKey }}-{{ $columnKey }}" class="border border-slate-200 px-2 py-2 align-top dark:border-white/10">
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
                                        class="block w-full min-w-0 rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-center text-xs text-slate-900 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 dark:border-white/15 dark:bg-slate-900 dark:text-white dark:disabled:bg-white/5"
                                    />
                                </div>
                            </td>
                        @endforeach
                        <td class="border border-slate-200 px-1 py-2 text-center align-top dark:border-white/10">
                            <button type="button" wire:click="removeMatrixRow('{{ $rowKey }}')" class="rounded-lg px-2 py-1 text-[10px] font-semibold text-rose-600 transition hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-500/10">Eliminar</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="border border-slate-200 px-4 py-6 text-center text-slate-500 dark:border-white/10 dark:text-slate-400" colspan="{{ max(1, $columnCount) + 3 }}">
                            Sin beneficios. Use «Agregar beneficio».
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <x-filament::button type="button" size="sm" wire:click="addRateRow" icon="heroicon-m-plus">
            Agregar rango etario
        </x-filament::button>
        @if ($columnCount === 0)
            <span class="text-xs text-amber-600 dark:text-amber-400">Agregue columnas del plan para configurar tarifas alineadas.</span>
        @else
            <span class="text-xs text-slate-500 dark:text-slate-400">Las columnas de tarifa coinciden con las del plan: {{ collect($columns)->pluck('header_label')->join(' · ') }}</span>
        @endif
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200/80 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950/40">
        <table class="pg-matrix-table min-w-full border-collapse text-xs leading-snug text-slate-800 dark:text-slate-100">
            @include('filament.business.plan-generators.partials.matrix-column-colgroup', ['columns' => $columns, 'type' => 'rates'])
            <thead>
                <tr class="bg-[#1d4ed8] text-white">
                    <th class="border border-[#1e40af] px-3 py-2.5 text-left font-bold uppercase tracking-wide">Tarifa individual Anual</th>
                    <th class="border border-[#1e40af] px-3 py-2.5 text-center font-bold uppercase">Población</th>
                    @include('filament.business.plan-generators.partials.matrix-plan-column-headers', ['columns' => $columns, 'type' => 'rates'])
                    <th class="border border-[#1e40af] px-2 py-2.5 w-10"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rateRows as $rowKey => $rateRow)
                    <tr wire:key="pg-rate-row-{{ $rowKey }}" class="{{ $loop->even ? 'bg-white dark:bg-slate-900/40' : 'bg-slate-50/80 dark:bg-white/[0.03]' }}">
                        <td class="border border-slate-200 px-2 py-2 align-top dark:border-white/10">
                            <input type="text" wire:model.live.debounce.300ms="data.rate_rows.{{ $rowKey }}.age_range_label" placeholder="Ej: Rango etario 0 - 30" class="block w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-900 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-white/15 dark:bg-slate-900 dark:text-white" />
                        </td>
                        <td class="border border-slate-200 px-2 py-2 align-top dark:border-white/10">
                            <input type="number" min="0" step="1" wire:model.live.debounce.300ms="data.rate_rows.{{ $rowKey }}.population" placeholder="0" class="block w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-center text-xs text-slate-900 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-white/15 dark:bg-slate-900 dark:text-white" />
                        </td>
                        @foreach ($columns as $column)
                            @php $columnKey = (string) ($column['column_key'] ?? ''); @endphp
                            <td wire:key="pg-rate-cell-{{ $rowKey }}-{{ $columnKey }}" class="border border-slate-200 px-2 py-2 align-top dark:border-white/10">
                                <input type="text" wire:model.live.debounce.300ms="data.rate_rows.{{ $rowKey }}.cells.{{ $columnKey }}.rate_amount" placeholder="Tarifa" class="block w-full min-w-0 rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-center text-xs font-semibold text-slate-900 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-white/15 dark:bg-slate-900 dark:text-white" />
                            </td>
                        @endforeach
                        <td class="border border-slate-200 px-1 py-2 text-center align-top dark:border-white/10">
                            <button type="button" wire:click="removeRateRow('{{ $rowKey }}')" class="rounded-lg px-2 py-1 text-[10px] font-semibold text-rose-600 transition hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-500/10">Eliminar</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="border border-slate-200 px-4 py-6 text-center text-slate-500 dark:border-white/10 dark:text-slate-400" colspan="{{ max(1, $columnCount) + 3 }}">
                            Sin rangos etarios. Use «Agregar rango etario».
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('filament.business.plan-generators.partials.group-total-matrix', [
        'columns' => $columns,
        'rateRows' => $rateRows,
    ])
</div>
