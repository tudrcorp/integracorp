@php
    /** @var \App\Support\Telemedicine\TelemedicineAmdVisitProgress|null $progress */
    $progress = method_exists($this, 'amdVisitProgress') ? $this->amdVisitProgress() : null;
    $hasInform = $progress?->hasInform ?? false;
    $hasPhysicalExam = $progress?->hasExam ?? (method_exists($this, 'hasAmdPhysicalExam') && $this->hasAmdPhysicalExam());
    $files = $progress?->files ?? 0;
    $examReport = $progress?->examReport ?? (method_exists($this, 'amdPhysicalExamReportLabel') ? $this->amdPhysicalExamReportLabel() : null);
    $complete = $progress?->isComplete() ?? false;
    $completed = $progress?->completedRequired() ?? 0;
    $total = \App\Support\Telemedicine\TelemedicineAmdVisitProgress::REQUIRED_TOTAL;
    $percent = $progress?->percent() ?? 0;
    $steps = $progress?->steps() ?? [];

    // La siguiente acción pendiente va resaltada: el médico sabe qué pulsar.
    $nextAction = ! $hasInform ? 'inform' : (! $hasPhysicalExam ? 'exam' : null);

    $primaryButton = 'inline-flex min-h-[2.75rem] shrink-0 items-center justify-center gap-2 rounded-2xl bg-sky-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700 active:scale-[0.98] disabled:opacity-60 dark:bg-sky-500 dark:hover:bg-sky-400';
    $secondaryButton = 'inline-flex min-h-[2.75rem] shrink-0 items-center justify-center gap-2 rounded-2xl border border-sky-300 bg-white px-5 py-2.5 text-sm font-semibold text-sky-700 shadow-sm transition hover:bg-sky-50 active:scale-[0.98] disabled:opacity-60 dark:border-sky-500/40 dark:bg-sky-950/40 dark:text-sky-200 dark:hover:bg-sky-900/50';
