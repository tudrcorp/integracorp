@php
    /** @var list<array{stage: string, tone: string, date: string, doctor: string, reference: string, fields: array<string, string>, truncated: bool}> $entries */
    $entries = $entries ?? [];
    $dotClasses = [
        'initial' => 'bg-sky-500 ring-sky-100 dark:ring-sky-500/20',
        'follow_up' => 'bg-amber-500 ring-amber-100 dark:ring-amber-500/20',
        'discharge' => 'bg-emerald-500 ring-emerald-100 dark:ring-emerald-500/20',
    ];
    $badgeClasses = [
        'initial' => 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-300',
        'follow_up' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300',
        'discharge' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300',
    ];
@endphp

<x-operations.bitacora-accordion title="Resumen clínico del caso" :count="count($entries)" :open="true">
    <div class="border-t border-slate-100 px-4 py-3 dark:border-white/10">
        @if ($entries === [])
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Este caso aún no tiene consultas registradas.
            </p>
        @else
            <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">
                Historia del caso desde la consulta inicial. Laboratorios, medicamentos, estudios y especialistas están en sus propias secciones.
            </p>
            <ol class="relative space-y-4 border-l border-slate-200 pl-5 dark:border-white/10">
                @foreach ($entries as $index => $entry)
                    <li wire:key="bitacora-clinical-summary-{{ $index }}" class="relative">
                        <span
                            class="absolute -left-[26px] top-1 size-3 rounded-full ring-4 {{ $dotClasses[$entry['tone']] ?? $dotClasses['follow_up'] }}"
                            aria-hidden="true"
                        ></span>
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $badgeClasses[$entry['tone']] ?? $badgeClasses['follow_up'] }}">
                                {{ $entry['stage'] }}
                            </span>
                            <span class="text-xs text-slate-500 dark:text-slate-400">
                                {{ $entry['date'] }}
                                @if ($entry['doctor'] !== '—')
                                    · {{ $entry['doctor'] }}
                                @endif
                                · {{ $entry['reference'] }}
                            </span>
                        </div>

                        @if ($entry['fields'] === [])
                            <p class="mt-1.5 text-sm text-slate-500 dark:text-slate-400">Sin notas clínicas en este registro.</p>
                        @else
                            <dl class="mt-1.5 space-y-1">
                                @foreach ($entry['fields'] as $label => $value)
                                    <div class="grid gap-x-3 sm:grid-cols-[10rem_1fr]">
                                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $label }}</dt>
                                        <dd class="text-sm text-slate-800 dark:text-slate-100">{{ $value }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        @endif

                        @if ($entry['truncated'])
                            <p class="mt-1 text-[11px] text-slate-400">
                                Texto resumido. La nota completa está en «Consultas y notas médicas».
                            </p>
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</x-operations.bitacora-accordion>
