@php
    $open = (bool) ($open ?? false);
    $entries = $entries ?? [];
@endphp

@if (count($entries) > 0)
    <x-operations.bitacora-accordion title="Informes AMD" :count="count($entries)" :open="$open">
        <div class="divide-y divide-slate-100 border-t border-slate-100 dark:divide-white/10 dark:border-white/10">
            @foreach ($entries as $index => $entry)
                @php
                    $summary = collect([
                        $entry['created_at_label'] ?? null,
                        $entry['doctor_name'] ?? null,
                        $entry['consultation_reference'] ?? null,
                    ])->filter(fn ($value) => filled($value) && $value !== '—')->implode(' · ');
                @endphp
                <article
                    x-data="{ open: false }"
                    class="px-4 py-2.5"
                    wire:key="bitacora-amd-{{ $entry['id'] ?? $index }}"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <button
                            type="button"
                            @click="open = ! open"
                            class="min-w-0 flex-1 text-left"
                        >
                            <span class="block truncate text-sm font-medium text-slate-800 dark:text-slate-100">
                                {{ $summary !== '' ? $summary : 'Informe AMD '.($index + 1) }}
                            </span>
                            <span class="mt-0.5 block truncate text-xs text-slate-500">
                                {{ $entry['document_name'] ?? 'Sin documento' }}
                            </span>
                        </button>
                        @include('filament.operations.partials.bitacora-caso-download-link', [
                            'available' => ! empty($entry['document_exists']),
                            'url' => $entry['download_url'] ?? null,
                            'filename' => $entry['document_name'] ?? null,
                        ])
                    </div>
                    <dl x-show="open" x-collapse class="mt-2 space-y-1.5" style="display: none;">
                        @foreach ([
                            'Consulta' => $entry['consultation_reference'] ?? null,
                            'Médico' => $entry['doctor_name'] ?? null,
                            'Motivo' => $entry['reason_consultation'] ?? null,
                            'Diagnóstico' => $entry['diagnostic_impression'] ?? null,
                            'Documento' => $entry['document_name'] ?? null,
                        ] as $label => $value)
                            @continue(! filled($value) || $value === '—')
                            <div class="grid gap-1 sm:grid-cols-3">
                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $label }}</dt>
                                <dd class="sm:col-span-2 text-sm text-slate-800 dark:text-slate-100">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </article>
            @endforeach
        </div>
    </x-operations.bitacora-accordion>
@endif
