@php
    /** @var array<int, array<string, mixed>> $columns */
    /** @var array<string, array<string, mixed>> $rows */
    /** @var array<string, array<string, mixed>> $rateRows */
    use App\Support\PlanGenerators\PlanGeneratorMatrixState;

    // El mismo editor sirve al formulario de una página (estado en `data`) y a
    // la modal que deriva una cotización desde la tabla (estado en
    // `mountedActions.N.data`). La ruta la da `$getStatePath()`, el getter del
    // componente del schema que renderiza esta vista.
    //
    // La variable no puede llamarse igual que el método `statePath()` del
    // componente: Filament inyecta en la vista un closure por cada método
    // público, así que ese nombre llega ocupado y castearlo revienta el render.
    $matrixStatePath = (isset($getStatePath) && $getStatePath instanceof \Closure)
        ? (string) $getStatePath()
        : 'data';

    if ($matrixStatePath === '') {
        $matrixStatePath = 'data';
    }
    $manageColumns = (bool) ($manageColumns ?? false);
    $editorScope = substr(md5($matrixStatePath), 0, 8);

    $columns = $manageColumns
        ? PlanGeneratorMatrixState::keyedColumns((array) ($columns ?? []))
        : PlanGeneratorMatrixState::normalizeColumns((array) ($columns ?? []));
    $columnsFingerprint = PlanGeneratorMatrixState::columnsFingerprint($columns);
    $rateAdjustmentSummary = \App\Support\PlanGenerators\PlanGeneratorRateAdjustment::summary($columns);
    $rows = (array) ($rows ?? []);
    $rateRows = (array) ($rateRows ?? []);
    $populationUnitLabel = (string) ($populationUnitLabel ?? 'Población');
    $includeMonthlyTotal = (bool) ($includeMonthlyTotal ?? false);
    $columnCount = count($columns);
    $benefitOptions = collect($benefitOptions ?? [])
        ->map(fn ($benefit): string => (string) $benefit)
        ->filter(fn (string $benefit): bool => $benefit !== '')
        ->values();
@endphp

