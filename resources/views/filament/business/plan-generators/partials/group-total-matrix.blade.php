@php
    /** @var array<int, array<string, mixed>> $columns */
    /** @var array<string, array<string, mixed>>  $rateRows */
    use App\Support\PlanGenerators\PlanGeneratorGroupTotalCalculator;
    use App\Support\PlanGenerators\PlanGeneratorMatrixState;

    $columns = PlanGeneratorMatrixState::normalizeColumns((array) ($columns ?? []));
    $rateRows = (array) ($rateRows ?? []);
    $includeMonthlyTotal = (bool) ($includeMonthlyTotal ?? false);
    $columnCount = count($columns);
    $groupTotals = PlanGeneratorGroupTotalCalculator::totalsByColumn($columns, $rateRows);
    $rows = PlanGeneratorGroupTotalCalculator::groupTotalRows($includeMonthlyTotal);
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
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    <p class="mt-2 text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">
        Cálculo automático: tarifa individual anual × población por rango etario. Semestral = anual ÷ 2. Trimestral = anual ÷ 4.@if ($includeMonthlyTotal) Mensual = anual ÷ 12.@endif
    </p>
</div>
