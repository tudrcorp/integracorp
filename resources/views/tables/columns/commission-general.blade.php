@include('partials.head')

@php
$record = $getRecord();
@endphp

<div class="text-sm p-4">
    <div class="flex flex-col justify-right gap-1">
        <span class="fi-color fi-color-primary fi-text-color-700 dark:fi-text-color-300 fi-badge flex items-center justify-center gap-x-1 rounded-md text-xs font-medium ring-1 ring-inset px-2 min-w-[theme(spacing.6)] py-1 fi-color-custom bg-custom-50 text-custom-600 ring-custom-600/10 dark:bg-custom-400/10 dark:text-custom-400 dark:ring-custom-400/30 fi-color-azulOscuro" style=" --c-50:var(--primary-50);--c-400:var(--primary-400);--c-600:var(--primary-600);">
            General: {{ $getNameCorporative() }}
        </span>
        <span class="fi-color fi-color-primary fi-text-color-700 dark:fi-text-color-300 fi-badge flex items-center justify-center gap-x-1 rounded-md text-xs font-medium ring-1 ring-inset px-2 min-w-[theme(spacing.6)] py-1 fi-color-custom bg-custom-50 text-custom-600 ring-custom-600/10 dark:bg-custom-400/10 dark:text-custom-400 dark:ring-custom-400/30 fi-color-azulOscuro" style=" --c-50:var(--primary-50);--c-400:var(--primary-400);--c-600:var(--primary-600);">

            Asigando: {{ number_format($record['commission_agency_general_tdec'], 0, ',', '.') }}%
        </span>
        <span class="fi-color fi-color-primary fi-text-color-700 dark:fi-text-color-300 fi-badge flex items-center justify-center gap-x-1 rounded-md text-xs font-medium ring-1 ring-inset px-2 min-w-[theme(spacing.6)] py-1 fi-color-custom bg-custom-50 text-custom-600 ring-custom-600/10 dark:bg-custom-400/10 dark:text-custom-400 dark:ring-custom-400/30 fi-color-azulOscuro" style=" --c-50:var(--primary-50);--c-400:var(--primary-400);--c-600:var(--primary-600);">
            Costo: {{ number_format($record['commission_agency_general'], 0, ',', '.') }} US$
        </span>
    </div>
</div>



