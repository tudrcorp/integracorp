@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\AffiliateBusinessUnitChange> $changes */
    $tz = (string) config('app.timezone');
@endphp

<div class="space-y-3">
    @if ($changes->isEmpty())
        <p class="rounded-lg bg-gray-50 px-3 py-6 text-center text-sm text-gray-600 dark:bg-white/5 dark:text-gray-300">
            Este afiliado no tiene cambios manuales de unidad específica. Cuando alguien la cambie, aparecerá aquí con su nota.
        </p>
    @else
        <ol class="relative space-y-4 border-s border-gray-200 ps-4 dark:border-white/10">
            @foreach ($changes as $change)
                <li class="relative space-y-1.5">
                    <span class="absolute -start-[1.4rem] top-1 size-3 rounded-full border-2 border-white bg-primary-500 dark:border-gray-900"></span>

                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $change->created_at?->timezone($tz)->format('d/m/Y H:i') }} · {{ $change->actor_name ?? 'sistema' }}
                        @if ($change->inherited)
                            · <span class="font-medium text-gray-700 dark:text-gray-200">volvió a heredar de la afiliación</span>
                        @endif
                    </p>

                    <p class="text-sm">
                        <span class="text-gray-500 line-through dark:text-gray-400">{{ $change->before_value ?? 'Sin unidad específica' }}</span>
                        <span class="text-gray-400">→</span>
                        <span class="font-semibold text-emerald-700 dark:text-emerald-300">{{ $change->after_value ?? 'Sin unidad específica' }}</span>
                    </p>

                    <p class="rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-800 ring-1 ring-gray-950/5 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10">
                        {{ $change->note }}
                    </p>

                    @if (! empty($change->telemedicine_patient_ids))
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Paciente(s) de telemedicina actualizados: {{ implode(', ', array_map(fn ($id) => '#'.$id, (array) $change->telemedicine_patient_ids)) }}
                        </p>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif
</div>
