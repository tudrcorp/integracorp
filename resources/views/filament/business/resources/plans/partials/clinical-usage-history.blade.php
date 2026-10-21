@php
    use App\Support\ClinicalEntitlements\PlanClinicalUsageTrace;

    /** @var \Illuminate\Support\Collection<int, \App\Models\PlanClinicalUsageEvent> $events */
    $tz = (string) config('app.timezone');
    $tones = [
        'success' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/30',
        'info' => 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-400/30',
        'primary' => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20 dark:bg-indigo-500/10 dark:text-indigo-300 dark:ring-indigo-400/30',
        'warning' => 'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/30',
        'danger' => 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-400/30',
        'gray' => 'bg-gray-50 text-gray-600 ring-gray-500/20 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10',
    ];
@endphp

<div class="space-y-3">
    @if (! $tableReady)
        <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-500/10 dark:text-amber-200">
            El historial aún no está habilitado en este servidor. Los cambios igual quedan en la Auditoría de Negocios.
        </p>
    @elseif ($events->isEmpty())
        <p class="rounded-lg bg-gray-50 px-3 py-6 text-center text-sm text-gray-600 dark:bg-white/5 dark:text-gray-300">
            Todavía no hay movimientos registrados para este plan. Cada guardado, copia, beneficio o servicio macro nuevo aparecerá aquí.
        </p>
    @else
        <ol class="relative space-y-3 border-s border-gray-200 ps-4 dark:border-white/10">
            @foreach ($events as $event)
                @php
                    $at = $event->created_at?->timezone($tz);
                    $benefitChanges = (array) data_get($event->changes, 'benefits', []);
                @endphp
                <li class="relative space-y-1.5">
                    <span class="absolute -start-[1.4rem] top-1 size-3 rounded-full border-2 border-white bg-gray-300 dark:border-gray-900 dark:bg-gray-600"></span>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[0.7rem] font-medium ring-1 ring-inset {{ $tones[PlanClinicalUsageTrace::color($event->event)] ?? $tones['gray'] }}">
                            {{ PlanClinicalUsageTrace::label($event->event) }}
                        </span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $at?->format('d/m/Y H:i') }} · {{ $event->actor_name ?? 'sistema' }}
                        </span>
                    </div>
                    <p class="text-sm text-gray-800 dark:text-gray-200">{{ $event->description }}</p>

                    @if ($benefitChanges !== [])
                        <details class="rounded-lg bg-gray-50 px-3 py-2 text-xs dark:bg-white/5">
                            <summary class="cursor-pointer font-medium text-gray-700 dark:text-gray-200">Ver antes y después ({{ count($benefitChanges) }})</summary>
                            <ul class="mt-2 space-y-1.5">
                                @foreach ($benefitChanges as $change)
                                    <li class="text-gray-600 dark:text-gray-300">
                                        <span class="font-medium text-gray-900 dark:text-white">{{ $change['benefit'] ?? '' }}</span><br>
                                        <span class="text-gray-500 line-through dark:text-gray-400">{{ $change['before'] ?? 'Sin configurar' }}</span>
                                        →
                                        <span class="text-emerald-700 dark:text-emerald-300">{{ $change['after'] ?? 'Quitado' }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif
</div>
