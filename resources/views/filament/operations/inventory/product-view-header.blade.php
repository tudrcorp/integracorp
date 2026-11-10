@php
    /**
     * Encabezado de la ficha de un producto del Inventario Diagnomóvil.
     * Mismo lenguaje visual que `filament.operations.partials.list-header`.
     *
     * @var \App\Models\OperationInventoryProduct $product
     * @var int $totalExistence
     * @var string $stockStatus
     * @var float|null $profitPercentage
     * @var float|null $finalPrice
     * @var string|null $missingPriceReason
     */
    use App\Enums\OperationInventoryProductPresentation;
    use App\Support\Operations\OperationInventoryProductStockStatus;
    use Illuminate\Support\Number;

    $tones = [
        'success' => 'bg-emerald-500/10 text-emerald-700 ring-emerald-600/20 dark:text-emerald-300 dark:ring-emerald-400/30',
        'warning' => 'bg-amber-500/10 text-amber-700 ring-amber-600/25 dark:text-amber-300 dark:ring-amber-400/30',
        'danger' => 'bg-rose-500/10 text-rose-700 ring-rose-600/25 dark:text-rose-300 dark:ring-rose-400/30',
        'info' => 'bg-sky-500/10 text-sky-700 ring-sky-600/25 dark:text-sky-300 dark:ring-sky-400/30',
        'gray' => 'bg-gray-500/10 text-gray-600 ring-gray-500/20 dark:text-gray-300 dark:ring-white/15',
    ];
    $chip = 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1';
    $money = static fn (?float $value): string => $value === null ? 'Sin definir' : Number::currency($value, 'USD');
    $presentation = filled($product->presentation) ? OperationInventoryProductPresentation::labelFromMixed($product->presentation) : null;
    $packaging = collect([$product->unit, $presentation])
        ->filter(fn (mixed $value): bool => filled($value))
        ->unique(fn (string $value): string => mb_strtolower(trim($value)))
        ->implode(' · ');
@endphp

<div class="flex min-w-0 items-start gap-4 py-1">
    <span
        class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-[#4aa3e3] to-[#1f6fae] text-white shadow-md shadow-[#2d89ca]/25"
        aria-hidden="true"
    >
        <x-filament::icon icon="heroicon-o-cube" class="size-7" />
    </span>

    <div class="min-w-0 space-y-2">
        <span class="text-xs font-semibold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">Inventario Diagnomóvil · Producto</span>

        <p class="text-xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-2xl">{{ $product->name }}</p>

        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm font-normal text-gray-600 dark:text-gray-300">
            @if (filled($product->code))
                <span x-data="{ copied: false }" class="inline-flex items-center gap-1.5">
                    <span class="text-gray-500 dark:text-gray-400">Código</span>
                    <span class="font-mono font-semibold text-gray-900 dark:text-white">{{ $product->code }}</span>
                    <button
                        type="button"
                        x-on:click="navigator.clipboard?.writeText(@js((string) $product->code)); copied = true; setTimeout(() => copied = false, 1500)"
                        class="inline-flex items-center rounded-md p-0.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-white/10 dark:hover:text-gray-200"
                        x-bind:title="copied ? 'Copiado' : 'Copiar código'"
                        aria-label="Copiar código"
                    >
                        <x-filament::icon x-show="! copied" icon="heroicon-o-clipboard-document" class="size-4" />
                        <x-filament::icon x-show="copied" x-cloak icon="heroicon-s-check" class="size-4 text-emerald-600" />
                    </button>
                </span>
            @endif
            <span class="inline-flex items-center gap-1">
                <x-filament::icon icon="heroicon-o-tag" class="size-4 text-gray-400" />
                {{ $product->category?->name ?? 'Sin categoría' }}
            </span>
            @if ($packaging !== '')
                <span class="inline-flex items-center gap-1">
                    <x-filament::icon icon="heroicon-o-archive-box" class="size-4 text-gray-400" />
                    {{ $packaging }}
                </span>
            @endif
        </div>

        <ul class="flex flex-wrap items-center gap-2" aria-label="Resumen del producto">
            <li class="{{ $chip }} {{ $product->is_active ? $tones['success'] : $tones['gray'] }}">
                <x-filament::icon :icon="$product->is_active ? 'heroicon-m-check-circle' : 'heroicon-m-pause-circle'" class="size-3.5" />
                {{ $product->is_active ? 'Activo' : 'Inactivo' }}
            </li>
            <li
                class="{{ $chip }} {{ $tones[OperationInventoryProductStockStatus::color($stockStatus)] }}"
                title="Existencia total en todos los almacenes"
            >
                <x-filament::icon :icon="OperationInventoryProductStockStatus::icon($stockStatus)" class="size-3.5" />
                {{ OperationInventoryProductStockStatus::label($stockStatus) }}
                <span class="tabular-nums">{{ number_format($totalExistence, 0, ',', '.') }} und.</span>
            </li>
        </ul>

        {{-- Precio: costo → utilidad de la categoría → precio final. --}}
        <div class="flex flex-wrap items-stretch gap-2 pt-1" aria-label="Precio de venta">
            <div class="rounded-xl bg-white px-3 py-1.5 shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                <div class="text-[0.7rem] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Costo</div>
                <div class="text-base font-bold tabular-nums text-gray-950 dark:text-white">{{ $money(is_numeric($product->cost) ? (float) $product->cost : null) }}</div>
            </div>
            <span class="flex items-center text-gray-400" aria-hidden="true">
                <x-filament::icon icon="heroicon-m-plus" class="size-4" />
            </span>
            <div class="rounded-xl bg-white px-3 py-1.5 shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                <div class="text-[0.7rem] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Utilidad</div>
                <div class="text-base font-bold tabular-nums text-gray-950 dark:text-white">
                    {{ $profitPercentage === null ? 'Sin definir' : number_format($profitPercentage, 2, ',', '.').' %' }}
                </div>
            </div>
            <span class="flex items-center text-gray-400" aria-hidden="true">
                <x-filament::icon icon="heroicon-m-arrow-right" class="size-4" />
            </span>
            <div @class([
                'rounded-xl px-3 py-1.5 shadow-sm ring-1',
                'bg-emerald-50 ring-emerald-600/20 dark:bg-emerald-500/10 dark:ring-emerald-400/30' => $finalPrice !== null,
                'bg-amber-50 ring-amber-600/20 dark:bg-amber-500/10 dark:ring-amber-400/30' => $finalPrice === null,
            ])>
                <div class="text-[0.7rem] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Precio final</div>
                <div @class([
                    'text-base font-bold tabular-nums',
                    'text-emerald-700 dark:text-emerald-300' => $finalPrice !== null,
                    'text-amber-700 dark:text-amber-300' => $finalPrice === null,
                ])>{{ $money($finalPrice) }}</div>
            </div>
        </div>

        @if (filled($missingPriceReason))
            <p class="inline-flex items-center gap-1.5 text-xs font-medium text-amber-700 dark:text-amber-300">
                <x-filament::icon icon="heroicon-m-information-circle" class="size-4" />
                {{ $missingPriceReason }}
            </p>
        @endif
    </div>
</div>
