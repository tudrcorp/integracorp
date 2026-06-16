@php
    /** @var bool $isSelected */
    /** @var string $coverageLabel */
@endphp

<div class="flex flex-col items-center gap-1">
    @if ($isSelected)
        <span
            class="inline-flex size-5 items-center justify-center rounded-full bg-emerald-100 text-[10px] font-bold leading-none text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200"
            aria-label="Beneficio incluido">
            ✓
        </span>
        @if ($coverageLabel !== '')
            <span class="text-[11px] font-semibold text-slate-700 dark:text-slate-200">US$ {{ $coverageLabel }}</span>
        @endif
    @else
        <span
            class="inline-flex size-5 items-center justify-center rounded-full bg-rose-100 text-[11px] font-bold leading-none text-rose-700 dark:bg-rose-500/20 dark:text-rose-200"
            aria-label="No incluido">
            −
        </span>
    @endif
</div>
