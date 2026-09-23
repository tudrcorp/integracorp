@php
    use App\Support\PlanGenerators\PlanGeneratorConditions;

    $conditions = PlanGeneratorConditions::normalize($conditions ?? '');
@endphp

@if ($conditions !== '')
    <div>
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            Condiciones
        </p>
        <div class="whitespace-pre-wrap rounded-xl border border-slate-200/80 bg-white px-4 py-3 text-sm leading-relaxed text-slate-800 shadow-sm dark:border-white/10 dark:bg-slate-950/40 dark:text-slate-100">{{ $conditions }}</div>
    </div>
@endif
