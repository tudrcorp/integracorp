@php
    /** @var array<int, array<string, mixed>> $columns */
    /** @var array{upgrades?: list<array<string, mixed>>, annual_total?: float, errors?: list<string>} $upgradeQuote */
    use App\Support\PlanGenerators\PlanGeneratorGroupTotalCalculator;
    use App\Support\PlanGenerators\PlanGeneratorUpgrades;

    $columns = (array) ($columns ?? []);
    $rateRows = (array) ($rateRows ?? []);
    $upgradesAnnual = (float) ($upgradeQuote['annual_total'] ?? 0);
    $planTotals = PlanGeneratorGroupTotalCalculator::totalsByColumn($columns, $rateRows);
    $withUpgrades = PlanGeneratorGroupTotalCalculator::totalsByColumn($columns, $rateRows, $upgradesAnnual);
    $frequencyRows = array_values(array_filter(
        PlanGeneratorGroupTotalCalculator::displayRows((bool) ($includeMonthlyTotal ?? false), $groupTotalHiddenRows ?? [], $upgradesAnnual),
        static fn (array $row): bool => $row['key'] !== PlanGeneratorGroupTotalCalculator::ROW_ANNUAL,
    ));
    $errors = (array) ($upgradeQuote['errors'] ?? []);
    $cell = static fn (float $amount): string => PlanGeneratorGroupTotalCalculator::formatGroupTotal($amount > 0 ? $amount : null);
@endphp

<div class="space-y-2">
    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Paso 3 · Recálculo del total grupal</p>

    @if ($errors !== [])
        <div class="space-y-1 rounded-xl border border-danger-200 bg-danger-50 px-3 py-2 text-sm text-danger-800 dark:border-danger-500/30 dark:bg-danger-500/10 dark:text-danger-200">
            <p class="font-semibold">Corrija esto antes de guardar:</p>
            <ul class="list-disc ps-5">
                @foreach ($errors as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($columns === [])
        <p class="text-sm text-slate-500 dark:text-slate-400">Agregue columnas y tarifas al plan para ver el recálculo.</p>
    @else
        <div class="overflow-x-auto rounded-xl border border-slate-200/80 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950/40">
            <table class="min-w-full border-collapse text-xs leading-snug text-slate-800 dark:text-slate-100">
                <thead>
                    <tr class="bg-[#1d4ed8] text-white">
                        <th class="border border-[#1e40af] px-3 py-2 text-left font-bold">Concepto</th>
                        @foreach ($columns as $column)
                            <th class="border border-[#1e40af] px-3 py-2 text-center font-bold uppercase">{{ $column['header_label'] ?? '—' }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="border border-slate-200 px-3 py-2 dark:border-white/10">Total anual del plan</td>
                        @foreach ($columns as $column)
                            <td class="border border-slate-200 px-3 py-2 text-center dark:border-white/10">{{ $cell((float) ($planTotals['annual'][$column['column_key']] ?? 0)) }}</td>
                        @endforeach
                    </tr>
                    @foreach ((array) ($upgradeQuote['upgrades'] ?? []) as $upgrade)
                        <tr class="text-emerald-700 dark:text-emerald-300">
                            <td class="border border-slate-200 px-3 py-2 dark:border-white/10">
                                + {{ $upgrade['upgrade_name'] }}
                                <span class="text-slate-500 dark:text-slate-400">({{ $upgrade['persons'] }} {{ (int) $upgrade['persons'] === 1 ? 'persona' : 'personas' }})</span>
                            </td>
                            @foreach ($columns as $column)
                                @php $hasPlan = (float) ($planTotals['annual'][$column['column_key']] ?? 0) > 0; @endphp
                                <td class="border border-slate-200 px-3 py-2 text-center dark:border-white/10">{{ $hasPlan ? '+'.PlanGeneratorUpgrades::money((float) $upgrade['annual']) : '—' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                    <tr class="bg-slate-50 dark:bg-white/[0.04]">
                        <td class="border border-slate-200 px-3 py-2 font-bold dark:border-white/10">Total anual con upgrades (pago único)</td>
                        @foreach ($columns as $column)
                            <td class="border border-slate-200 px-3 py-2 text-center font-bold text-slate-900 dark:border-white/10 dark:text-white">{{ $cell((float) ($withUpgrades['annual'][$column['column_key']] ?? 0)) }}</td>
                        @endforeach
                    </tr>
                    @foreach ($frequencyRows as $row)
                        <tr>
                            <td class="border border-slate-200 px-3 py-2 dark:border-white/10">{{ $row['label'] }}</td>
                            @foreach ($columns as $column)
                                <td class="border border-slate-200 px-3 py-2 text-center dark:border-white/10">{{ $cell((float) ($withUpgrades[$row['key']][$column['column_key']] ?? 0)) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">
            Los upgrades se pagan una sola vez al año, completos, en el primer pago; los pagos siguientes llevan solo el plan.
            Este desglose es solo para usted. En el PDF el cliente ve un único total grupal (ya con los upgrades) y, debajo, una tabla por upgrade con su cobertura, su tarifa y sus servicios.
        </p>
    @endif
</div>
