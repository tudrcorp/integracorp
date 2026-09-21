@php
    $open = (bool) ($open ?? true);
    $entries = $entries ?? [];
    $available = collect($entries)->where('exists', true)->count();
    $toneClasses = [
        'primary' => 'border-sky-400/40 bg-sky-500/10 text-sky-800 dark:text-sky-200',
        'info' => 'border-cyan-400/40 bg-cyan-500/10 text-cyan-800 dark:text-cyan-200',
        'warning' => 'border-amber-400/40 bg-amber-500/10 text-amber-800 dark:text-amber-200',
        'success' => 'border-emerald-400/40 bg-emerald-500/10 text-emerald-800 dark:text-emerald-200',
        'danger' => 'border-rose-400/40 bg-rose-500/10 text-rose-800 dark:text-rose-200',
        'gray' => 'border-slate-300/70 bg-slate-100 text-slate-700 dark:border-white/15 dark:bg-white/10 dark:text-slate-200',
    ];
@endphp

<x-operations.bitacora-accordion title="Documentos del caso" :count="count($entries)" :open="$open">
    @if (count($entries) === 0)
        <p class="border-t border-slate-100 px-4 py-3 text-sm text-slate-500 dark:border-white/10">
            No hay documentos consignados en este caso.
        </p>
    @else
        <div class="border-t border-slate-100 dark:border-white/10">
            <p class="px-4 py-2 text-[11px] font-medium text-slate-500 dark:text-slate-400">
                {{ $available }} disponible{{ $available === 1 ? '' : 's' }} de {{ count($entries) }}
            </p>
            <ul class="divide-y divide-slate-100 dark:divide-white/10">
                @foreach ($entries as $index => $entry)
                    @php
                        $tone = (string) ($entry['category_tone'] ?? 'gray');
                        $badgeClass = $toneClasses[$tone] ?? $toneClasses['gray'];
                    @endphp
                    <li
                        wire:key="bitacora-doc-{{ $entry['uid'] ?? $index }}"
                        class="flex flex-wrap items-center justify-between gap-3 px-4 py-2.5"
                    >
                        <div class="min-w-0 flex-1 space-y-1">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="inline-flex rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $badgeClass }}">
                                    {{ $entry['category'] ?? 'Documento' }}
                                </span>
                                @if (filled($entry['extension'] ?? null))
                                    <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[10px] font-semibold text-slate-500 dark:border-white/15 dark:bg-white/5 dark:text-slate-300">
                                        {{ $entry['extension'] }}
                                    </span>
                                @endif
                            </div>
                            <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">
                                {{ $entry['document_name'] ?? 'Documento' }}
                            </p>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                                {{ collect([
                                    $entry['reference'] ?? null,
                                    $entry['types_label'] ?? null,
                                    $entry['uploaded_at_label'] ?? null,
                                ])->filter(fn ($value) => filled($value) && $value !== '—')->implode(' · ') }}
                            </p>
                        </div>
                        @include('filament.operations.partials.bitacora-caso-download-link', [
                            'available' => ! empty($entry['exists']),
                            'url' => $entry['download_url'] ?? null,
                            'filename' => $entry['document_name'] ?? null,
                        ])
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</x-operations.bitacora-accordion>
