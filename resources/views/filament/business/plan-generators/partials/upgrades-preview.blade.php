@php
    /** @var array{upgrades?: list<array<string, mixed>>, errors?: list<string>} $upgradeQuote */
    use App\Support\PlanGenerators\PlanGeneratorUpgrades;

    $upgrades = (array) ($upgradeQuote['upgrades'] ?? []);
    $errors = (array) ($upgradeQuote['errors'] ?? []);
@endphp

@if ($upgrades !== [])
    <div class="space-y-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            Upgrades incluidos
        </p>

        @foreach ($upgrades as $upgrade)
            @php
                $pricedRows = PlanGeneratorUpgrades::chargedRows($upgrade);
                $services = array_values((array) ($upgrade['services'] ?? []));
            @endphp

            <div class="space-y-2" wire:key="pg-upgrade-preview-{{ $upgrade['line_key'] }}">
                <p class="text-sm font-bold text-[#1d4ed8] dark:text-sky-300">{{ $upgrade['upgrade_name'] }}:</p>

                <div class="overflow-x-auto rounded-xl border border-slate-200/80 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950/40">
                    <table class="min-w-full border-collapse text-xs leading-snug text-slate-800 dark:text-slate-100">
                        <thead>
                            <tr class="bg-[#1d4ed8] text-white">
                                <th class="border border-[#1e40af] px-3 py-2 text-left font-bold">{{ $upgrade['upgrade_name'] }}</th>
                                <th class="w-1/3 border border-[#1e40af] px-3 py-2 text-center font-bold">{{ PlanGeneratorUpgrades::coverageLabel($upgrade) }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if ($upgrade['uniform_price'] !== null)
                                <tr>
                                    <td class="border border-slate-200 px-3 py-2 dark:border-white/10">Tarifa anual individual por persona</td>
                                    <td class="border border-slate-200 px-3 py-2 text-center font-semibold dark:border-white/10">{{ PlanGeneratorUpgrades::money($upgrade['uniform_price']) }}</td>
                                </tr>
                            @else
                                @foreach ($pricedRows as $row)
                                    <tr>
                                        <td class="border border-slate-200 px-3 py-2 dark:border-white/10">Tarifa anual individual · {{ $row['status'] === PlanGeneratorUpgrades::STATUS_MANUAL && filled($row['covered'] ?? null) ? $row['covered'] : $row['label'] }}</td>
                                        <td class="border border-slate-200 px-3 py-2 text-center font-semibold dark:border-white/10">{{ PlanGeneratorUpgrades::money($row['price']) }}</td>
                                    </tr>
                                @endforeach
                            @endif
                            <tr class="bg-slate-50/80 dark:bg-white/[0.03]">
                                <td class="border border-slate-200 px-3 py-2 dark:border-white/10">Tarifa anual grupal ({{ $upgrade['persons'] }} {{ $upgrade['persons'] === 1 ? 'persona' : 'personas' }})</td>
                                <td class="border border-slate-200 px-3 py-2 text-center font-bold text-slate-900 dark:border-white/10 dark:text-white">{{ PlanGeneratorUpgrades::money($upgrade['annual']) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                @php
                    $manualRows = array_values(array_filter(
                        (array) ($upgrade['rows'] ?? []),
                        static fn (array $row): bool => $row['status'] === PlanGeneratorUpgrades::STATUS_MANUAL,
                    ));
                @endphp
                @if ($manualRows !== [])
                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
                        <p class="font-semibold">Rangos declarados a mano (solo visible aquí, no sale en el PDF). Al aprobar, el upgrade se asigna por la edad real de cada persona:</p>
                        <ul class="mt-1 list-disc space-y-1 ps-5">
                            @foreach ($manualRows as $row)
                                <li>
                                    <span class="font-semibold">{{ $row['label'] }}</span>: {{ $row['charged_persons'] }} de {{ $row['population'] }} personas declaradas con {{ $row['covered'] }}, a {{ PlanGeneratorUpgrades::money($row['price']) }}. Motivo: {{ $row['manual']['note'] ?? '' }}
                                    <span class="text-amber-700/80 dark:text-amber-300/70">
                                        — {{ $row['manual']['by'] ?? 'sin usuario' }}@if (filled($row['manual']['at'] ?? null)), {{ \Illuminate\Support\Carbon::parse($row['manual']['at'])->timezone(config('app.timezone'))->format('d/m/Y h:i a') }}@endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($services !== [])
                    <div class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950/40">
                        <p class="bg-[#3b82f6] px-3 py-1.5 text-xs font-bold text-white">Servicios incluidos:</p>
                        <ol class="divide-y divide-slate-200 text-xs text-slate-700 dark:divide-white/10 dark:text-slate-200">
                            @foreach ($services as $service)
                                <li class="px-3 py-1.5">
                                    {{ $loop->iteration }}. {{ $service['name'] ?? '' }}
                                    @if (filled($service['description'] ?? null))
                                        <span class="text-slate-500 dark:text-slate-400">— {{ $service['description'] }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endif
            </div>
        @endforeach

        @foreach ($errors as $error)
            <p class="text-xs font-medium text-danger-600 dark:text-danger-400">{{ $error }}</p>
        @endforeach
    </div>
@endif
