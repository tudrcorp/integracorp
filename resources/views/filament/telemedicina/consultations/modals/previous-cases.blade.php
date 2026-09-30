@php
    /** @var list<array{id: int, code: string, status: string, tone: string, is_current: bool, opened_at: string, reason: string|null, doctor: string, priority: string|null, consultations: int}> $cases */
    $statusClasses = [
        'success' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300',
        'warning' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300',
        'danger' => 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-300',
        'info' => 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-300',
    ];
@endphp

<div class="space-y-3">
    @if ($cases === [])
        <p class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500 dark:border-white/15 dark:text-slate-400">
            El paciente no tiene casos anteriores.
        </p>
    @else
        <p class="text-xs text-slate-500 dark:text-slate-400">
            {{ count($cases) === 1 ? '1 caso' : count($cases).' casos' }}, del más reciente al más antiguo.
        </p>

        <ul class="space-y-2">
            @foreach ($cases as $case)
                <li
                    wire:key="previous-case-{{ $case['id'] }}"
                    @class([
                        'flex flex-col gap-3 rounded-xl border bg-white p-4 dark:bg-gray-900 sm:flex-row sm:items-center sm:justify-between',
                        'border-primary-400 ring-1 ring-primary-400 dark:border-primary-500' => $case['is_current'],
                        'border-slate-200 dark:border-white/10' => ! $case['is_current'],
                    ])
                >
                    <div class="min-w-0 space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold text-slate-900 dark:text-white">{{ $case['code'] }}</span>
                            @if ($case['is_current'])
                                <span class="rounded-md bg-primary-600 px-2 py-0.5 text-[11px] font-semibold text-white">Caso actual</span>
                            @endif
                            <span class="rounded-md px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset {{ $statusClasses[$case['tone']] ?? $statusClasses['info'] }}">{{ $case['status'] }}</span>
                            @if (filled($case['priority']))
                                <span class="text-[11px] text-slate-500 dark:text-slate-400">Prioridad {{ $case['priority'] }}</span>
                            @endif
                        </div>
                        @if (filled($case['reason']))
                            <p class="text-sm text-slate-800 dark:text-slate-100">{{ $case['reason'] }}</p>
                        @endif
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ $case['opened_at'] }} · {{ $case['doctor'] }} ·
                            {{ $case['consultations'] === 1 ? '1 consulta' : $case['consultations'].' consultas' }}
                        </p>
                    </div>

                    @unless ($case['is_current'])
                        <x-filament::button
                            size="sm"
                            color="gray"
                            icon="heroicon-m-eye"
                            wire:click="openPreviousCase({{ $case['id'] }})"
                            wire:loading.attr="disabled"
                            wire:target="openPreviousCase({{ $case['id'] }})"
                            class="shrink-0"
                        >
                            Ver caso
                        </x-filament::button>
                    @endunless
                </li>
            @endforeach
        </ul>
    @endif
</div>
