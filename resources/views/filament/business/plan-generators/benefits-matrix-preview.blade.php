@php
    /** @var array<int, array<string, mixed>> $columns */
    /** @var array<string, array<string, mixed>> $rows */
    use App\Support\PlanGenerators\PlanGeneratorPreviewBuilder;

    $columns = (array) ($columns ?? []);
    $rows = (array) ($rows ?? []);
    $columnCount = count($columns);
@endphp

<div class="overflow-x-auto rounded-xl border border-slate-200/80 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950/40">
    @if ($columnCount === 0 && $rows === [])
        <p class="p-6 text-center text-sm text-slate-500 dark:text-slate-400">
            Sin columnas ni beneficios configurados para este plan.
        </p>
    @else
        <table class="min-w-full border-collapse text-xs leading-snug text-slate-800 dark:text-slate-100">
            <thead>
                <tr class="bg-[#1d4ed8] text-white">
                    <th class="border border-[#1e40af] px-3 py-2.5 text-left font-bold uppercase tracking-wide min-w-[220px]">
                        Beneficios del Plan
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
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr class="{{ $loop->even ? 'bg-white dark:bg-slate-900/40' : 'bg-slate-50/80 dark:bg-white/[0.03]' }}">
                        <td class="border border-slate-200 px-3 py-2.5 align-top dark:border-white/10">
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
                        @if ($columnCount === 0)
                            <td class="border border-slate-200 px-3 py-2 text-center text-slate-400 dark:border-white/10">—</td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td
                            class="border border-slate-200 px-4 py-6 text-center text-slate-500 dark:border-white/10 dark:text-slate-400"
                            colspan="{{ max(1, $columnCount) + 1 }}">
                            Sin beneficios registrados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @endif
</div>
