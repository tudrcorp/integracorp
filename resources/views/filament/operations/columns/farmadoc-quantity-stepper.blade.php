@php
    $record = $getRecord();
    $productId = (int) (is_array($record) ? ($record['product_id'] ?? 0) : 0);
    $quantity = (int) (is_array($record) ? ($record['order_quantity'] ?? 0) : 0);
    $maximum = \App\Support\Farmadoc\FarmadocOrderDraft::MAX_QUANTITY;
    $medicationName = is_array($record) ? (string) ($record['name'] ?? 'el medicamento') : 'el medicamento';
@endphp

{{--
    Respuesta inmediata: la cantidad cambia en pantalla al instante y se confirma con el
    servidor después. Siempre se envía la cantidad final (nunca «sumar 1»), un envío a la
    vez y en orden; al terminar manda lo que el servidor guardó, así un rechazo (tope de
    medicamentos) o un fallo de red no dejan la fila mostrando algo que no está en el pedido.
--}}
<div
    wire:key="farmadoc-stepper-{{ $productId }}"
    x-data="{
        productId: {{ $productId }},
        max: {{ $maximum }},
        pending: null,
        inFlight: false,
        timer: null,
        get serverQty() {
            const line = ($wire.orderLines || []).find((line) => Number(line.product_id) === this.productId);

            return line ? Number(line.quantity) : 0;
        },
        get qty() {
            return this.pending ?? this.serverQty;
        },
        set(value) {
            let next = parseInt(value, 10);

            if (Number.isNaN(next)) {
                next = this.qty;
            }

            next = Math.max(0, Math.min(this.max, next));

            if (next === this.qty) {
                return;
            }

            this.pending = next;
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.flush(), 250);
        },
        flush() {
            if (this.inFlight || this.pending === null) {
                return;
            }

            const value = this.pending;
            this.inFlight = true;

            $wire.changeLineQuantity(this.productId, value)
                .catch(() => {})
                .finally(() => {
                    this.inFlight = false;

                    if (this.pending !== null && this.pending !== value) {
                        this.flush();

                        return;
                    }

                    this.pending = null;
                });
        },
    }"
    x-effect="$el.closest('tr')?.classList.toggle('fi-farmadoc-row-in-order', qty > 0)"
    class="fi-farmadoc-stepper-cell"
    x-bind:class="{ 'is-syncing': pending !== null }"
>
    <button
        type="button"
        x-show="qty === 0"
        @style(['display: none' => $quantity > 0])
        x-on:click="set(1)"
        aria-label="Agregar {{ $medicationName }} al pedido"
        class="fi-farmadoc-add-button rounded-full"
    >
        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="fi-farmadoc-add-icon">
            <path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z" />
        </svg>
        Agregar
    </button>

    <div
        x-show="qty > 0"
        @style(['display: none' => $quantity === 0])
        class="fi-farmadoc-stepper rounded-full"
        role="group"
        aria-label="Cantidad de {{ $medicationName }}"
    >
        <button
            type="button"
            x-on:click="set(qty - 1)"
            x-bind:aria-label="qty <= 1 ? 'Quitar del pedido' : 'Disminuir cantidad'"
            aria-label="Disminuir cantidad"
            class="fi-farmadoc-stepper-button rounded-full"
        >
            −
        </button>
        <input
            type="number"
            min="0"
            max="{{ $maximum }}"
            inputmode="numeric"
            value="{{ $quantity }}"
            x-bind:value="qty"
            x-on:focus="$event.target.select()"
            x-on:change="set($event.target.value); $event.target.value = qty"
            x-on:keydown.enter.prevent="$event.target.blur()"
            aria-label="Cantidad de {{ $medicationName }}"
            class="fi-farmadoc-stepper-input"
        >
        <button
            type="button"
            x-on:click="set(qty + 1)"
            x-bind:disabled="qty >= max"
            @disabled($quantity >= $maximum)
            aria-label="Aumentar cantidad"
            class="fi-farmadoc-stepper-button rounded-full"
        >
            +
        </button>
    </div>
</div>
