@php
    /** @var array<int, array<string, mixed>> $columns */
    use App\Support\PlanGenerators\PlanGeneratorPreviewBuilder;
    use App\Support\PlanGenerators\PlanGeneratorMatrixColumnLayout;

    $columns = (array) ($columns ?? []);
    $rows = (array) ($rows ?? []);
    $rateRows = (array) ($rateRows ?? []);
    $populationUnitLabel = (string) ($populationUnitLabel ?? 'Población');
    $includeMonthlyTotal = (bool) ($includeMonthlyTotal ?? false);
    $columnCount = count($columns);
@endphp

<div class="pg-stacked-matrices space-y-4">
    @include('filament.business.plan-generators.partials.matrix-alignment-styles', ['columns' => $columns])

    <div>
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            Matriz de beneficios y coberturas
        </p>
        <div class="overflow-x-auto rounded-xl border border-slate-200/80 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950/40">
            @if ($columnCount === 0 && $rows === [])
                <p class="p-6 text-center text-sm text-slate-500 dark:text-slate-400">
                    Sin columnas ni beneficios configurados para este plan.
                </p>
            @else
                <table class="pg-matrix-table min-w-full border-collapse text-xs leading-snug text-slate-800 dark:text-slate-100">
                    @include('filament.business.plan-generators.partials.matrix-column-colgroup', ['columns' => $columns, 'type' => 'benefits'])
                    <thead>
                        <tr class="bg-[#1d4ed8] text-white">
                            <th colspan="2" class="border border-[#1e40af] px-2 py-2.5 text-left font-bold uppercase tracking-wide">
                                Beneficios del Plan
                            </th>
                            @foreach ($columns as $column)
                                <th class="border border-[#1e40af] px-2 py-2.5 text-center font-bold uppercase">
                                    {{ $column['header_label'] ?? '—' }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr class="{{ $loop->even ? 'bg-white dark:bg-slate-900/40' : 'bg-slate-50/80 dark:bg-white/[0.03]' }}">
                                <td colspan="2" class="border border-slate-200 px-2 py-2.5 align-top dark:border-white/10">
                                    <span class="font-semibold text-slate-500">{{ $loop->iteration }}.</span>
                                    <span class="ml-1">{{ $row['benefit_label'] ?? '—' }}</span>
                                </td>
                                @foreach ($columns as $column)
                                    @php
                                        $columnKey = (string) ($column['column_key'] ?? '');
                                        $cell = (array) data_get($row, "cells.{$columnKey}", []);
                                        $isSelected = (bool) ($cell['is_selected'] ?? false);
                                        $coverage = $cell['coverage_amount'] ?? null;
                                        $coverageLabel = is_numeric($coverage)
                                            ? PlanGeneratorPreviewBuilder::formatCoverageAmount((float) $coverage)
                                            : '';
                                    @endphp
                                    <td class="border border-slate-200 px-2 py-2.5 text-center align-top dark:border-white/10">
                                        @include('filament.business.plan-generators.partials.benefit-cell-status-preview', [
                                            'isSelected' => $isSelected,
                                            'coverageLabel' => $coverageLabel,
                                        ])
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td class="border border-slate-200 px-4 py-6 text-center text-slate-500 dark:border-white/10 dark:text-slate-400" colspan="{{ max(1, $columnCount) + 2 }}">
                                    Sin beneficios registrados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <div>
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            Tarifa individual anual
        </p>
        <div class="overflow-x-auto rounded-xl border border-slate-200/80 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950/40">
            @if ($columnCount === 0 && $rateRows === [])
                <p class="p-6 text-center text-sm text-slate-500 dark:text-slate-400">
                    Sin tarifas individuales anuales configuradas.
                </p>
            @else
                <table class="pg-matrix-table min-w-full border-collapse text-xs leading-snug text-slate-800 dark:text-slate-100">
                    @include('filament.business.plan-generators.partials.matrix-column-colgroup', ['columns' => $columns, 'type' => 'rates'])
                    <thead>
                        <tr class="bg-[#1d4ed8] text-white">
                            <th class="border border-[#1e40af] px-2 py-2.5 text-left font-bold uppercase tracking-wide">
                                Tarifa individual Anual
                            </th>
                            <th class="border border-[#1e40af] px-2 py-2.5 text-center font-bold uppercase">
                                {{ $populationUnitLabel }}
                            </th>
                            @foreach ($columns as $column)
                                <th class="border border-[#1e40af] px-2 py-2.5 text-center font-bold uppercase">
                                    {{ $column['header_label'] ?? '—' }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rateRows as $rateRow)
                            <tr class="{{ $loop->even ? 'bg-white dark:bg-slate-900/40' : 'bg-slate-50/80 dark:bg-white/[0.03]' }}">
                                <td class="border border-slate-200 px-2 py-2.5 align-top dark:border-white/10">
                                    {{ $rateRow['age_range_label'] ?? '—' }}
                                </td>
                                <td class="border border-slate-200 px-2 py-2.5 text-center align-top dark:border-white/10">
                                    {{ filled($rateRow['population'] ?? null) ? number_format((int) $rateRow['population']) : '—' }}
                                </td>
                                @foreach ($columns as $column)
                                    @php
                                        $columnKey = (string) ($column['column_key'] ?? '');
                                        $rate = data_get($rateRow, "cells.{$columnKey}.rate_amount");
                                        $rateLabel = is_numeric($rate)
                                            ? PlanGeneratorPreviewBuilder::formatRateAmount((float) $rate)
                                            : '';
                                    @endphp
                                    <td class="border border-slate-200 px-2 py-2.5 text-center align-top font-semibold dark:border-white/10">
                                        {{ $rateLabel !== '' ? $rateLabel : '—' }}
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td class="border border-slate-200 px-4 py-6 text-center text-slate-500 dark:border-white/10 dark:text-slate-400" colspan="{{ max(1, $columnCount) + 2 }}">
                                    Sin rangos etarios registrados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    @include('filament.business.plan-generators.partials.group-total-matrix', [
        'columns' => $columns,
        'rateRows' => $rateRows,
        'includeMonthlyTotal' => (bool) ($includeMonthlyTotal ?? false),
    ])
</div>
