@include('partials.head')

@php
$record = $getRecord();
@endphp

<div class="text-sm p-4">
    <div class="flex flex-col justify-right gap-1">
        <span class="fi-badge flex items-center justify-center gap-x-1 rounded-md text-xs font-medium ring-1 ring-inset px-2 min-w-[theme(spacing.6)] py-1 fi-color-custom bg-custom-50 text-custom-600 ring-custom-600/10 dark:bg-custom-400/10 dark:text-custom-400 dark:ring-custom-400/30 fi-color-success" style=" --c-50:var(--primary-50);--c-400:var(--primary-400);--c-600:var(--primary-600);">
            {{ $getNameCorporative() }} US$
        </span>
    </div>
</div>





