@php
    /** @var array{upgrades?: list<array<string, mixed>>, errors?: list<string>} $upgradeQuote */
    use App\Support\PlanGenerators\PlanGeneratorUpgrades;
    use Illuminate\Support\Carbon;

    // Ruta de esta fila del repetidor (`data.upgrades.{clave}`). La variable no
    // puede llamarse `$statePath`: Filament inyecta ese nombre como closure.
    $itemStatePath = (isset($getStatePath) && $getStatePath instanceof \Closure) ? (string) $getStatePath() : '';
    $upgrade = $upgradeQuote['upgrades'][0] ?? null;
    $populationUnitLabel = (string) ($populationUnitLabel ?? 'Población');
@endphp

@if ($upgrade === null)
    @foreach ((array) ($upgradeQuote['errors'] ?? []) as $error)
        <p class="text-sm font-medium text-danger-600 dark:text-danger-400">{{ $error }}</p>
    @endforeach
@else
    @php
        $rows = (array) $upgrade['rows'];
        $count = static fn (string $status): int => count(array_filter($rows, static fn (array $row): bool => $row['status'] === $status));
        $excludedCount = $count(PlanGeneratorUpgrades::STATUS_EXCLUDED);
        $manualCount = $count(PlanGeneratorUpgrades::STATUS_MANUAL);
        $partialWithPeople = array_filter($rows, static fn (array $row): bool => $row['status'] === PlanGeneratorUpgrades::STATUS_PARTIAL && $row['population'] > 0);
        $persons = (int) $upgrade['persons'];
        $population = (int) $upgrade['population'];
        $errors = array_values(array_filter(
            (array) ($upgradeQuote['errors'] ?? []),
            static fn (string $error): bool => ! str_contains($error, 'no se aplica a ninguna persona'),
        ));
    @endphp

    <div class="space-y-3" wire:key="pg-upgrade-population-{{ $upgrade['line_key'] }}">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Paso 2 · ¿A quién se le agrega?</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">«{{ $upgrade['upgrade_name'] }}» cubre a personas de <span class="font-semibold">{{ $upgrade['covers'] }}</span>. Nadie fuera de esas edades puede recibirlo.</p>
            </div>
            @if ($excludedCount > 0 && $itemStatePath !== '')
                <x-filament::button
                    type="button"
                    size="xs"
                    color="gray"
                    icon="heroicon-m-arrow-uturn-left"
                    wire:click="includeAllPlanGeneratorUpgradeRanges(@js($itemStatePath))"
                    wire:loading.attr="disabled"
                >
                    Volver a incluir los rangos excluidos
                </x-filament::button>
            @endif
        </div>

        <div @class([
            'rounded-xl border px-3 py-2 text-sm',
            'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200' => $persons > 0,
            'border-danger-200 bg-danger-50 text-danger-800 dark:border-danger-500/30 dark:bg-danger-500/10 dark:text-danger-200' => $persons === 0,
        ])>
            @if ($persons > 0)
                Se agrega a <span class="font-bold">{{ $persons }} de {{ $population }}</span> {{ $population === 1 ? 'persona' : 'personas' }}
                · suma <span class="font-bold">{{ PlanGeneratorUpgrades::money($upgrade['annual']) }} al año</span> al total grupal de cada columna.
                @if ($manualCount > 0)
                    <span class="font-semibold">Incluye {{ $manualCount }} {{ $manualCount === 1 ? 'rango declarado' : 'rangos declarados' }} a mano.</span>
                @endif
            @elseif ($partialWithPeople !== [])
                Todavía no se aplica a nadie: los rangos de esta cotización solo están cubiertos en parte. Use «Incluir personas» en los rangos donde corresponda, o quite el upgrade. Así no se puede guardar.
            @else
                Este upgrade no se aplica a nadie: ningún rango con personas tiene las edades que cubre. Quite el upgrade o elija otra cobertura. Así no se puede guardar.
            @endif
        </div>

        @foreach ($errors as $error)
            <p class="rounded-lg bg-danger-50 px-3 py-1.5 text-xs font-medium text-danger-700 dark:bg-danger-500/10 dark:text-danger-300">{{ $error }}</p>
        @endforeach

        <div class="overflow-x-auto rounded-xl border border-slate-200/80 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950/40">
            <table class="min-w-full border-collapse text-xs leading-snug text-slate-800 dark:text-slate-100">
                <thead>
                    <tr class="bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-300">
                        <th class="px-3 py-2 text-left font-semibold">Rango de edad</th>
                        <th class="px-3 py-2 text-center font-semibold">{{ $populationUnitLabel }}</th>
                        <th class="px-3 py-2 text-center font-semibold">Reciben el upgrade</th>
                        <th class="px-3 py-2 text-center font-semibold">Precio por persona</th>
                        <th class="px-3 py-2 text-center font-semibold">Subtotal anual</th>
                        <th class="px-3 py-2 text-center font-semibold">¿Se le agrega?</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-white/10">
                    @foreach ($rows as $row)
                        @php
                            $status = $row['status'];
                            $isIncluded = $status === PlanGeneratorUpgrades::STATUS_INCLUDED;
                            $isExcluded = $status === PlanGeneratorUpgrades::STATUS_EXCLUDED;
                            $isManual = $status === PlanGeneratorUpgrades::STATUS_MANUAL;
                            $isPartial = $status === PlanGeneratorUpgrades::STATUS_PARTIAL;
                            $isCharged = $isIncluded || $isManual;
                            $manual = $isManual ? (array) $row['manual'] : [];
                            $manualAt = filled($manual['at'] ?? null) ? Carbon::parse($manual['at'])->timezone(config('app.timezone'))->format('d/m/Y h:i a') : null;
                            $label = $row['label'] !== '' ? $row['label'] : 'Rango sin nombre';
                        @endphp
                        <tr @class([
                            'bg-white dark:bg-transparent' => $isIncluded,
                            'bg-amber-50/70 dark:bg-amber-500/[0.06]' => $isManual,
                            'bg-sky-50/50 dark:bg-sky-500/[0.04]' => $isPartial,
                            'bg-slate-50 text-slate-400 dark:bg-white/[0.02] dark:text-slate-500' => ! $isCharged && ! $isPartial,
                        ]) wire:key="pg-upgrade-row-{{ $upgrade['line_key'] }}-{{ $row['index'] }}">
                            <td class="px-3 py-2 align-top font-medium">
                                {{ $label }}
                                @if ($isPartial || $isManual)
                                    <span class="block text-[11px] font-normal text-sky-700 dark:text-sky-300">El upgrade cubre solo {{ $row['covered'] }}</span>
                                @endif
                                @if ($isManual)
                                    <p class="mt-1 max-w-md text-[11px] font-normal leading-snug text-amber-900 dark:text-amber-200">
                                        <span class="font-semibold">Motivo:</span> {{ $manual['note'] ?? '' }}
                                        <span class="block text-amber-700/80 dark:text-amber-300/70">
                                            {{ filled($manual['by'] ?? null) ? 'Declarado por '.$manual['by'] : 'Declarado' }}{{ $manualAt !== null ? ' · '.$manualAt : '' }} · queda registrado al guardar
                                        </span>
                                    </p>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-center align-top">{{ $row['population'] }}</td>
                            <td class="px-3 py-2 text-center align-top font-semibold">
                                @if ($isIncluded)
                                    {{ $row['charged_persons'] }}
                                @elseif ($isManual)
                                    {{ $row['charged_persons'] }} de {{ $row['population'] }}
                                    <span class="block text-[10px] font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">declaradas</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-3 py-2 text-center align-top">{{ ($isCharged || $isPartial) && $row['price'] !== null ? PlanGeneratorUpgrades::money($row['price']) : '—' }}</td>
                            <td class="px-3 py-2 text-center align-top font-semibold">{{ $isCharged ? PlanGeneratorUpgrades::money($row['subtotal']) : '—' }}</td>
                            <td class="px-3 py-2 text-center align-top">
                                @if ($status === PlanGeneratorUpgrades::STATUS_NO_PRICE)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-200 px-2 py-0.5 text-[11px] font-semibold text-slate-600 dark:bg-white/10 dark:text-slate-300" title="Ninguna edad de este rango está entre {{ $upgrade['covers'] }}">
                                        <x-filament::icon icon="heroicon-m-no-symbol" class="size-3.5" />
                                        No aplica: ninguna edad está cubierta
                                    </span>
                                @elseif ($isPartial)
                                    @if ($row['population'] > 0 && $itemStatePath !== '')
                                        <button
                                            type="button"
                                            wire:click="mountAction('includeUpgradeRangeManually', @js(['path' => $itemStatePath, 'label' => $row['label']]))"
                                            wire:loading.attr="disabled"
                                            class="inline-flex items-center gap-1 rounded-full border border-amber-300 bg-white px-2.5 py-1 text-[11px] font-semibold text-amber-800 transition hover:bg-amber-50 disabled:opacity-50 dark:border-amber-500/40 dark:bg-transparent dark:text-amber-200 dark:hover:bg-amber-500/10"
                                            title="Declarar cuántas personas de este rango tienen {{ $row['covered'] }}"
                                        >
                                            <x-filament::icon icon="heroicon-m-hand-raised" class="size-3.5" />
                                            Incluir personas de {{ $row['covered'] }}
                                        </button>
                                    @else
                                        <span class="text-[11px] text-slate-500 dark:text-slate-400">Cubierto en parte · sin personas</span>
                                    @endif
                                @elseif ($isManual)
                                    <div class="flex flex-col items-center gap-1.5">
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-200 px-2.5 py-1 text-[11px] font-semibold text-amber-900 dark:bg-amber-500/25 dark:text-amber-100">
                                            <x-filament::icon icon="heroicon-m-hand-raised" class="size-3.5" />
                                            Incluido a mano
                                        </span>
                                        @if ($itemStatePath !== '')
                                            <div class="flex items-center gap-2">
                                                <button
                                                    type="button"
                                                    wire:click="mountAction('includeUpgradeRangeManually', @js(['path' => $itemStatePath, 'label' => $row['label']]))"
                                                    wire:loading.attr="disabled"
                                                    class="text-[11px] font-semibold text-amber-800 hover:underline disabled:opacity-50 dark:text-amber-200"
                                                >Cambiar</button>
                                                <button
                                                    type="button"
                                                    wire:click="removePlanGeneratorUpgradeManualRange(@js($itemStatePath), @js($row['label']))"
                                                    wire:confirm="¿Quitar las personas declaradas en «{{ $label }}»? El rango vuelve a «cubierto en parte» y su nota se descarta."
                                                    wire:loading.attr="disabled"
                                                    class="text-[11px] font-semibold text-danger-600 hover:underline disabled:opacity-50 dark:text-danger-400"
                                                >Quitar</button>
                                            </div>
                                        @endif
                                    </div>
                                @elseif ($itemStatePath !== '')
                                    <button
                                        type="button"
                                        wire:click="togglePlanGeneratorUpgradeRange(@js($itemStatePath), @js($row['label']))"
                                        wire:loading.attr="disabled"
                                        wire:target="togglePlanGeneratorUpgradeRange"
                                        @class([
                                            'inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold transition disabled:opacity-50',
                                            'bg-emerald-100 text-emerald-800 hover:bg-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-200' => $isIncluded,
                                            'bg-slate-200 text-slate-600 hover:bg-slate-300 dark:bg-white/10 dark:text-slate-300' => $isExcluded,
                                        ])
                                        title="{{ $isIncluded ? 'Sacar este rango del upgrade' : 'Agregar este rango al upgrade' }}"
                                    >
                                        <x-filament::icon :icon="$isIncluded ? 'heroicon-m-check-circle' : 'heroicon-m-minus-circle'" class="size-3.5" />
                                        {{ $isIncluded ? 'Incluido' : 'Excluido' }}
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <ul class="list-disc space-y-0.5 ps-5 text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">
            <li><span class="font-semibold">Incluido / Excluido:</span> el upgrade cubre todas las edades del rango; pulse para sacarlo o volver a incluirlo.</li>
            <li><span class="font-semibold">Incluir personas:</span> el upgrade cubre solo una parte de las edades del rango. Declare cuántas personas tienen esa edad y por qué; el precio es el del upgrade.</li>
            <li><span class="font-semibold">Al aprobar la cotización</span> el upgrade se asigna por la edad real de cada persona del padrón: quien no tenga la edad no lo recibe, aunque su rango esté incluido.</li>
        </ul>
    </div>
@endif
