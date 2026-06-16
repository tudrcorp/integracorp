@php
    /** @var array<int, array<string, mixed>> $columns */
    /** @var array<string, array<string, mixed>> $rateRows */
    use App\Support\PlanGenerators\PlanGeneratorPreviewBuilder;

    $columns = (array) ($columns ?? []);
    $rateRows = (array) ($rateRows ?? []);
    $columnCount = count($columns);
@endphp

<div class="overflow-x-auto rounded-xl border border-slate-200/80 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950/40">
    @if ($columnCount === 0 && $rateRows === [])
        <p class="p-6 text-center text-sm text-slate-500 dark:text-slate-400">
            Sin tarifas individuales anuales configuradas.
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
                </tr>
            </thead>
            <tbody>
                @forelse ($rateRows as $rateRow)
                    <tr class="{{ $loop->even ? 'bg-white dark:bg-slate-900/40' : 'bg-slate-50/80 dark:bg-white/[0.03]' }}">
                        <td class="border border-slate-200 px-3 py-2.5 align-top dark:border-white/10">
                            {{ $rateRow['age_range_label'] ?? '—' }}
                        </td>
                        <td class="border border-slate-200 px-3 py-2.5 text-center align-top dark:border-white/10">
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
                        <td
                            class="border border-slate-200 px-4 py-6 text-center text-slate-500 dark:border-white/10 dark:text-slate-400"
                            colspan="{{ max(1, $columnCount) + 2 }}">
                            Sin rangos etarios registrados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @endif
</div>
