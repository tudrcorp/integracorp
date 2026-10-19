@php
    /**
     * Subtítulo del encabezado de la ficha del proveedor.
     *
     * Filament lo envuelve en un <p>: aquí solo van elementos en línea (span, a)
     * con estilos de bloque, para no romper el HTML.
     *
     * @var \App\Models\Supplier $supplier
     * @var array<string, int>|null $counts  Null si el usuario no ve órdenes de servicio.
     * @var array<string, array{label: string, description: string, icon: string, color: string}> $buckets
     * @var \Closure(string): string $bucketUrl
     */
    $tone = static fn (string $filamentColor): string => match ($filamentColor) {
        'success' => 'bg-emerald-50 text-emerald-800 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-400/30',
        'warning' => 'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-400/30',
        'danger' => 'bg-rose-50 text-rose-800 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-200 dark:ring-rose-400/30',
        'info' => 'bg-sky-50 text-sky-800 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-200 dark:ring-sky-400/30',
        'primary' => 'bg-indigo-50 text-indigo-800 ring-indigo-600/20 dark:bg-indigo-500/10 dark:text-indigo-200 dark:ring-indigo-400/30',
        default => 'bg-slate-100 text-slate-700 ring-slate-500/20 dark:bg-white/10 dark:text-slate-200 dark:ring-white/15',
    };
    $statusColor = static fn (?string $state): string => \App\Filament\Operations\Resources\Suppliers\Schemas\SupplierInfolist::statusBadgeColor($state);
    $location = collect([$supplier->city?->definition, $supplier->state?->definition])->filter()->implode(', ');
    $chip = 'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset';
@endphp

<span class="mt-1 flex flex-col gap-3">
    <span class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-600 dark:text-gray-300">
        @if (filled($supplier->razon_social) && $supplier->razon_social !== $supplier->name)
            <span class="font-medium">{{ $supplier->razon_social }}</span>
        @endif
        @if (filled($supplier->rif))
            <span
                x-data="{ copied: false }"
                class="inline-flex items-center gap-1.5"
            >
                <span class="text-gray-500 dark:text-gray-400">RIF</span>
                <span class="font-mono font-semibold text-gray-900 dark:text-white">{{ $supplier->rif }}</span>
                <button
                    type="button"
                    x-on:click="navigator.clipboard?.writeText(@js($supplier->rif)); copied = true; setTimeout(() => copied = false, 1500)"
                    class="inline-flex items-center rounded-md p-0.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-white/10 dark:hover:text-gray-200"
                    x-bind:title="copied ? 'Copiado' : 'Copiar RIF'"
                    aria-label="Copiar RIF"
                >
                    <x-filament::icon x-show="! copied" icon="heroicon-o-clipboard-document" class="h-4 w-4" />
                    <x-filament::icon x-show="copied" x-cloak icon="heroicon-s-check" class="h-4 w-4 text-emerald-600" />
                </button>
            </span>
        @endif
        @if ($location !== '')
            <span class="inline-flex items-center gap-1">
                <x-filament::icon icon="heroicon-o-map-pin" class="h-4 w-4 text-gray-400" />
                {{ $location }}
            </span>
        @endif
    </span>

    <span class="flex flex-wrap items-center gap-2">
        @if (filled($supplier->status_convenio))
            <span class="{{ $chip }} {{ $tone($statusColor($supplier->status_convenio)) }}">Convenio: {{ $supplier->status_convenio }}</span>
        @endif
        @if (filled($supplier->status_sistema))
            <span class="{{ $chip }} {{ $tone($statusColor($supplier->status_sistema)) }}">Sistema: {{ $supplier->status_sistema }}</span>
        @endif
        @if (filled($supplier->SupplierClasificacion?->description))
            <span class="{{ $chip }} {{ $tone('primary') }}">{{ $supplier->SupplierClasificacion->description }}</span>
        @endif
        @if (filled($supplier->tipo_clinica))
            <span class="{{ $chip }} {{ $tone('gray') }}">{{ $supplier->tipo_clinica }}</span>
        @endif
    </span>

    @if ($counts !== null)
        {{-- Contadores de órdenes: cada uno abre la pantalla de órdenes en su grupo. --}}
        <span class="flex flex-wrap items-stretch gap-2" aria-label="Órdenes de servicio del proveedor">
            @foreach ($buckets as $bucket => $definition)
                <a
                    href="{{ $bucketUrl($bucket) }}"
                    wire:navigate
                    title="{{ $definition['description'] }}"
                    class="group inline-flex items-center gap-2 rounded-xl bg-white py-1.5 pl-1.5 pr-3 shadow-sm ring-1 ring-gray-950/5 transition hover:-translate-y-px hover:shadow-md hover:ring-gray-950/10 dark:bg-white/5 dark:ring-white/10 dark:hover:ring-white/20"
                >
                    <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-md {{ $tone($counts[$bucket] > 0 ? $definition['color'] : 'gray') }} ring-1 ring-inset">
                        <x-filament::icon :icon="$definition['icon']" class="h-3.5 w-3.5" />
                    </span>
                    {{-- Número y rótulo en la misma línea: el contador ocupa una sola fila. --}}
                    <span class="inline-flex items-baseline gap-1.5 whitespace-nowrap leading-none">
                        <span class="text-base font-bold tabular-nums text-gray-950 dark:text-white">{{ $counts[$bucket] }}</span>
                        <span class="text-xs text-gray-500 group-hover:text-gray-700 dark:text-gray-400 dark:group-hover:text-gray-200">{{ $definition['label'] }}</span>
                    </span>
                </a>
            @endforeach
        </span>
    @endif
</span>
