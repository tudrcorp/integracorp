@include('partials.head')

@php
$record = $getRecord();
@endphp

<div class="text-sm p-4">
    <div class="flex flex-col justify-right gap-1">

        <span class="fi-badge flex items-center justify-center gap-x-1 rounded-md text-xs font-medium ring-1 ring-inset px-2 min-w-[theme(spacing.6)] py-1 fi-color-custom bg-custom-50 text-custom-600 ring-custom-600/10 dark:bg-custom-400/10 dark:text-custom-400 dark:ring-custom-400/30 fi-color-azulOscuro" style=" --c-50:var(--primary-50);--c-400:var(--primary-400);--c-600:var(--primary-600);">
            Metodo: {{ $record->payment_method }}
        </span>

        <span class="fi-badge flex items-center justify-center gap-x-1 rounded-md text-xs font-medium ring-1 ring-inset px-2 min-w-[theme(spacing.6)] py-1 fi-color-custom bg-custom-50 text-custom-600 ring-custom-600/10 dark:bg-custom-400/10 dark:text-custom-400 dark:ring-custom-400/30 fi-color-azulOscuro" style=" --c-50:var(--verde-50);--c-400:var(--verde-400);--c-600:var(--verde-600);">
            US$: {{ $record->payment_method_usd }}

        </span>
        <span class="fi-badge flex items-center justify-center gap-x-1 rounded-md text-xs font-medium ring-1 ring-inset px-2 min-w-[theme(spacing.6)] py-1 fi-color-custom bg-custom-50 text-custom-600 ring-custom-600/10 dark:bg-custom-400/10 dark:text-custom-400 dark:ring-custom-400/30 fi-color-azulOscuro" style=" --c-50:var(--verde-50);--c-400:var(--verde-400);--c-600:var(--verde-600);">
            VES: {{ $record->payment_method_ves }}
        </span>
    </div>
</div>

