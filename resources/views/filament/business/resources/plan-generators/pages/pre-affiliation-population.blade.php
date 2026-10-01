@php
    use App\Filament\Business\Resources\PlanGenerators\PlanGeneratorResource;
    use App\Support\PlanGenerators\PlanGeneratorCoverageAssignment;
    use App\Support\PlanGenerators\PlanGeneratorPopulationStatus;

    $plan = $this->getRecord();
    $imported = PlanGeneratorPopulationStatus::importedCount($plan);
    $declared = PlanGeneratorPopulationStatus::declaredPopulation();
    $running = PlanGeneratorPopulationStatus::isImportRunning($plan);
    $progress = PlanGeneratorPopulationStatus::importProgress($plan);
    $blocked = PlanGeneratorPopulationStatus::blockedReason($plan);
    $report = PlanGeneratorCoverageAssignment::report($plan);
    $planFailure = PlanGeneratorCoverageAssignment::planFailure($report);
    $assignedTotal = array_sum(array_column($report['by_coverage'], 'persons'));
    $editPlanUrl = PlanGeneratorResource::canEdit($plan) ? PlanGeneratorResource::getUrl('edit', ['record' => $plan->getKey()]) : null;
@endphp

<x-filament-panels::page>
    {{-- El bloque se refresca solo mientras el import corre, para que el
         analista vea avanzar el padrón sin recargar la página. --}}
    <div
        @if ($running) wire:poll.5s @endif
        class="grid gap-4 md:grid-cols-3"
    >
        {{-- Montos de la población realmente ubicada en cada cobertura, no de
             la cotizada: es lo que termina en la afiliación. --}}
        <div class="rounded-[1.25rem] border border-slate-200/90 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-900/60">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Coberturas elegidas</p>
            <ul class="mt-2 grid gap-2">
                @forelse ($report['coverages'] as $columnKey => $coverage)
                    @php $amounts = $report['by_coverage'][$columnKey] ?? ['persons' => 0, 'annual' => 0]; @endphp
                    <li wire:key="coverage-{{ $columnKey }}" class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold leading-snug text-slate-900 dark:text-white">{{ $coverage['label'] }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ PlanGeneratorCoverageAssignment::rangesLine($coverage) }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ number_format($amounts['persons'], 0, ',', '.') }} pers.</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">US$ {{ number_format($amounts['annual'], 2, ',', '.') }} anual</p>
                        </div>
                    </li>
                @empty
                    <li class="text-sm text-slate-500 dark:text-slate-400">—</li>
                @endforelse
            </ul>
        </div>

        <div class="rounded-[1.25rem] border border-slate-200/90 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-900/60">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Población cargada</p>
            <p class="mt-1 text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                {{ number_format($imported, 0, ',', '.') }}
                @if ($declared > 0)
                    <span class="text-sm font-medium text-slate-500 dark:text-slate-400">de {{ number_format($declared, 0, ',', '.') }} cotizadas</span>
                @endif
            </p>
            @if ($imported > 0)
                <p class="mt-1 text-xs font-medium text-slate-600 dark:text-slate-300">
                    {{ number_format($assignedTotal, 0, ',', '.') }} ubicada(s) en cobertura
                    @if ($report['unassigned'] > 0)
                        · <span class="text-amber-600 dark:text-amber-400">{{ number_format($report['unassigned'], 0, ',', '.') }} sin asignar</span>
                    @endif
                </p>
            @endif
            @if ($declared > 0 && $imported > 0 && $imported !== $declared)
                <p class="mt-1 text-xs font-medium text-amber-600 dark:text-amber-400">
                    El padrón no coincide con la población cotizada. Revise el archivo si la diferencia no es intencional.
                </p>
            @endif
        </div>

        <div @class([
            'rounded-[1.25rem] border p-4 shadow-sm',
            'border-rose-300/80 bg-rose-50/80 dark:border-rose-500/30 dark:bg-rose-500/10' => $blocked !== null && $blocked === $planFailure,
            'border-amber-300/80 bg-amber-50/80 dark:border-amber-500/30 dark:bg-amber-500/10' => $blocked !== null && $blocked !== $planFailure,
            'border-emerald-300/80 bg-emerald-50/80 dark:border-emerald-500/30 dark:bg-emerald-500/10' => $blocked === null,
        ])>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Estado</p>
            @if ($blocked === null)
                <p class="mt-1 flex items-center gap-2 text-sm font-semibold text-emerald-700 dark:text-emerald-300">
                    <x-filament::icon icon="heroicon-m-check-circle" class="size-5 shrink-0" />
                    Padrón listo. Continúe a la pre-afiliación.
                </p>
            @elseif ($blocked === $planFailure)
                {{-- No se arregla en esta pantalla: hay que corregir el plan. --}}
                <p class="mt-1 flex items-start gap-2 text-sm font-semibold leading-snug text-rose-800 dark:text-rose-200">
                    <x-filament::icon icon="heroicon-m-x-circle" class="mt-0.5 size-5 shrink-0" />
                    {{ $blocked }}
                </p>
                @if ($editPlanUrl !== null)
                    <a href="{{ $editPlanUrl }}" wire:navigate class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-rose-700 underline underline-offset-2 hover:text-rose-900 dark:text-rose-300 dark:hover:text-rose-100">
                        Abrir el plan para corregirlo
                        <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" class="size-4" />
                    </a>
                @endif
            @else
                <p class="mt-1 flex items-start gap-2 text-sm font-semibold leading-snug text-amber-800 dark:text-amber-200">
                    @if ($running)
                        <x-filament::loading-indicator class="mt-0.5 size-5 shrink-0" />
                    @else
                        <x-filament::icon icon="heroicon-m-exclamation-triangle" class="mt-0.5 size-5 shrink-0" />
                    @endif
                    {{ $blocked }}
                </p>
            @endif

            @if ($progress !== null && $progress['failed'] > 0)
                {{-- Filas realmente rechazadas (`failed_import_rows`), no la
                     resta total − exitosas, que antes de que el worker tocara
                     el lote acusaba de fallidas todas las filas. --}}
                <p class="mt-2 text-xs font-medium text-rose-600 dark:text-rose-400">
                    {{ number_format($progress['failed'], 0, ',', '.') }} fila(s) fallaron en el último import. Descargue el CSV de fallos desde la notificación, corríjalo y vuelva a importar.
                </p>
            @endif

            @if ($progress !== null && ! $progress['completed'])
                <p class="mt-2 text-xs font-medium text-slate-500 dark:text-slate-400">
                    {{ number_format($progress['processed'], 0, ',', '.') }} de {{ number_format($progress['total'], 0, ',', '.') }} filas procesadas.
                </p>
            @endif
        </div>
    </div>

    {{ $this->table }}

    <x-filament-actions::modals />
</x-filament-panels::page>
