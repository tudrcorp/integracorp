@php
    /** @var list<array<string, mixed>> $cases */
    $stageClasses = [
        'initial' => 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-300',
        'follow_up' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300',
        'discharge' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300',
    ];
    $dotClasses = [
        'initial' => 'bg-sky-500',
        'follow_up' => 'bg-amber-500',
        'discharge' => 'bg-emerald-500',
    ];
@endphp

<div class="space-y-3">
    @if ($cases === [])
        <p class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500 dark:border-white/15 dark:text-slate-400">
            El paciente no tiene consultas anteriores.
        </p>
    @else
        <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
            <span>Agrupadas por caso, del más reciente al más antiguo.</span>
            <span class="inline-flex items-center gap-1"><span class="size-2 rounded-full bg-sky-500"></span>Cubierto</span>
            <span class="inline-flex items-center gap-1"><span class="size-2 rounded-full bg-amber-500"></span>No cubierto</span>
        </div>

        @foreach ($cases as $index => $case)
            <section
                x-data="{ open: {{ $case['is_current'] || $index === 0 ? 'true' : 'false' }} }"
                wire:key="previous-consultations-case-{{ $case['case_id'] ?? 'none' }}-{{ $index }}"
                @class([
                    'overflow-hidden rounded-xl border bg-white dark:bg-gray-900',
                    'border-primary-400 ring-1 ring-primary-400 dark:border-primary-500' => $case['is_current'],
                    'border-slate-200 dark:border-white/10' => ! $case['is_current'],
                ])
            >
                <button
                    type="button"
                    @click="open = ! open"
                    class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left hover:bg-slate-50 dark:hover:bg-white/5"
                    :aria-expanded="open.toString()"
                >
                    <span class="flex min-w-0 flex-wrap items-center gap-2">
                        <span class="font-semibold text-slate-900 dark:text-white">{{ $case['code'] }}</span>
                        @if ($case['is_current'])
                            <span class="rounded-md bg-primary-600 px-2 py-0.5 text-[11px] font-semibold text-white">Caso actual</span>
                        @endif
                        <span class="rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-700 dark:bg-white/10 dark:text-slate-200">{{ $case['status'] }}</span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">
                            Abierto el {{ $case['opened_at'] }} · {{ count($case['consultations']) }} {{ count($case['consultations']) === 1 ? 'consulta' : 'consultas' }}
                        </span>
                    </span>
                    <x-filament::icon
                        icon="heroicon-m-chevron-down"
                        class="size-4 shrink-0 text-slate-400 transition-transform"
                        x-bind:class="open && 'rotate-180'"
                    />
                </button>

                <ol x-show="open" x-collapse class="space-y-4 border-t border-slate-100 px-4 py-4 dark:border-white/10">
                    @foreach ($case['consultations'] as $consultation)
                        <li class="relative pl-5">
                            <span class="absolute left-0 top-1.5 size-2.5 rounded-full {{ $dotClasses[$consultation['tone']] ?? 'bg-slate-400' }}" aria-hidden="true"></span>
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span class="rounded-md px-2 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $stageClasses[$consultation['tone']] ?? $stageClasses['follow_up'] }}">{{ $consultation['stage'] }}</span>
                                <span class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $consultation['date'] }}
                                    @if ($consultation['doctor'] !== '—') · {{ $consultation['doctor'] }} @endif
                                    @if ($consultation['service'] !== '—') · {{ $consultation['service'] }} @endif
                                </span>
                            </div>

                            @if (filled($consultation['reason']) || filled($consultation['diagnosis']))
                                <dl class="mt-1.5 space-y-1 text-sm">
                                    @if (filled($consultation['reason']))
                                        <div class="grid gap-x-3 sm:grid-cols-[8rem_1fr]">
                                            <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Motivo</dt>
                                            <dd class="text-slate-800 dark:text-slate-100">{{ $consultation['reason'] }}</dd>
                                        </div>
                                    @endif
                                    @if (filled($consultation['diagnosis']))
                                        <div class="grid gap-x-3 sm:grid-cols-[8rem_1fr]">
                                            <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Diagnóstico</dt>
                                            <dd class="text-slate-800 dark:text-slate-100">{{ $consultation['diagnosis'] }}</dd>
                                        </div>
                                    @endif
                                </dl>
                            @endif

                            @if ($consultation['covered'] !== [] || $consultation['not_covered'] !== [])
                                <ul class="mt-2 flex flex-wrap gap-1.5" aria-label="Indicaciones de la consulta">
                                    @foreach ($consultation['covered'] as $item)
                                        <li class="rounded-md bg-sky-50 px-2 py-0.5 text-[11px] text-sky-800 ring-1 ring-inset ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-200" title="{{ $item['type'] }} · Cubierto">
                                            <span class="font-semibold">{{ $item['type'] }}:</span> {{ $item['name'] }}
                                        </li>
                                    @endforeach
                                    @foreach ($consultation['not_covered'] as $item)
                                        <li class="rounded-md bg-amber-50 px-2 py-0.5 text-[11px] text-amber-800 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-200" title="{{ $item['type'] }} · No cubierto">
                                            <span class="font-semibold">{{ $item['type'] }}:</span> {{ $item['name'] }}
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            @if (! empty($consultation['physical_exam']))
                                @php($exam = $consultation['physical_exam'])
                                <div x-data="{ exam: false }" class="mt-2">
                                    <button
                                        type="button"
                                        @click="exam = ! exam"
                                        class="inline-flex items-center gap-1 text-xs font-semibold text-primary-700 hover:underline dark:text-primary-300"
                                        :aria-expanded="exam.toString()"
                                    >
                                        <x-filament::icon icon="heroicon-m-heart" class="size-3.5" />
                                        <span x-text="exam ? 'Ocultar examen físico' : 'Ver examen físico'"></span>
                                    </button>
                                    <div x-show="exam" x-collapse class="mt-2 space-y-2 rounded-lg bg-slate-50 p-3 dark:bg-white/5" style="display: none;">
                                        @if ($exam['vitals'] !== [])
                                            <p class="text-xs text-slate-700 dark:text-slate-200">
                                                @foreach ($exam['vitals'] as $vital)
                                                    <span class="font-semibold">{{ $vital['label'] }}:</span> {{ $vital['value'] }}@if (! $loop->last) · @endif
                                                @endforeach
                                            </p>
                                        @endif
                                        <dl class="space-y-1">
                                            @foreach ($exam['systems'] as $system)
                                                <div class="text-xs">
                                                    <dt class="inline font-semibold text-slate-900 dark:text-white">{{ $system['label'] }}:</dt>
                                                    <dd @class([
                                                        'inline text-slate-700 dark:text-slate-300',
                                                        'rounded bg-amber-100 px-1 text-amber-900 dark:bg-amber-500/15 dark:text-amber-200' => ! $system['is_default'],
                                                    ])>{{ $system['text'] }}</dd>
                                                </div>
                                            @endforeach
                                        </dl>
                                    </div>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </section>
        @endforeach
    @endif
</div>
