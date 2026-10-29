@php
    /** @var array<string, mixed> $preview  ver App\Support\Upgrades\UpgradeAssignmentPreview */
    use App\Support\Upgrades\UpgradeAssignmentPreview;

    $preview = (array) ($preview ?? []);
    $rows = (array) ($preview['rows'] ?? []);
    $owner = $preview['owner'] ?? null;
    $collections = (array) ($preview['collections'] ?? []);
    $blockers = (array) ($preview['blockers'] ?? []);
    $receiving = count(UpgradeAssignmentPreview::receivingIds($preview));
    $money = static fn (float $amount): string => 'US$ '.number_format($amount, 2, ',', '.');
    $frequencyLabel = static fn (?string $frequency): string => mb_strtolower((string) ($frequency ?: 'anual'), 'UTF-8');
@endphp

<div class="space-y-3">
    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Paso 2 · Recálculo</p>

    @if (($preview['error'] ?? null) !== null)
        <div class="rounded-xl border border-danger-200 bg-danger-50 px-3 py-2 text-sm text-danger-800 dark:border-danger-500/30 dark:bg-danger-500/10 dark:text-danger-200">
            {{ $preview['error'] }}
        </div>
    @endif

    @if ($blockers !== [])
        <div class="rounded-xl border border-danger-200 bg-danger-50 px-3 py-2 text-sm text-danger-800 dark:border-danger-500/30 dark:bg-danger-500/10 dark:text-danger-200">
            <p class="font-semibold">No se puede agregar hasta resolver:</p>
            <ul class="mt-1 list-disc ps-5">
                @foreach ($blockers as $blocker)
                    <li>{{ $blocker }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (($preview['upgrade'] ?? null) !== null)
        <div @class([
            'rounded-xl border px-3 py-2 text-sm',
            'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200' => $receiving > 0,
            'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200' => $receiving === 0,
        ])>
            <span class="font-semibold">{{ $preview['upgrade']['name'] }}</span> · {{ $preview['upgrade']['coverage'] }} · cubre {{ $preview['upgrade']['covers'] }}.
            @if ($receiving > 0)
                Lo recibe{{ $receiving === 1 ? '' : 'n' }} <span class="font-bold">{{ $receiving }} de {{ count($rows) }}</span> · suma <span class="font-bold">{{ $money((float) array_sum(array_column(array_filter($rows, fn (array $row): bool => $row['receives']), 'price'))) }} al año</span> en tarifas.
            @else
                Ningún afiliado seleccionado puede recibirlo: revise el motivo de cada uno.
            @endif
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-200/80 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950/40">
            <table class="min-w-full border-collapse text-xs leading-snug text-slate-800 dark:text-slate-100">
                <thead>
                    <tr class="bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-300">
                        <th class="px-3 py-2 text-left font-semibold">Afiliado</th>
                        <th class="px-3 py-2 text-center font-semibold">Edad</th>
                        <th class="px-3 py-2 text-center font-semibold">Upgrade</th>
                        <th class="px-3 py-2 text-center font-semibold">Tarifa anual</th>
                        <th class="px-3 py-2 text-center font-semibold">Monto por período</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-white/10">
                    @foreach ($rows as $row)
                        <tr wire:key="upgrade-preview-row-{{ $row['id'] }}" @class([
                            'bg-white dark:bg-transparent' => $row['receives'],
                            'bg-slate-50 text-slate-500 dark:bg-white/[0.02] dark:text-slate-400' => ! $row['receives'],
                        ])>
                            <td class="px-3 py-2 font-medium">{{ $row['name'] }}</td>
                            <td class="px-3 py-2 text-center">{{ $row['age'] !== null ? $row['age'].' años' : '—' }}</td>
                            <td class="px-3 py-2 text-center">
                                @if ($row['receives'])
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200">
                                        <x-filament::icon icon="heroicon-m-check-circle" class="size-3.5" />
                                        +{{ $money((float) $row['price']) }}
                                    </span>
                                    @if (filled($row['range'] ?? null))
                                        <span class="block text-[10px] text-slate-500 dark:text-slate-400">precio de {{ $row['range'] }}</span>
                                    @endif
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-200 px-2 py-0.5 text-[11px] font-semibold text-slate-600 dark:bg-white/10 dark:text-slate-300">
                                        <x-filament::icon icon="heroicon-m-no-symbol" class="size-3.5" />
                                        No lo recibe
                                    </span>
                                    <span class="block text-[10px]">{{ $row['reason'] }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-center">
                                @if ($row['receives'])
                                    {{ $money((float) $row['fee_before']) }} → <span class="font-bold">{{ $money((float) $row['fee_after']) }}</span>
                                @else
                                    {{ $money((float) $row['fee_before']) }}
                                @endif
                            </td>
                            <td class="px-3 py-2 text-center">
                                @if ($row['receives'])
                                    {{ $money((float) $row['period_before']) }} → <span class="font-bold">{{ $money((float) $row['period_after']) }}</span>
                                @else
                                    {{ $money((float) $row['period_before']) }}
                                @endif
                                <span class="block text-[10px] text-slate-500 dark:text-slate-400">{{ $frequencyLabel($row['frequency']) }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if (is_array($owner))
            <div class="grid gap-3 md:grid-cols-2">
                <div class="rounded-xl border border-slate-200/80 bg-white px-3 py-2 text-xs shadow-sm dark:border-white/10 dark:bg-slate-950/40">
                    <p class="font-semibold text-slate-700 dark:text-slate-200">{{ $owner['label'] }}</p>
                    <dl class="mt-1 grid grid-cols-2 gap-x-3 gap-y-1">
                        <dt class="text-slate-500 dark:text-slate-400">Total anual</dt>
                        <dd class="text-right">{{ $money((float) $owner['annual_before']) }} → <span class="font-bold">{{ $money((float) $owner['annual_after']) }}</span></dd>
                        <dt class="text-slate-500 dark:text-slate-400">Por período ({{ $frequencyLabel($owner['frequency']) }})</dt>
                        <dd class="text-right">{{ $money((float) $owner['period_before']) }} → <span class="font-bold">{{ $money((float) $owner['period_after']) }}</span></dd>
                    </dl>
                    @if (filled($owner['note'] ?? null))
                        <p class="mt-1 text-[11px] text-amber-700 dark:text-amber-300">{{ $owner['note'] }}</p>
                    @endif
                </div>

                <div class="rounded-xl border border-slate-200/80 bg-white px-3 py-2 text-xs shadow-sm dark:border-white/10 dark:bg-slate-950/40">
                    <p class="font-semibold text-slate-700 dark:text-slate-200">Cuotas por pagar que se ajustan</p>
                    @if ($collections === [])
                        <p class="mt-1 text-slate-500 dark:text-slate-400">No hay cuotas por pagar: el nuevo monto se verá en los próximos avisos de cobro.</p>
                    @else
                        <table class="mt-1 w-full">
                            @foreach ($collections as $collection)
                                <tr>
                                    <td class="py-0.5">{{ $collection['invoice'] }}@if (filled($collection['date'])) <span class="text-slate-500 dark:text-slate-400">· {{ $collection['date'] }}</span>@endif</td>
                                    <td class="py-0.5 text-right">{{ $money((float) $collection['before']) }} → <span class="font-bold">{{ $money((float) $collection['after']) }}</span></td>
                                </tr>
                            @endforeach
                        </table>
                    @endif
                </div>
            </div>
        @endif
    @endif
</div>
