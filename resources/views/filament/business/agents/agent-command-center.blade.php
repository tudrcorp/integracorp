@php
    /** @var \App\Models\Agent $record */
    $code = 'AGT-000'.$record->id;
    $typeLabel = $record->typeAgent?->definition ?? '—';
    $affiliations = $record->relationLoaded('affiliations') ? $record->affiliations : collect();
@endphp

<div class="agent-command-center-root space-y-6 overflow-x-hidden px-0.5 pb-6 sm:pb-8">
    {{-- Resumen principal --}}
    <section
        class="rounded-[1.35rem] border border-slate-200/80 bg-gradient-to-b from-white to-slate-50/95 p-5 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] ring-1 ring-black/[0.04] dark:border-white/10 dark:from-gray-900/90 dark:to-slate-950/90 dark:ring-white/[0.06]">
        <p class="text-[0.65rem] font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Agente</p>
        <div class="mt-2 space-y-3">
            <div>
                <p class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $record->name }}</p>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                    <span class="font-semibold text-slate-800 dark:text-slate-100">Código</span><br />
                    <span class="inline-flex items-center rounded-full bg-sky-100 px-2.5 py-0.5 text-xs font-semibold text-sky-900 dark:bg-sky-500/20 dark:text-sky-100">{{ $code }}</span>
                </p>
            </div>
            <div class="grid gap-3 border-t border-slate-200/70 pt-3 text-sm dark:border-white/10 sm:grid-cols-2">
                <div>
                    <p class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Estado</p>
                    <p class="mt-0.5 font-medium text-slate-900 dark:text-white">{{ $record->status }}</p>
                </div>
                <div>
                    <p class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Tipo</p>
                    <p class="mt-0.5 font-medium text-slate-900 dark:text-white">{{ $typeLabel }}</p>
                </div>
                <div>
                    <p class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Correo</p>
                    <p class="mt-0.5 break-all text-slate-800 dark:text-slate-100">{{ $record->email ?: '—' }}</p>
                </div>
                <div>
                    <p class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Teléfono</p>
                    <p class="mt-0.5 text-slate-800 dark:text-slate-100">{{ $record->phone ?: '—' }}</p>
                </div>
                <div class="sm:col-span-2">
                    <p class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Pertenece a (owner)</p>
                    <p class="mt-0.5 text-slate-800 dark:text-slate-100">{{ $record->owner_code ?: '—' }}</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Afiliaciones --}}
    <section class="rounded-[1.35rem] border border-slate-200/80 bg-white/90 p-5 ring-1 ring-black/[0.04] dark:border-white/10 dark:bg-white/[0.03] dark:ring-white/[0.05]">
        <p class="text-sm font-semibold text-slate-900 dark:text-white">Afiliaciones asociadas</p>
        <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
            Listado de afiliaciones individuales vinculadas a este agente. Si no hay registros, el agente aún no tiene afiliaciones cargadas con su código.
        </p>
        @if ($affiliations->isEmpty())
            <p class="mt-4 rounded-xl border border-dashed border-slate-200/90 bg-slate-50/80 px-4 py-6 text-center text-sm text-slate-500 dark:border-white/10 dark:bg-slate-900/40 dark:text-slate-400">
                No hay afiliaciones asociadas a este agente.
            </p>
        @else
            <ul class="mt-4 space-y-3">
                @foreach ($affiliations as $aff)
                    <li
                        class="rounded-xl border border-slate-200/80 bg-slate-50/60 px-4 py-3 text-sm dark:border-white/10 dark:bg-slate-900/50">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="font-semibold text-slate-900 dark:text-white">
                                    {{ $aff->full_name_ti ?? $aff->full_name_applicant ?? 'Afiliación #'.$aff->getKey() }}
                                </p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    Código: <span class="font-mono text-slate-700 dark:text-slate-200">{{ $aff->code ?: '—' }}</span>
                                    @if ($aff->relationLoaded('plan') && $aff->plan)
                                        · Plan: {{ $aff->plan->description ?? $aff->plan->name ?? '—' }}
                                    @endif
                                </p>
                            </div>
                            <span
                                class="shrink-0 rounded-full bg-slate-200/80 px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-slate-700 dark:bg-white/10 dark:text-slate-200">
                                {{ $aff->status ?? '—' }}
                            </span>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    @if (($canAddObservation ?? false) && is_array($noteTimeline ?? null))
        @include('filament.business.agents.agent-notes-command-center', [
            'record' => $record,
            'noteTimeline' => $noteTimeline,
        ])
    @endif

    {{-- Acciones --}}
    <section class="rounded-[1.35rem] border border-slate-200/80 bg-white/90 p-5 ring-1 ring-black/[0.04] dark:border-white/10 dark:bg-white/[0.03] dark:ring-white/[0.05]">
        <p class="text-sm font-semibold text-slate-900 dark:text-white">Acciones rápidas</p>
        <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
            Cada botón ejecuta la misma acción que el menú «⋯» de la fila. Se abrirá el flujo correspondiente (confirmación o formulario).
        </p>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            @if ($canActivate)
                <button
                    type="button"
                    wire:click.prevent="mountTableAction('Activate', '{{ $record->getKey() }}')"
                    wire:loading.attr="disabled"
                    class="ticket-btn-ios-success inline-flex min-h-[2.75rem] w-full items-center justify-center gap-2 rounded-2xl px-4 py-3 text-sm font-semibold shadow-sm transition active:scale-[0.98] disabled:opacity-50">
                    Activar agente
                </button>
            @endif

            @if ($canEditHierarchy)
                <button
                    type="button"
                    wire:click.prevent="mountTableAction('edit_jerarquia', '{{ $record->getKey() }}')"
                    wire:loading.attr="disabled"
                    class="inline-flex min-h-[2.75rem] w-full items-center justify-center gap-2 rounded-2xl border border-amber-200/80 bg-amber-50/90 px-4 py-3 text-sm font-semibold text-amber-950 shadow-sm transition active:scale-[0.98] disabled:opacity-50 dark:border-amber-500/30 dark:bg-amber-950/30 dark:text-amber-100">
                    Editar jerarquía
                </button>
            @endif

            @if ($canInactivate)
                <button
                    type="button"
                    wire:click.prevent="mountTableAction('Inactivate', '{{ $record->getKey() }}')"
                    wire:loading.attr="disabled"
                    class="inline-flex min-h-[2.75rem] w-full items-center justify-center gap-2 rounded-2xl border border-rose-200/80 bg-rose-50/90 px-4 py-3 text-sm font-semibold text-rose-900 shadow-sm transition active:scale-[0.98] disabled:opacity-50 dark:border-rose-500/30 dark:bg-rose-950/35 dark:text-rose-100">
                    Inactivar
                </button>
            @endif

            @if ($canDelete)
                <button
                    type="button"
                    wire:click.prevent="mountTableAction('delete', '{{ $record->getKey() }}')"
                    wire:loading.attr="disabled"
                    class="inline-flex min-h-[2.75rem] w-full items-center justify-center gap-2 rounded-2xl border border-rose-300/90 bg-white px-4 py-3 text-sm font-semibold text-rose-700 shadow-sm transition active:scale-[0.98] disabled:opacity-50 dark:border-rose-500/40 dark:bg-rose-950/20 dark:text-rose-200">
                    Eliminar
                </button>
            @endif

        </div>
    </section>
</div>
