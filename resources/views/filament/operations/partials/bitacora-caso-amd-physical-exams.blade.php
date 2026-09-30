@php
    /** @var list<array{date: string, doctor: string, reference: string, vitals: list<array{label: string, value: string}>, systems: list<array{label: string, text: string, is_default: bool}>}> $entries */
    $entries = $entries ?? [];
@endphp

@if ($entries !== [])
    <x-operations.bitacora-accordion title="Examen físico AMD" :count="count($entries)" :open="true">
        <div class="divide-y divide-slate-100 border-t border-slate-100 dark:divide-white/10 dark:border-white/10">
            @foreach ($entries as $index => $exam)
                <article wire:key="bitacora-amd-exam-{{ $index }}" class="space-y-3 px-4 py-3">
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ $exam['date'] }}
                        @if ($exam['doctor'] !== '—') · {{ $exam['doctor'] }} @endif
                        · {{ $exam['reference'] }}
                    </p>

                    @if ($exam['vitals'] !== [])
                        <div>
                            <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Signos vitales</p>
                            <ul class="flex flex-wrap gap-1.5">
                                @foreach ($exam['vitals'] as $vital)
                                    <li class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs text-slate-800 dark:bg-white/10 dark:text-slate-100">
                                        <span class="font-semibold">{{ $vital['label'] }}:</span> {{ $vital['value'] }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if ($exam['systems'] !== [])
                        <div>
                            <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Exploración física por sistemas</p>
                            <dl class="space-y-1.5">
                                @foreach ($exam['systems'] as $system)
                                    <div class="grid gap-x-3 text-sm sm:grid-cols-[11rem_1fr]">
                                        <dt class="font-semibold text-slate-900 dark:text-white">{{ $system['label'] }}:</dt>
                                        <dd @class([
                                            'text-slate-700 dark:text-slate-300',
                                            'rounded bg-amber-50 px-1.5 text-amber-900 dark:bg-amber-500/10 dark:text-amber-200' => ! $system['is_default'],
                                        ])>{{ $system['text'] }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                            @if (collect($exam['systems'])->contains('is_default', false))
                                <p class="mt-2 text-[11px] text-slate-400">Resaltado: hallazgos que el médico modificó respecto al examen normal.</p>
                            @endif
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    </x-operations.bitacora-accordion>
@endif
