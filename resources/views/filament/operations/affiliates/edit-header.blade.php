@php
    /**
     * Encabezado de las páginas de edición de afiliados (individual y corporativo).
     *
     * @var string $name
     * @var string|null $status
     * @var array<string, string|null> $chips
     * @var string|null $eyebrow
     */
    $chips = array_filter($chips ?? [], fn ($value): bool => filled($value));
    $eyebrow ??= 'Editar datos del afiliado';
@endphp

<div style="display: flex; flex-direction: column; gap: 2px; padding: 12px 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
    <span class="text-sm font-bold uppercase tracking-tight text-gray-500 dark:text-gray-400" style="display: inline-flex; align-items: center; gap: 6px;">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" style="width: 16px; height: 16px;">
            <path d="m5.433 13.917 1.262-3.155A4 4 0 0 1 7.58 9.42l6.92-6.918a2.121 2.121 0 0 1 3 3l-6.92 6.918c-.383.383-.84.685-1.343.886l-3.154 1.262a.5.5 0 0 1-.65-.65Z" />
            <path d="M3.5 5.75c0-.69.56-1.25 1.25-1.25H10A.75.75 0 0 0 10 3H4.75A2.75 2.75 0 0 0 2 5.75v9.5A2.75 2.75 0 0 0 4.75 18h9.5A2.75 2.75 0 0 0 17 15.25V10a.75.75 0 0 0-1.5 0v5.25c0 .69-.56 1.25-1.25 1.25h-9.5c-.69 0-1.25-.56-1.25-1.25v-9.5Z" />
        </svg>
        {{ $eyebrow }}
    </span>

    <span class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white" style="margin-bottom: 6px;">
        {{ filled($name ?? null) ? $name : 'Sin nombre' }}
    </span>

    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 4px;">
        {!! \App\Support\Filament\AffiliateStatusHeaderBadge::html($status ?? null) !!}

        @foreach ($chips as $label => $value)
            <span class="text-gray-700 dark:text-gray-200" style="display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 999px; font-size: 0.8rem; font-weight: 600; background-color: rgba(142, 142, 147, 0.14); border: 1px solid rgba(142, 142, 147, 0.28);">
                <span style="opacity: 0.65; font-weight: 500;">{{ $label }}</span>
                {{ $value }}
            </span>
        @endforeach
    </div>

    <span class="text-sm text-gray-500 dark:text-gray-400" style="margin-top: 10px; font-weight: 400;">
        Solo datos personales. Al guardar, el equipo de Afiliaciones recibe el detalle de cada cambio.
    </span>
</div>
