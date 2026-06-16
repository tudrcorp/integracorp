@php
    /** @var \App\Models\Agency $record */
    $typeLabel = $record->typeAgency?->definition ?? '—';
@endphp

<div class="agency-command-center-root space-y-6 overflow-x-hidden px-0.5 pb-6 sm:pb-8">
    <section
        class="rounded-[1.35rem] border border-slate-200/80 bg-gradient-to-b from-white to-slate-50/95 p-5 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] ring-1 ring-black/[0.04] dark:border-white/10 dark:from-gray-900/90 dark:to-slate-950/90 dark:ring-white/[0.06]">
        <p class="text-[0.65rem] font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Agencia</p>
        <div class="mt-2 space-y-3">
            <div>
                <p class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $record->name_corporative }}</p>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                    <span class="font-semibold text-slate-800 dark:text-slate-100">Código</span><br />
                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-900 dark:bg-emerald-500/20 dark:text-emerald-100">{{ $typeLabel }} — {{ $record->code }}</span>
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
                    <p class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Owner (owner_code)</p>
                    <p class="mt-0.5 font-mono text-sm text-slate-800 dark:text-slate-100">{{ $record->owner_code ?: '—' }}</p>
                </div>
            </div>
        </div>
    </section>

    @if (($canAddObservation ?? false) && is_array($noteTimeline ?? null))
        @include('filament.business.agencies.agency-notes-command-center', [
            'record' => $record,
            'noteTimeline' => $noteTimeline,
        ])
    @endif

    @include('filament.business.agencies.agency-ficha-panel', ['record' => $record])
</div>