@endphp
<div @class([
    'space-y-4 rounded-xl border p-4 transition-colors',
    'border-emerald-200/80 bg-emerald-50/70 dark:border-emerald-500/25 dark:bg-emerald-950/25' => $complete,
    'border-sky-200/80 bg-sky-50/80 dark:border-sky-500/20 dark:bg-sky-950/30' => ! $complete,
])>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0 space-y-1">
            <p class="text-sm font-semibold text-slate-900 dark:text-white">Visita AMD</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Registre el informe y el examen físico de la visita domiciliaria. Los archivos son opcionales.
            </p>
        </div>
        <span @class([
            'inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ring-1',
            'bg-emerald-100 text-emerald-800 ring-emerald-200 dark:bg-emerald-500/20 dark:text-emerald-200 dark:ring-emerald-500/30' => $complete,
            'bg-white text-sky-800 ring-sky-200 dark:bg-sky-950/40 dark:text-sky-200 dark:ring-sky-500/30' => ! $complete,
        ])>
            @if ($complete)
                <x-filament::icon icon="heroicon-s-check-circle" class="h-4 w-4" />
            @endif
            {{ $completed }} de {{ $total }} obligatorias
        </span>
    </div>

    <div
        class="h-2 w-full overflow-hidden rounded-full bg-white/80 ring-1 ring-slate-200 dark:bg-white/10 dark:ring-white/10"
        role="progressbar"
        aria-label="Avance de las acciones AMD"
        aria-valuemin="0"
        aria-valuemax="{{ $total }}"
        aria-valuenow="{{ $completed }}"
    >
        <div
            @class([
                'h-full rounded-full transition-all duration-500',
                'bg-emerald-500' => $complete,
                'bg-sky-500' => ! $complete,
            ])
            style="width: {{ $percent }}%"
        ></div>
    </div>

    @if ($steps !== [])
        <ol class="grid gap-2 sm:grid-cols-3">
            @foreach ($steps as $step)
                <li
                    wire:key="amd-step-{{ $step['key'] }}"
                    @class([
                        'flex items-start gap-2 rounded-lg px-3 py-2 ring-1',
                        'bg-white/80 ring-emerald-200 dark:bg-emerald-950/30 dark:ring-emerald-500/30' => $step['done'],
                        'bg-white/60 ring-slate-200 dark:bg-white/5 dark:ring-white/10' => ! $step['done'],
                    ])
                >
                    <x-filament::icon
                        :icon="$step['done'] ? 'heroicon-s-check-circle' : ($step['optional'] ? 'heroicon-o-minus-circle' : 'heroicon-o-clock')"
                        @class([
                            'mt-0.5 h-4 w-4 shrink-0',
                            'text-emerald-600 dark:text-emerald-400' => $step['done'],
                            'text-amber-500 dark:text-amber-400' => ! $step['done'] && ! $step['optional'],
                            'text-slate-400 dark:text-slate-500' => ! $step['done'] && $step['optional'],
                        ])
                    />
                    <span class="min-w-0">
                        <span class="block text-xs font-semibold text-slate-900 dark:text-white">{{ $step['label'] }}</span>
                        <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $step['detail'] }}</span>
                    </span>
                </li>
            @endforeach
        </ol>

        <p @class([
            'flex items-center gap-1.5 text-xs font-medium',
            'text-emerald-800 dark:text-emerald-200' => $complete,
            'text-amber-800 dark:text-amber-200' => ! $complete,
        ])>
            <x-filament::icon :icon="$complete ? 'heroicon-s-check-badge' : 'heroicon-o-arrow-right-circle'" class="h-4 w-4 shrink-0" />
            {{ $progress->nextStepMessage() }}
        </p>
    @endif

    <div class="flex flex-wrap items-center gap-2">
        <button
            type="button"
            wire:click="mountAction('informAmd')"
            wire:loading.attr="disabled"
            wire:target="mountAction('informAmd')"
            title="{{ $hasInform ? 'Informe AMD registrado. Ábralo para revisarlo o corregirlo.' : 'Registrar los datos clínicos de la visita AMD' }}"
            class="{{ $nextAction === 'inform' ? $primaryButton : $secondaryButton }}"
        >
            @if ($hasInform)
                <x-filament::icon icon="heroicon-s-check-circle" class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
            @else
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            @endif
            <span wire:loading.remove wire:target="mountAction('informAmd')">{{ $hasInform ? 'Informe AMD (registrado)' : 'Informar AMD' }}</span>
            <span wire:loading wire:target="mountAction('informAmd')">Abriendo…</span>
        </button>
        <button
            type="button"
            wire:click="mountAction('amdPhysicalExam')"
            wire:loading.attr="disabled"
            wire:target="mountAction('amdPhysicalExam')"
            title="{{ $hasPhysicalExam ? 'Examen físico cargado. Ábralo para revisarlo o editarlo.' : 'Cargar el examen físico de la visita. Se anexa al '.($examReport ?? 'informe de esta consulta').'.' }}"
            class="{{ $nextAction === 'exam' ? $primaryButton : $secondaryButton }}"
        >
            <x-filament::icon :icon="$hasPhysicalExam ? 'heroicon-s-check-circle' : 'heroicon-o-heart'" @class(['h-4 w-4', 'text-emerald-600 dark:text-emerald-400' => $hasPhysicalExam]) />
            <span wire:loading.remove wire:target="mountAction('amdPhysicalExam')">{{ $hasPhysicalExam ? 'Examen físico (cargado)' : 'Examen físico AMD' }}</span>
            <span wire:loading wire:target="mountAction('amdPhysicalExam')">Abriendo…</span>
        </button>
        <button
            type="button"
            wire:click="mountAction('uploadAmdFile')"
            wire:loading.attr="disabled"
            wire:target="mountAction('uploadAmdFile')"
            title="Adjuntar una imagen o documento de la visita (opcional)"
            class="{{ $secondaryButton }}"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
            </svg>
            <span wire:loading.remove wire:target="mountAction('uploadAmdFile')">{{ $files > 0 ? 'Cargar otro archivo AMD' : 'Cargar Archivo AMD' }}</span>
            <span wire:loading wire:target="mountAction('uploadAmdFile')">Abriendo…</span>
            @if ($files > 0)
                <span wire:loading.remove wire:target="mountAction('uploadAmdFile')" class="inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-sky-100 px-1.5 text-xs font-bold text-sky-800 dark:bg-sky-500/20 dark:text-sky-200">{{ $files }}</span>
            @endif
        </button>
    </div>
</div>
