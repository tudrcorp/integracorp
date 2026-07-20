@props([
    'colaborador' => null,
    'size' => 'md',
])

@php
    $sizeClass = match ($size) {
        'sm' => 'size-8 text-[10px]',
        'lg' => 'size-12 text-sm',
        default => 'size-10 text-[11px]',
    };
@endphp

@if (is_array($colaborador))
    @if (! empty($colaborador['avatar_url']))
        <img
            src="{{ $colaborador['avatar_url'] }}"
            alt="{{ $colaborador['name'] ?? 'Colaborador' }}"
            loading="lazy"
            decoding="async"
            class="{{ $sizeClass }} shrink-0 rounded-full border border-slate-200/80 object-cover dark:border-white/15"
        >
    @else
        <span class="{{ $sizeClass }} inline-flex shrink-0 items-center justify-center rounded-full border border-slate-200/80 bg-slate-100 font-semibold text-slate-700 dark:border-white/15 dark:bg-slate-800 dark:text-slate-100">
            {{ $colaborador['initials'] ?? '--' }}
        </span>
    @endif
@endif
