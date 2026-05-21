@props([
    'platform' => '',
    'size' => 'md',
])

@php
    $sizeClasses = match ($size) {
        'sm' => 'size-5',
        'lg' => 'size-7',
        default => 'size-5',
    };

    $iconSizeClasses = match ($size) {
        'sm' => 'size-2.5',
        'lg' => 'size-4',
        default => 'size-3',
    };

    $meta = \App\Support\CorporateAgendaSocialPlatformCatalog::for((string) $platform);
@endphp

<span
    {{ $attributes->class([
        'inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full border border-white/70 ring-1 ring-inset transition-transform duration-200 hover:scale-105 dark:border-slate-900/70',
        $sizeClasses,
        $meta['calendar_icon_class'],
        $meta['icon_ring_class'],
    ]) }}
    title="{{ $meta['label'] }}"
    aria-label="{{ $meta['label'] }}"
>
    @php
        $imagePath = match ((string) $platform) {
            'instagram' => 'image/instagram.png',
            'youtube' => 'image/youtube.png',
            'x' => 'image/twitter.png',
            'facebook' => 'image/communication.png',
            default => null,
        };
    @endphp

    @if ($imagePath !== null)
        <img
            src="{{ asset($imagePath) }}"
            alt="{{ $meta['label'] }}"
            class="h-full w-full object-cover"
            loading="lazy"
        >
    @else
        @switch($platform)
            @case('facebook')
                <svg class="{{ $iconSizeClasses }}" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M24 12.07C24 5.41 18.63 0 12 0S0 5.4 0 12.07c0 6.02 4.39 11.02 10.13 11.91v-8.4H7.08v-3.5h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.68.24 2.68.24v2.97h-1.51c-1.49 0-1.95.93-1.95 1.88v2.26h3.32l-.53 3.5h-2.79v8.4C19.61 23.09 24 18.09 24 12.07Z"/>
                </svg>
                @break
            @default
                <svg class="{{ $iconSizeClasses }}" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Z"/>
                </svg>
        @endswitch
    @endif
</span>
