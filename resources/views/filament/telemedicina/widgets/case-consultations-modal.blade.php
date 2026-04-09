@php
    $count = $consultations->count();
@endphp

<div class="telemedicine-ios-consultations-sheet space-y-5">
    {{-- Resumen del caso --}}
    <div
        class="flex flex-col gap-3 rounded-2xl border border-zinc-200/90 bg-gradient-to-b from-white/90 to-zinc-50/80 px-4 py-3.5 shadow-sm dark:border-white/10 dark:from-zinc-900/80 dark:to-zinc-950/70 sm:flex-row sm:items-center sm:justify-between"
    >
        <div class="min-w-0">
            <p class="text-[0.6875rem] font-semibold uppercase tracking-[0.08em] text-zinc-500 dark:text-zinc-400">
                Código del caso
            </p>
            <p class="mt-0.5 truncate text-lg font-semibold tracking-tight text-zinc-900 dark:text-zinc-50">
                {{ $caseCode }}
            </p>
        </div>
        <div
            class="inline-flex w-fit shrink-0 items-center rounded-full border border-zinc-200/80 bg-white/80 px-3 py-1 text-xs font-semibold tabular-nums text-zinc-700 shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-zinc-200"
        >
            {{ $count }} {{ $count === 1 ? 'consulta' : 'consultas' }}
        </div>
    </div>

    @if (! $canEditLast)
        <div
            class="flex gap-3 rounded-2xl border border-amber-200/90 bg-amber-50/90 px-4 py-3 dark:border-amber-500/35 dark:bg-amber-500/10"
            role="status"
        >
            <div
                class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-200"
                aria-hidden="true"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-amber-950 dark:text-amber-100">Solo lectura</p>
                <p class="mt-1 text-sm leading-relaxed text-amber-900/90 dark:text-amber-100/85">
                    El caso está en alta médica. Puedes abrir el detalle de cada consulta, pero no registrar una nueva
                    desde aquí.
                </p>
            </div>
        </div>
    @elseif ($canEditLast && $lastConsultationId)
        <div
            class="flex gap-3 rounded-2xl border border-sky-200/80 bg-sky-50/80 px-4 py-3 dark:border-sky-500/30 dark:bg-sky-500/10"
            role="note"
        >
            <div
                class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sky-100 text-sky-800 dark:bg-sky-500/25 dark:text-sky-100"
                aria-hidden="true"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-sky-950 dark:text-sky-50">Cómo actualizar</p>
                <p class="mt-1 text-sm leading-relaxed text-sky-900/88 dark:text-sky-100/85">
                    Localiza la tarjeta resaltada con la etiqueta <span class="font-semibold">Más reciente</span> y el
                    botón <span class="font-semibold">Actualizar</span> para abrir el asistente (paciente y caso en
                    sesión, paso 1 listo).
                </p>
            </div>
        </div>
    @endif

    <ul class="space-y-3" role="list">
        @forelse ($consultations as $consultation)
            @php
                $isLastInCase = $lastConsultationId && (int) $consultation->id === (int) $lastConsultationId;
                $showUpdateButton = $canEditLast && $isLastInCase;
            @endphp
            <li
                @class([
                    'flex flex-col gap-3 rounded-2xl border p-4 transition-shadow duration-200 sm:flex-row sm:items-center sm:justify-between sm:gap-4',
                    'border-zinc-200/90 bg-white/60 shadow-sm dark:border-white/10 dark:bg-zinc-900/40' => ! $isLastInCase,
                    'border-primary-500/40 bg-primary-500/[0.08] shadow-md ring-2 ring-primary-500/20 dark:border-primary-400/35 dark:bg-primary-400/[0.12] dark:ring-primary-400/25' => $isLastInCase && $canEditLast,
                    'border-zinc-300/90 bg-zinc-100/70 ring-1 ring-zinc-300/60 dark:border-zinc-600/60 dark:bg-zinc-800/50 dark:ring-zinc-500/30' => $isLastInCase && ! $canEditLast,
                ])
            >
                <div class="min-w-0 flex-1 space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">
                            Consulta #{{ $consultation->id }}
                        </span>
                        @if ($isLastInCase && $canEditLast)
                            <span
                                class="inline-flex items-center rounded-full bg-primary-600 px-2 py-0.5 text-[0.6875rem] font-semibold uppercase tracking-wide text-white dark:bg-primary-500"
                            >
                                Más reciente
                            </span>
                        @elseif ($isLastInCase)
                            <span
                                class="inline-flex items-center rounded-full border border-zinc-300/90 bg-white/80 px-2 py-0.5 text-[0.6875rem] font-semibold uppercase tracking-wide text-zinc-600 dark:border-white/15 dark:bg-white/5 dark:text-zinc-300"
                            >
                                Última consulta
                            </span>
                        @endif
                    </div>
                    @if (filled($consultation->telemedicine_service_list_id))
                        <div
                            class="inline-flex max-w-full flex-wrap items-center gap-x-1.5 gap-y-1 rounded-lg border border-zinc-200/80 bg-zinc-50/90 px-2.5 py-1 text-xs font-medium text-zinc-800 dark:border-white/10 dark:bg-white/5 dark:text-zinc-200"
                        >
                            <span class="text-zinc-500 dark:text-zinc-400">Servicio</span>
                            <span class="tabular-nums font-semibold text-zinc-900 dark:text-zinc-100">#{{ $consultation->telemedicine_service_list_id }}</span>
                            @if (filled($consultation->telemedicineServiceList?->name))
                                <span class="max-w-[14rem] truncate text-zinc-700 dark:text-zinc-300">
                                    {{ $consultation->telemedicineServiceList->name }}
                                </span>
                            @endif
                        </div>
                    @endif
                    @if ($consultation->created_at)
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                            <span class="font-medium text-zinc-600 dark:text-zinc-300">Registrada</span>
                            · {{ $consultation->created_at->format('d/m/Y · H:i') }}
                        </p>
                    @endif
                </div>
                <div class="flex shrink-0 flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
                    <x-filament::button
                        tag="a"
                        outlined
                        size="sm"
                        color="gray"
                        class="w-full justify-center sm:w-auto"
                        :href="$viewUrls[$consultation->id] ?? '#'"
                    >
                        Ver detalle
                    </x-filament::button>
                    @if ($showUpdateButton)
                        <x-filament::button
                            size="sm"
                            color="primary"
                            class="w-full justify-center sm:w-auto"
                            wire:click="redirectToConsultationCreateWizard({{ $consultation->id }})"
                        >
                            Actualizar
                        </x-filament::button>
                    @endif
                </div>
            </li>
        @empty
            <li
                class="rounded-2xl border border-dashed border-zinc-300/90 bg-zinc-50/50 px-4 py-12 text-center dark:border-zinc-600/50 dark:bg-zinc-900/30"
            >
                <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">Sin consultas todavía</p>
                <p class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">
                    Cuando registres la primera consulta para este caso, aparecerá aquí. Puedes iniciar el flujo desde el
                    menú de acciones de la fila en la tabla principal.
                </p>
            </li>
        @endforelse
    </ul>
</div>
