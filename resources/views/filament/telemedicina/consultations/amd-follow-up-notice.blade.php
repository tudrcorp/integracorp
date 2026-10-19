@php
    $notice = (string) ($notice ?? '');
@endphp
<div
    role="note"
    class="flex items-start gap-3 rounded-xl border border-sky-200/80 bg-sky-50/80 p-4 dark:border-sky-500/20 dark:bg-sky-950/30"
>
    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-200">
        <x-filament::icon icon="heroicon-o-home-modern" class="h-5 w-5" />
    </span>
    <div class="min-w-0 space-y-1">
        <p class="text-sm font-semibold text-slate-900 dark:text-white">Seguimiento por visita AMD</p>
        <p class="text-xs leading-relaxed text-slate-600 dark:text-slate-300">{{ $notice }}</p>
        <p class="text-xs font-medium text-sky-800 dark:text-sky-200">
            Encontrará los botones AMD más abajo, junto al tipo de servicio.
        </p>
    </div>
</div>