<div class="pg-stacked-matrices space-y-4" wire:key="pg-stacked-editor-{{ $editorScope }}-{{ $columnsFingerprint }}-{{ \Illuminate\Support\Str::slug($populationUnitLabel) }}-{{ (int) $includeMonthlyTotal }}">
    @include('filament.business.plan-generators.partials.matrix-alignment-styles', ['columns' => $columns])

    <div class="flex flex-wrap items-center gap-2">
        <x-filament::button type="button" size="sm" wire:click="addMatrixRow('{{ $matrixStatePath }}')" icon="heroicon-m-plus">
            Agregar beneficio
        </x-filament::button>
        @if ($manageColumns)
            <x-filament::button type="button" size="sm" color="gray" wire:click="addMatrixColumn('{{ $matrixStatePath }}')" icon="heroicon-m-view-columns">
                Agregar columna
            </x-filament::button>
            <span class="text-xs text-slate-500 dark:text-slate-400">
                Escriba el nombre de cada columna en su encabezado azul; la «✕» la quita de esta cotización.
            </span>
        @endif
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
                    @include('filament.business.plan-generators.partials.matrix-plan-column-headers', ['columns' => $columns, 'type' => 'benefits', 'manageColumns' => $manageColumns, 'matrixStatePath' => $matrixStatePath])
                    <th class="border border-[#1e40af] px-2 py-2.5 w-10"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $rowKey => $row)
                    @php
                        $currentBenefitLabel = (string) ($row['benefit_label'] ?? '');
                        $benefitsUsedByOtherRows = collect($rows)
                            ->reject(fn ($otherRow, $otherKey): bool => $otherKey === $rowKey)
                            ->pluck('benefit_label')
                            ->map(fn ($label): string => (string) $label)
                            ->filter(fn (string $label): bool => $label !== '')
                            ->all();
                        $availableBenefits = $benefitOptions
                            ->reject(fn (string $benefit): bool => in_array($benefit, $benefitsUsedByOtherRows, true))
                            ->values();
                        if ($currentBenefitLabel !== '' && ! $availableBenefits->contains($currentBenefitLabel)) {
                            $availableBenefits = $availableBenefits->push($currentBenefitLabel)->values();
                        }
                        $benefitSelectKey = $editorScope.'-pg-benefit-select-'.$rowKey.'-'.md5($availableBenefits->implode('||'));
                    @endphp
                    <tr wire:key="{{ $editorScope }}-pg-benefit-row-{{ $rowKey }}" class="{{ $loop->even ? 'bg-white dark:bg-slate-900/40' : 'bg-slate-50/80 dark:bg-white/[0.03]' }}">
                        <td colspan="2" class="border border-slate-200 px-2 py-2 align-middle dark:border-white/10">
                            <div class="flex items-center gap-2" x-data="{ creatingBenefit: false, newBenefitLabel: '' }">
                                <span class="shrink-0 font-semibold text-slate-500">{{ $loop->iteration }}.</span>
                                <div class="w-full space-y-1.5">
                                    <div x-show="! creatingBenefit" class="flex items-center gap-1.5">
                                        <select
                                            wire:key="{{ $benefitSelectKey }}"
                                            wire:model.live="{{ $matrixStatePath }}.rows.{{ $rowKey }}.benefit_label"
                                            class="block w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-900 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-white/15 dark:bg-slate-900 dark:text-white"
                                        >
                                            <option value="">Seleccione un beneficio</option>
                                            @foreach ($availableBenefits as $benefitOption)
                                                <option value="{{ $benefitOption }}">{{ $benefitOption }}</option>
                                            @endforeach
                                        </select>
                                        <button
                                            type="button"
                                            x-on:click="creatingBenefit = true; $nextTick(() => $refs.newBenefitInput?.focus())"
                                            class="inline-flex shrink-0 items-center justify-center rounded-lg border border-slate-300 p-1.5 text-blue-600 transition hover:bg-blue-50 dark:border-white/15 dark:text-blue-300 dark:hover:bg-blue-500/10"
                                            title="Crear nuevo beneficio"
                                            aria-label="Crear nuevo beneficio"
                                        >
                                            <x-filament::icon icon="heroicon-m-plus" class="size-4" />
                                        </button>
                                    </div>
                                    <div x-show="creatingBenefit" x-cloak class="flex items-center gap-1.5">
                                        <input
                                            type="text"
                                            x-ref="newBenefitInput"
                                            x-model="newBenefitLabel"
                                            x-on:keydown.enter.prevent="if (newBenefitLabel.trim() !== '') { $wire.createPlanGeneratorBenefit('{{ $rowKey }}', newBenefitLabel, '{{ $matrixStatePath }}'); } creatingBenefit = false; newBenefitLabel = ''"
                                            placeholder="Nuevo beneficio"
                                            class="block w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-900 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-white/15 dark:bg-slate-900 dark:text-white"
                                        />
                                        <button
                                            type="button"
                                            x-on:click="if (newBenefitLabel.trim() !== '') { $wire.createPlanGeneratorBenefit('{{ $rowKey }}', newBenefitLabel, '{{ $matrixStatePath }}'); } creatingBenefit = false; newBenefitLabel = ''"
                                            class="inline-flex shrink-0 items-center justify-center rounded-lg border border-emerald-300 p-1.5 text-emerald-600 transition hover:bg-emerald-50 dark:border-emerald-500/30 dark:text-emerald-300 dark:hover:bg-emerald-500/10"
                                            title="Guardar beneficio"
                                            aria-label="Guardar beneficio"
                                        >
                                            <x-filament::icon icon="heroicon-m-check" class="size-4" />
                                        </button>
                                        <button
                                            type="button"
                                            x-on:click="creatingBenefit = false; newBenefitLabel = ''"
                                            class="inline-flex shrink-0 items-center justify-center rounded-lg border border-slate-300 p-1.5 text-slate-500 transition hover:bg-slate-100 dark:border-white/15 dark:text-slate-300 dark:hover:bg-white/10"
                                            title="Cancelar"
                                            aria-label="Cancelar"
                                        >
                                            <x-filament::icon icon="heroicon-m-x-mark" class="size-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </td>
                        @foreach ($columns as $column)
                            @php
                                $columnKey = (string) ($column['column_key'] ?? '');
                                $isSelected = (bool) data_get($row, "cells.{$columnKey}.is_selected", false);
                            @endphp
                            <td wire:key="{{ $editorScope }}-pg-benefit-cell-{{ $rowKey }}-{{ $columnKey }}" class="border border-slate-200 px-2 py-2 align-top dark:border-white/10">
                                <div class="flex flex-col items-center gap-2">
                                    <label class="flex cursor-pointer items-center gap-1.5">
                                        <input
                                            type="checkbox"
                                            wire:model.live="{{ $matrixStatePath }}.rows.{{ $rowKey }}.cells.{{ $columnKey }}.is_selected"
                                            class="size-4 rounded border-slate-300 text-[#1d4ed8] focus:ring-[#1d4ed8] dark:border-white/20 dark:bg-slate-900"
                                        />
                                        <span class="text-[10px] font-medium text-slate-500 dark:text-slate-400">Incluido</span>
                                    </label>
                                    <input
                                        type="text"
                                        wire:model.live.debounce.300ms="{{ $matrixStatePath }}.rows.{{ $rowKey }}.cells.{{ $columnKey }}.coverage_amount"
                                        placeholder="Cobertura US $"
                                        @disabled(! $isSelected)
                                        class="block w-full min-w-0 rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-center text-xs text-slate-900 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 dark:border-white/15 dark:bg-slate-900 dark:text-white dark:disabled:bg-white/5"
                                    />
                                </div>
                            </td>
                        @endforeach
                        <td class="border border-slate-200 px-1 py-2 text-center align-top dark:border-white/10">
                            <button type="button" wire:click="removeMatrixRow('{{ $rowKey }}', '{{ $matrixStatePath }}')" class="inline-flex items-center justify-center rounded-lg p-1.5 text-rose-600 transition hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-500/10" title="Eliminar beneficio" aria-label="Eliminar beneficio"><x-filament::icon icon="heroicon-m-trash" class="size-4" /></button>
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
        <x-filament::button type="button" size="sm" wire:click="addRateRow('{{ $matrixStatePath }}')" icon="heroicon-m-plus">
            Agregar rango etario
        </x-filament::button>

        {{-- Ajuste global de tarifas. Se registra en el componente del schema
             (`registerActions()`), así que si no está registrado no se pinta. --}}
        @if (isset($getAction) && ($adjustRatesAction = $getAction('adjustRateAmounts')))
            {{ $adjustRatesAction }}
        @endif

        @if ($columnCount === 0)
            <span class="text-xs text-amber-600 dark:text-amber-400">Agregue columnas del plan para configurar tarifas alineadas.</span>
        @else
            <span class="text-xs text-slate-500 dark:text-slate-400">Las columnas de tarifa coinciden con las del plan: {{ collect($columns)->pluck('header_label')->join(' · ') }}</span>
        @endif
    </div>

    @if ($rateAdjustmentSummary !== null)
        {{-- Dato interno: nunca se imprime en la cotización. Las plantillas del
             PDF solo leen `header_label` y `rate_amount`. --}}
        <div class="flex flex-wrap items-center gap-2 rounded-xl border border-amber-300/70 bg-amber-50/80 px-3 py-2 text-xs text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
            <x-filament::icon icon="heroicon-m-eye-slash" class="size-4 shrink-0" />
            <span class="font-semibold">Ajuste interno vigente:</span>
            <span>{{ $rateAdjustmentSummary }}</span>
            <span class="text-amber-700/80 dark:text-amber-200/70">— las tarifas de abajo ya lo incluyen; el porcentaje no sale en la cotización.</span>
        </div>
    @endif

    <div class="overflow-x-auto rounded-xl border border-slate-200/80 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950/40">
        <table class="pg-matrix-table min-w-full border-collapse text-xs leading-snug text-slate-800 dark:text-slate-100">
            @include('filament.business.plan-generators.partials.matrix-column-colgroup', ['columns' => $columns, 'type' => 'rates'])
            <thead>
                <tr class="bg-[#1d4ed8] text-white">
                    <th class="border border-[#1e40af] px-3 py-2.5 text-left font-bold uppercase tracking-wide">Tarifa individual Anual</th>
                    <th class="border border-[#1e40af] px-3 py-2.5 text-center font-bold uppercase">{{ $populationUnitLabel }}</th>
                    @include('filament.business.plan-generators.partials.matrix-plan-column-headers', ['columns' => $columns, 'type' => 'rates', 'manageColumns' => false, 'matrixStatePath' => $matrixStatePath])
                    <th class="border border-[#1e40af] px-2 py-2.5 w-10"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rateRows as $rowKey => $rateRow)
                    <tr wire:key="{{ $editorScope }}-pg-rate-row-{{ $rowKey }}" class="{{ $loop->even ? 'bg-white dark:bg-slate-900/40' : 'bg-slate-50/80 dark:bg-white/[0.03]' }}">
                        <td class="border border-slate-200 px-2 py-2 align-top dark:border-white/10">
                            <input type="text" wire:model.live.debounce.300ms="{{ $matrixStatePath }}.rate_rows.{{ $rowKey }}.age_range_label" placeholder="Ej: Rango etario 0 - 30" class="block w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-900 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-white/15 dark:bg-slate-900 dark:text-white" />
                        </td>
                        <td class="border border-slate-200 px-2 py-2 align-top dark:border-white/10">
                            <input type="number" min="0" step="1" wire:model.live.debounce.300ms="{{ $matrixStatePath }}.rate_rows.{{ $rowKey }}.population" placeholder="0" class="block w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-center text-xs text-slate-900 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-white/15 dark:bg-slate-900 dark:text-white" />
                        </td>
                        @foreach ($columns as $column)
                            @php $columnKey = (string) ($column['column_key'] ?? ''); @endphp
                            <td wire:key="{{ $editorScope }}-pg-rate-cell-{{ $rowKey }}-{{ $columnKey }}" class="border border-slate-200 px-2 py-2 align-top dark:border-white/10">
                                <input type="text" wire:model.live.debounce.300ms="{{ $matrixStatePath }}.rate_rows.{{ $rowKey }}.cells.{{ $columnKey }}.rate_amount" placeholder="Tarifa" class="block w-full min-w-0 rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-center text-xs font-semibold text-slate-900 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-white/15 dark:bg-slate-900 dark:text-white" />
                            </td>
                        @endforeach
                        <td class="border border-slate-200 px-1 py-2 text-center align-top dark:border-white/10">
                            <button type="button" wire:click="removeRateRow('{{ $rowKey }}', '{{ $matrixStatePath }}')" class="inline-flex items-center justify-center rounded-lg p-1.5 text-rose-600 transition hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-500/10" title="Eliminar rango etario" aria-label="Eliminar rango etario"><x-filament::icon icon="heroicon-m-trash" class="size-4" /></button>
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
        'includeMonthlyTotal' => (bool) ($includeMonthlyTotal ?? false),
    ])
</div>
