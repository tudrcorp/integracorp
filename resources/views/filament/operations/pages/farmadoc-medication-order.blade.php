<x-filament-panels::page>
    @if ($reviewingOrder)
        @php
            $medicationCount = count($orderLines);
            $unitCount = (int) collect($orderLines)->sum(fn (array $line): int => (int) ($line['quantity'] ?? 0));
            $maximumQuantity = \App\Support\Farmadoc\FarmadocOrderDraft::MAX_QUANTITY;
        @endphp
        <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-white/10">
                <div>
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Pedido por validar</h2>
                    @if ($medicationCount > 0)
                        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                            {{ $medicationCount }} {{ $medicationCount === 1 ? 'medicamento' : 'medicamentos' }}
                            · {{ $unitCount }} {{ $unitCount === 1 ? 'unidad' : 'unidades' }}
                        </p>
                    @endif
                </div>
                <p class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600 dark:bg-white/10 dark:text-gray-300">
                    Hasta {{ \App\Support\Farmadoc\FarmadocOrderDraft::MAX_LINES }} medicamentos
                </p>
            </header>

            @if ($orderLines === [])
                <div class="px-5 py-10 text-center">
                    <p class="text-sm font-medium text-gray-950 dark:text-white">El pedido quedó vacío</p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Vuelva al catálogo para elegir medicamentos.</p>
                    <button
                        type="button"
                        wire:click="continueOrdering"
                        class="mt-4 inline-flex items-center rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-white/15 dark:text-gray-200 dark:hover:bg-white/5"
                    >
                        Volver al catálogo
                    </button>
                </div>
            @else
                <ul class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($orderLines as $line)
                        @php
                            $quantity = (int) $line['quantity'];
                            $productId = (int) $line['product_id'];
                        @endphp
                        <li wire:key="order-line-{{ $productId }}" class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-600 dark:bg-white/10 dark:text-gray-300">
                                {{ $loop->iteration }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-gray-950 dark:text-white">{{ $line['name'] }}</p>
                                @if (filled($line['presentation'] ?? null))
                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $line['presentation'] }}</p>
                                @endif
                            </div>
                            <div class="flex items-center gap-3 self-end sm:self-auto">
                                <div class="inline-flex h-10 items-center rounded-full border border-gray-300 bg-white px-1 text-gray-950 dark:border-white/15 dark:bg-gray-950 dark:text-white">
                                    <button
                                        type="button"
                                        wire:click="changeLineQuantity({{ $productId }}, {{ $quantity - 1 }})"
                                        wire:loading.attr="disabled"
                                        @disabled($quantity <= 1)
                                        aria-label="Disminuir cantidad"
                                        class="flex h-8 w-8 items-center justify-center rounded-full text-lg leading-none transition hover:bg-gray-100 disabled:cursor-default disabled:opacity-30 dark:hover:bg-white/10"
                                    >
                                        −
                                    </button>
                                    <input
                                        id="farmadoc-qty-{{ $productId }}"
                                        type="number"
                                        min="1"
                                        max="{{ $maximumQuantity }}"
                                        inputmode="numeric"
                                        value="{{ $quantity }}"
                                        aria-label="Cantidad de {{ $line['name'] }}"
                                        wire:change="setReviewedQuantity({{ $productId }}, $event.target.value)"
                                        class="w-12 border-0 bg-transparent p-0 text-center text-sm font-semibold tabular-nums focus:ring-0 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                    >
                                    <button
                                        type="button"
                                        wire:click="changeLineQuantity({{ $productId }}, {{ $quantity + 1 }})"
                                        wire:loading.attr="disabled"
                                        @disabled($quantity >= $maximumQuantity)
                                        aria-label="Aumentar cantidad"
                                        class="flex h-8 w-8 items-center justify-center rounded-full text-lg leading-none transition hover:bg-gray-100 disabled:cursor-default disabled:opacity-30 dark:hover:bg-white/10"
                                    >
                                        +
                                    </button>
                                </div>
                                <button
                                    type="button"
                                    wire:click="removeLine({{ $productId }})"
                                    wire:loading.attr="disabled"
                                    aria-label="Quitar {{ $line['name'] }}"
                                    class="rounded-full px-3 py-2 text-sm font-medium text-gray-500 transition hover:bg-rose-50 hover:text-rose-700 disabled:opacity-50 dark:text-gray-400 dark:hover:bg-rose-500/10 dark:hover:text-rose-300"
                                >
                                    Quitar
                                </button>
                            </div>
                        </li>
                    @endforeach
                </ul>
                <footer class="flex flex-col gap-3 border-t border-gray-100 bg-gray-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-white/10 dark:bg-white/5">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $medicationCount }} {{ $medicationCount === 1 ? 'medicamento' : 'medicamentos' }}
                        · {{ $unitCount }} {{ $unitCount === 1 ? 'unidad' : 'unidades' }}
                    </p>
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            wire:click="continueOrdering"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center rounded-full border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-white/15 dark:bg-transparent dark:text-gray-200 dark:hover:bg-white/5"
                        >
                            Seguir eligiendo
                        </button>
                        <button
                            type="button"
                            wire:click="mountAction('placeOrder')"
                            wire:loading.attr="disabled"
                            wire:target="mountAction"
                            class="inline-flex items-center rounded-full bg-amber-500 px-5 py-2 text-sm font-semibold text-white hover:bg-amber-600 disabled:opacity-50"
                        >
                            <span wire:loading.remove wire:target="mountAction">Enviar pedido</span>
                            <span wire:loading wire:target="mountAction">Preparando envío…</span>
                        </button>
                    </div>
                </footer>
            @endif
        </section>
    @else
        @if (filled($logoUrl))
            <div class="mb-4 flex items-center gap-3">
                <img src="{{ $logoUrl }}" alt="Farmadoc" class="h-32 w-auto max-w-full sm:h-40">
            </div>
        @endif

        @if (filled($catalogError))
            <div class="mb-4 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-800 ring-1 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-200 dark:ring-rose-500/30">
                {{ $catalogError }}
            </div>
        @endif

        @if ($placedOrders !== [])
            <section class="mb-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Pedidos enviados en esta visita</h2>
                <ul class="mt-2 space-y-1 text-sm text-gray-700 dark:text-gray-200">
                    @foreach ($placedOrders as $order)
                        <li wire:key="placed-{{ $order['order_number'] }}">
                            {{ $order['order_number'] }}
                            · {{ $order['items_count'] }} {{ $order['items_count'] === 1 ? 'medicamento' : 'medicamentos' }}
                            · {{ $order['status_label'] }}
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($orderLines !== [])
            <div class="sticky top-0 z-20 mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-amber-50 px-4 py-3 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:ring-amber-400/30">
                <p class="text-sm font-medium text-amber-950 dark:text-amber-100">
                    {{ count($orderLines) }} {{ count($orderLines) === 1 ? 'medicamento listo' : 'medicamentos listos' }} para validar
                </p>
                <button
                    type="button"
                    wire:click="reviewOrder"
                    wire:loading.attr="disabled"
                    wire:target="reviewOrder"
                    class="inline-flex items-center rounded-full bg-amber-500 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-600 disabled:opacity-50"
                >
                    Validar pedido
                </button>
            </div>
        @endif

        {{ $this->table }}
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
