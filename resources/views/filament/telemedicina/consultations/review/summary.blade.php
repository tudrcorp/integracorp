@php
    /** @var array{kind: string, tone: string, is_discharge: bool, sections: list<array{title: string, step: string, fields: array<string, string>, lists: list<array{title: string, tone: string, items: list<string>}>, empty: string|null}>, warnings: list<string>, notes: list<string>, amd: array<string, mixed>|null} $review */
    $kindClasses = [
        'initial' => 'bg-sky-600',
        'follow_up' => 'bg-amber-500',
        'discharge' => 'bg-emerald-600',
    ];
    $listClasses = [
        'covered' => 'bg-sky-50 text-sky-800 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-200',
        'not_covered' => 'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-200',
        'neutral' => 'bg-slate-100 text-slate-800 ring-slate-600/10 dark:bg-white/10 dark:text-slate-100',
    ];
@endphp

<div class="space-y-3">
    <div class="flex flex-wrap items-center gap-2">
        <span class="rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide text-white {{ $kindClasses[$review['tone']] ?? 'bg-slate-600' }}">{{ $review['kind'] }}</span>
        <span class="text-sm text-slate-500 dark:text-slate-400">Así quedará registrada la consulta.</span>
    </div>

    @if ($review['warnings'] !== [])
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-500/30 dark:bg-amber-500/10">
            <p class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-amber-800 dark:text-amber-300">
                <x-filament::icon icon="heroicon-s-exclamation-triangle" class="size-4" />
                Revise antes de registrar
            </p>
            <ul class="mt-1.5 space-y-0.5 text-sm text-amber-950 dark:text-amber-100">
                @foreach ($review['warnings'] as $warning)
                    <li>• {{ $warning }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (($review['amd'] ?? null) !== null)
        @php($amd = $review['amd'])
        {{-- Solo avisa: la consulta se puede registrar aunque falte algo de la AMD. --}}
        <div @class([
            'rounded-xl border px-4 py-3',
            'border-emerald-200 bg-emerald-50 dark:border-emerald-500/30 dark:bg-emerald-500/10' => $amd['complete'],
            'border-amber-200 bg-amber-50 dark:border-amber-500/30 dark:bg-amber-500/10' => ! $amd['complete'],
        ])>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p @class([
                    'flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide',
                    'text-emerald-800 dark:text-emerald-300' => $amd['complete'],
                    'text-amber-800 dark:text-amber-300' => ! $amd['complete'],
                ])>
                    <x-filament::icon :icon="$amd['complete'] ? 'heroicon-s-check-badge' : 'heroicon-s-exclamation-triangle'" class="size-4" />
                    Acciones AMD · {{ $amd['completed'] }} de {{ $amd['total'] }} obligatorias
                </p>
            </div>
            <ul class="mt-2 grid gap-1.5 sm:grid-cols-3">
                @foreach ($amd['steps'] as $step)
                    <li class="flex items-start gap-1.5 text-sm text-slate-800 dark:text-slate-100">
                        <x-filament::icon
                            :icon="$step['done'] ? 'heroicon-s-check-circle' : ($step['optional'] ? 'heroicon-o-minus-circle' : 'heroicon-o-clock')"
                            @class([
                                'mt-0.5 size-4 shrink-0',
                                'text-emerald-600 dark:text-emerald-400' => $step['done'],
                                'text-amber-500' => ! $step['done'] && ! $step['optional'],
                                'text-slate-400' => ! $step['done'] && $step['optional'],
                            ])
                        />
                        <span><strong class="font-semibold">{{ $step['label'] }}:</strong> {{ $step['detail'] }}</span>
                    </li>
                @endforeach
            </ul>
            <p class="mt-2 text-sm text-slate-700 dark:text-slate-200">
                @if ($amd['complete'])
                    {{ $amd['message'] }}
                @else
                    {{ $amd['message'] }} Puede registrar la consulta igual, pero la visita quedará incompleta: complételo desde la tarjeta «Visita AMD».
                @endif
            </p>
        </div>
    @endif

    @foreach ($review['notes'] as $note)
        <p class="flex items-start gap-2 rounded-xl bg-slate-50 px-4 py-2.5 text-sm text-slate-700 dark:bg-white/5 dark:text-slate-200">
            <x-filament::icon icon="heroicon-o-information-circle" class="mt-0.5 size-4 shrink-0 text-slate-400" />
            {{ $note }}
        </p>
    @endforeach

    <div class="grid gap-3 lg:grid-cols-2">
        @foreach ($review['sections'] as $section)
            <section class="rounded-xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                <header class="flex items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ $section['title'] }}</h3>
                    <button
                        type="button"
                        x-on:click="step = getSteps().find((key) => String(key).endsWith(@js($section['step']))) ?? step; scroll()"
                        class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold text-primary-700 ring-1 ring-inset ring-primary-600/30 transition hover:bg-primary-50 dark:text-primary-300 dark:ring-primary-400/40 dark:hover:bg-primary-500/10"
                    >
                        <x-filament::icon icon="heroicon-m-pencil-square" class="size-3.5" />
                        Editar
                    </button>
                </header>

                @if ($section['fields'] !== [])
                    <dl class="mt-2 space-y-1.5">
                        @foreach ($section['fields'] as $label => $value)
                            <div>
                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $label }}</dt>
                                <dd class="whitespace-pre-line text-sm text-slate-800 dark:text-slate-100">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif

                @foreach ($section['lists'] as $list)
                    <p class="mt-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $list['title'] }}</p>
                    <ul class="mt-1 flex flex-wrap gap-1.5">
                        @foreach ($list['items'] as $item)
                            <li class="rounded-md px-2 py-0.5 text-xs ring-1 ring-inset {{ $listClasses[$list['tone']] ?? $listClasses['neutral'] }}">{{ $item }}</li>
                        @endforeach
                    </ul>
                @endforeach

                @if ($section['fields'] === [] && $section['lists'] === [])
                    <p class="mt-2 text-sm text-slate-400">{{ $section['empty'] ?? 'Sin datos cargados.' }}</p>
                @endif
            </section>
        @endforeach
    </div>
</div>
