@props([
    'avatars' => [],
    'overflowCount' => 0,
    'tooltipTitle' => null,
    'tooltipItems' => [],
    'align' => 'end',
])

@php
    $overflowCount = (int) $overflowCount;
    $showTooltip = filled($tooltipTitle) && $tooltipItems !== [];
@endphp

@if ($avatars !== [] || $overflowCount > 0)
    <div
        {{ $attributes->class([
            'group/tdg-avatars relative w-fit max-w-full',
            'ml-auto' => $align === 'end',
        ]) }}
    >
        <div class="flex items-center justify-end -space-x-2">
            @foreach ($avatars as $avatar)
                @if ($avatar['avatar_url'] ?? null)
                    <img
                        src="{{ $avatar['avatar_url'] }}"
                        alt="{{ $avatar['name'] ?? 'Colaborador' }}"
                        class="tdg-calendar-avatar-stack__item size-6 rounded-full border-2 border-white object-cover dark:border-slate-900"
                    >
                @else
                    <span class="tdg-calendar-avatar-stack__item inline-flex size-6 items-center justify-center rounded-full border-2 border-white bg-slate-200 text-[10px] font-semibold text-slate-700 dark:border-slate-900 dark:bg-slate-700 dark:text-slate-100">
                        {{ $avatar['initials'] ?? 'NA' }}
                    </span>
                @endif
            @endforeach

            @if ($overflowCount > 0)
                <span class="tdg-calendar-avatar-stack__item tdg-calendar-avatar-stack__overflow inline-flex size-6 items-center justify-center rounded-full border-2 border-white bg-slate-600 text-[10px] font-bold text-white dark:border-slate-900 dark:bg-slate-500">
                    +{{ $overflowCount }}
                </span>
            @endif
        </div>

        @if ($showTooltip)
            <div class="tdg-calendar-avatar-stack__tooltip absolute bottom-full right-0 z-40 mb-2 hidden w-64 rounded-xl border border-slate-200/80 bg-white/95 p-2.5 text-left shadow-xl group-hover/tdg-avatars:block hover:block dark:border-white/10 dark:bg-slate-900/95">
                <p class="mb-2 shrink-0 text-[10px] font-semibold uppercase tracking-[0.1em] text-slate-500 dark:text-slate-400">
                    {{ $tooltipTitle }}
                </p>
                <ul class="tdg-calendar-avatar-stack__tooltip-list max-h-48 space-y-1.5 overflow-y-auto overscroll-contain pr-1">
                    @foreach ($tooltipItems as $item)
                        @php
                            $detail = $item['detail'] ?? $item['offices'] ?? null;
                        @endphp
                        <li class="text-[11px] leading-snug text-slate-700 dark:text-slate-200">
                            <span class="font-semibold text-slate-900 dark:text-slate-50">{{ $item['name'] }}</span>
                            @if (filled($detail))
                                <span class="text-slate-500 dark:text-slate-400"> — {{ $detail }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endif
