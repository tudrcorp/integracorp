@php
    /** @var string $src */
    /** @var string $class */
    $eager = (bool) ($eager ?? false);
    $priority = (bool) ($priority ?? false);
    $width = (int) ($width ?? 900);
    $height = (int) ($height ?? 600);
    $alt = (string) ($alt ?? '');
    $webp = \App\Support\Storefront\StorefrontPlanNarrative::coverWebp($src);
@endphp
<picture>
    @if ($webp !== null)
        <source srcset="{{ asset($webp) }}" type="image/webp">
    @endif
    <img
        class="{{ $class }}"
        src="{{ asset($src) }}"
        alt="{{ $alt }}"
        width="{{ $width }}"
        height="{{ $height }}"
        decoding="async"
        @if ($priority) fetchpriority="high" @endif
        @if ($eager) loading="eager" @else loading="lazy" @endif
    >
</picture>
