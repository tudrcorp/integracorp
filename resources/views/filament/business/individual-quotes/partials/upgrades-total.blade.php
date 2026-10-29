@php
    /** @var array{upgrades: list<array<string, mixed>>, annual_total: float, population: int, errors: list<string>} $upgradeQuote */
    $iqMoney = static fn (float $amount): string => 'US$ '.number_format($amount, 2, ',', '.');
    $iqAnnualTotal = (float) ($upgradeQuote['annual_total'] ?? 0);
@endphp

<div class="space-y-3 rounded-xl border border-primary-200 bg-primary-50/40 p-4 text-sm dark:border-primary-500/30 dark:bg-primary-500/10">
    @if (($upgradeQuote['errors'] ?? []) !== [])
        <div class="rounded-lg border border-danger-200 bg-danger-50 p-3 text-danger-700 dark:border-danger-500/20 dark:bg-danger-500/10 dark:text-danger-300" role="alert">
            <p class="font-semibold">Corrija esto antes de guardar:</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-5">
                @foreach ($upgradeQuote['errors'] as $iqError)
                    <li>{{ $iqError }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <p class="font-semibold text-gray-950 dark:text-white">Total de upgrades de la cotización</p>

    <table class="w-full border-collapse text-left">
        <thead>
            <tr class="border-b border-gray-200 dark:border-white/10">
                <th class="px-2 py-1">Upgrade</th>
                <th class="px-2 py-1 text-right">Personas</th>
                <th class="px-2 py-1 text-right">Anual</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($upgradeQuote['upgrades'] ?? [] as $iqLine)
                <tr class="border-b border-gray-100 dark:border-white/5">
                    <td class="px-2 py-1">{{ $iqLine['upgrade_name'] }} <span class="text-gray-500 dark:text-gray-400">· {{ \App\Support\PlanGenerators\PlanGeneratorUpgrades::coverageLabel($iqLine) }}</span></td>
                    <td class="px-2 py-1 text-right">{{ (int) $iqLine['persons'] }} de {{ (int) $iqLine['population'] }}</td>
                    <td class="px-2 py-1 text-right">{{ $iqMoney((float) $iqLine['annual']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-white p-3 shadow-sm dark:bg-white/5">
        <div>
            <p class="text-xs text-gray-500 dark:text-gray-400">Pago único, en el primer pago</p>
            <p class="text-base font-semibold text-gray-950 dark:text-white">{{ $iqMoney($iqAnnualTotal) }}</p>
        </div>
        <x-filament::badge color="primary">Anual · no se reparte en cuotas</x-filament::badge>
    </div>

    <p class="text-xs text-gray-600 dark:text-gray-400">
        Los upgrades se pagan una sola vez al año, completos, en el primer pago, con cualquier frecuencia.
        Por ejemplo, en trimestral: primer pago = cuota del plan + {{ $iqMoney($iqAnnualTotal) }}; pagos 2 a 4 = solo la cuota del plan.
    </p>
</div>
