@php
    $badge = 'inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[0.7rem] font-medium ring-1 ring-inset';
@endphp

<div class="flex min-w-0 flex-col gap-1.5">
    <span class="text-sm font-semibold leading-snug text-gray-950 dark:text-white">{{ $label }}</span>

    <span class="flex flex-wrap gap-1">
        @if (! $applies)
            <span class="{{ $badge }} bg-gray-50 text-gray-600 ring-gray-500/20 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">No aplica en consulta</span>
        @elseif ($missing === null)
            <span class="{{ $badge }} bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/30">✓ Listo</span>
        @else
            <span class="{{ $badge }} bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/30">● {{ $missing }}</span>
        @endif

        @if ($origin === \App\Support\ClinicalEntitlements\PlanClinicalUsageWorkbench::ORIGIN_LINK)
            <span class="{{ $badge }} bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-400/30">Se vincula al guardar</span>
        @elseif ($origin === \App\Support\ClinicalEntitlements\PlanClinicalUsageWorkbench::ORIGIN_CREATE)
            <span class="{{ $badge }} bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-400/30">Nuevo en catálogo · se crea al guardar</span>
        @endif

        @if ($copiedFrom !== null)
            <span class="{{ $badge }} bg-indigo-50 text-indigo-700 ring-indigo-600/20 dark:bg-indigo-500/10 dark:text-indigo-300 dark:ring-indigo-400/30">Copiado de {{ $copiedFrom }}</span>
        @endif
    </span>
</div>
