@php
    /** @var array{lines: list<array<string, mixed>>, annual_total: float} $summary */
    $iqsMoney = static fn (float $amount): string => 'US$ '.number_format($amount, 2, ',', '.');
    $iqsStatus = static fn (string $status): string => match ($status) {
        \App\Support\PlanGenerators\PlanGeneratorUpgrades::STATUS_INCLUDED => 'Incluido',
        \App\Support\PlanGenerators\PlanGeneratorUpgrades::STATUS_MANUAL => 'Incluido a mano',
        \App\Support\PlanGenerators\PlanGeneratorUpgrades::STATUS_EXCLUDED => 'Excluido',
        \App\Support\PlanGenerators\PlanGeneratorUpgrades::STATUS_PARTIAL => 'Cubierto en parte (no se cobra)',
        default => 'No aplica',
    };
@endphp

<div class="space-y-5 text-sm">
    @foreach ($summary['lines'] as $iqsLine)
        <div>
            <p class="font-semibold text-gray-950 dark:text-white">{{ $iqsLine['name'] }} <span class="font-normal text-gray-500 dark:text-gray-400">· {{ $iqsLine['coverage'] }}</span></p>
            <table class="mt-2 w-full border-collapse text-left">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-white/10">
                        <th class="px-2 py-1">Rango de edad</th>
                        <th class="px-2 py-1 text-right">Personas</th>
                        <th class="px-2 py-1 text-right">Con upgrade</th>
                        <th class="px-2 py-1 text-right">Precio anual</th>
                        <th class="px-2 py-1 text-right">Subtotal</th>
                        <th class="px-2 py-1">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($iqsLine['rows'] as $iqsRow)
                        <tr class="border-b border-gray-100 dark:border-white/5">
                            <td class="px-2 py-1">{{ $iqsRow['label'] }}</td>
                            <td class="px-2 py-1 text-right">{{ (int) $iqsRow['population'] }}</td>
                            <td class="px-2 py-1 text-right">{{ (int) $iqsRow['charged_persons'] }}</td>
                            <td class="px-2 py-1 text-right">{{ $iqsRow['price'] !== null ? $iqsMoney((float) $iqsRow['price']) : '—' }}</td>
                            <td class="px-2 py-1 text-right">{{ $iqsMoney((float) $iqsRow['subtotal']) }}</td>
                            <td class="px-2 py-1">{{ $iqsStatus((string) $iqsRow['status']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="mt-1 text-right font-semibold">Total anual: {{ $iqsMoney((float) $iqsLine['annual']) }}</p>
        </div>
    @endforeach

    <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-gray-50 p-3 dark:bg-white/5">
        <div>
            <p class="text-xs text-gray-500 dark:text-gray-400">Total de upgrades · pago único, en el primer pago</p>
            <p class="text-base font-semibold text-gray-950 dark:text-white">{{ $iqsMoney((float) $summary['annual_total']) }}</p>
        </div>
        <p class="max-w-md text-xs text-gray-600 dark:text-gray-400">{{ \App\Support\Upgrades\UpgradeInstallments::RULE }} Las demás cuotas llevan solo el plan.</p>
    </div>
</div>
