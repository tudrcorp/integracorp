@php
    $record = $getRecord();
    $productId = (int) (is_array($record) ? ($record['product_id'] ?? 0) : 0);
    $quantity = (int) (is_array($record) ? ($record['order_quantity'] ?? 0) : 0);
    $maximum = \App\Support\Farmadoc\FarmadocOrderDraft::MAX_QUANTITY;
@endphp

<div class="inline-flex h-10 items-center rounded-full border border-gray-300 bg-white px-1 text-gray-950 shadow-sm dark:border-white/15 dark:bg-gray-900 dark:text-white">
    <button
        type="button"
        wire:click="changeLineQuantity({{ $productId }}, {{ $quantity - 1 }})"
        wire:loading.attr="disabled"
        @disabled($quantity < 1)
        aria-label="Disminuir cantidad"
        class="flex h-8 w-8 items-center justify-center rounded-full text-lg leading-none transition hover:bg-gray-100 disabled:cursor-default disabled:opacity-30 dark:hover:bg-white/10"
    >
        −
    </button>
    <span class="min-w-6 px-1 text-center text-sm font-semibold tabular-nums" aria-live="polite">{{ $quantity }}</span>
    <button
        type="button"
        wire:click="changeLineQuantity({{ $productId }}, {{ $quantity + 1 }})"
        wire:loading.attr="disabled"
        @disabled($quantity >= $maximum)
        aria-label="Aumentar cantidad"
        class="flex h-8 w-8 items-center justify-center rounded-full text-lg leading-none transition hover:bg-gray-100 disabled:cursor-default disabled:opacity-30 dark:hover:bg-white/10"
    >
        +
    </button>
</div>
