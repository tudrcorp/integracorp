@php
    /** @var array<int, array<string, mixed>> $columns */
    /** @var array<string, array<string, mixed>>  $rateRows */
    use App\Support\PlanGenerators\PlanGeneratorGroupTotalCalculator;
    use App\Support\PlanGenerators\PlanGeneratorMatrixState;

    $columns = PlanGeneratorMatrixState::normalizeColumns((array) ($columns ?? []));
    $rateRows = (array) ($rateRows ?? []);
    $includeMonthlyTotal = (bool) ($includeMonthlyTotal ?? false);
    $groupTotalHiddenRows = $groupTotalHiddenRows ?? [];
    // Solo el editor quita y restaura filas; la vista del registro es de lectura.
    $editable = (bool) ($editable ?? false) && filled($matrixStatePath ?? null);
    $columnCount = count($columns);
    $groupTotals = PlanGeneratorGroupTotalCalculator::totalsByColumn($columns, $rateRows);
    $rows = PlanGeneratorGroupTotalCalculator::groupTotalRows($includeMonthlyTotal, $groupTotalHiddenRows);
    $removedRows = $editable ? PlanGeneratorGroupTotalCalculator::removedRows($includeMonthlyTotal, $groupTotalHiddenRows) : [];
    $visibleKeys = array_column($rows, 'key');
    $divisorNotes = array_filter([
        PlanGeneratorGroupTotalCalculator::ROW_SEMESTRAL => 'Semestral = anual ÷ 2.',
        PlanGeneratorGroupTotalCalculator::ROW_TRIMESTRAL => 'Trimestral = anual ÷ 4.',
        PlanGeneratorGroupTotalCalculator::ROW_MENSUAL => 'Mensual = anual ÷ 12.',
    ], static fn (string $note, string $key): bool => in_array($key, $visibleKeys, true), ARRAY_FILTER_USE_BOTH);
@endphp

<div>
    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
        Total grupal
    </p>
    <div class="overflow-x-auto rounded-xl border border-slate-200/80 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950/40">
        @if ($columnCount === 0)
            <p class="p-6 text-center text-sm text-slate-500 dark:text-slate-400">
                Agregue columnas y tarifas individuales para calcular totales grupales.
            </p>
        @else
            <table class="pg-matrix-table min-w-full border-collapse text-xs leading-snug text-slate-800 dark:text-slate-100">
                @include('filament.business.plan-generators.partials.matrix-column-colgroup', ['columns' => $columns, 'type' => 'group-total'])
                <thead>
                    <tr class="bg-[#1d4ed8] text-white">
                        <th class="border border-[#1e40af] px-2 py-2.5 text-left font-bold uppercase tracking-wide">
                            Total Grupal
                        </th>
                        @foreach ($columns as $column)
                            <th class="border border-[#1e40af] px-2 py-2.5 text-center font-bold uppercase">
                                {{ $column['header_label'] ?? '—' }}
                            </th>
                        @endforeach
                        @if ($editable)
                            <th class="w-10 border border-[#1e40af] px-2 py-2.5"></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="{{ $loop->even ? 'bg-white dark:bg-slate-900/40' : 'bg-slate-50/80 dark:bg-white/[0.03]' }}">
                            <td class="border border-slate-200 px-2 py-2.5 align-top font-medium dark:border-white/10">
                                {{ $row['label'] }}
                            </td>
                            @foreach ($columns as $column)
                                @php
                                    $columnKey = (string) ($column['column_key'] ?? '');
                                    $amount = (float) ($groupTotals[$row['key']][$columnKey] ?? 0);
                                    $label = PlanGeneratorGroupTotalCalculator::formatGroupTotal($amount > 0 ? $amount : null);
                                @endphp
                                <td @class([
                                    'border border-slate-200 px-2 py-2.5 text-center align-top dark:border-white/10',
                                    'font-bold text-slate-900 dark:text-white' => $row['bold'],
                                    'font-semibold text-slate-700 dark:text-slate-200' => ! $row['bold'],
                                ])>
                                    {{ $label }}
                                </td>
                            @endforeach
                            @if ($editable)
                                <td class="border border-slate-200 px-1 py-2 text-center align-top dark:border-white/10">
                                    @if (count($rows) > 1)
                                        <button
                                            type="button"
                                            wire:click="removeGroupTotalRow('{{ $row['key'] }}', '{{ $matrixStatePath }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="removeGroupTotalRow"
                                            class="inline-flex items-center justify-center rounded-lg p-1.5 text-rose-600 transition hover:bg-rose-50 disabled:opacity-50 dark:text-rose-300 dark:hover:bg-rose-500/10"
                                            title="Quitar «{{ $row['label'] }}» de la cotización"
                                            aria-label="Quitar {{ $row['label'] }}"
                                        ><x-filament::icon icon="heroicon-m-trash" class="size-4" /></button>
                                    @else
                                        <span class="inline-flex p-1.5 text-slate-300 dark:text-slate-600" title="Debe quedar al menos una forma de pago"><x-filament::icon icon="heroicon-m-lock-closed" class="size-4" /></span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    @if ($removedRows !== [])
        <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
            <span class="text-slate-500 dark:text-slate-400">Quitadas de la cotización:</span>
            @foreach ($removedRows as $removedRow)
                <x-filament::button
                    type="button"
                    size="xs"
                    color="gray"
                    icon="heroicon-m-plus"
                    wire:click="restoreGroupTotalRow('{{ $removedRow['key'] }}', '{{ $matrixStatePath }}')"
                    wire:loading.attr="disabled"
                    wire:target="restoreGroupTotalRow"
                    wire:key="pg-group-total-restore-{{ $removedRow['key'] }}"
                >
                    {{ $removedRow['label'] }}
                </x-filament::button>
            @endforeach
        </div>
    @endif
    <p class="mt-2 text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">
        Cálculo automático: tarifa individual anual × población por rango etario.@foreach ($divisorNotes as $note) {{ $note }}@endforeach
        @if ($editable)
            Las filas que quite no salen en el PDF ni se ofrecen como forma de pago al registrar la empresa.
        @endif
    </p>
</div>
