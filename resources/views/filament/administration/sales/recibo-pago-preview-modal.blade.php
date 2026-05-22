@props([
    'sale' => null,
])

@php
    $regenerateAsyncUrl = $sale ? route('administration.sales.recibo-pago.regenerate-async', $sale) : '';
    $reciboPagoConfig = \Illuminate\Support\Js::from([
        'regenerateUrl' => $regenerateAsyncUrl,
        'desdeDefault' => '',
        'hastaDefault' => '',
    ]);
@endphp

@if (! $sale)
    <p class="text-sm text-gray-500 dark:text-gray-400">No hay venta asociada.</p>
@else
    <div
        wire:ignore
        class="fi-scoped space-y-4"
        x-data="window.reciboPagoPanel({{ $reciboPagoConfig }})"
    >
        <article
            class="overflow-hidden rounded-3xl border border-gray-200/80 bg-white/80 shadow-sm backdrop-blur-md dark:border-white/10 dark:bg-gray-900/70"
        >
            <div class="border-b border-gray-200/80 px-4 py-3 dark:border-white/10">
                <p class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">
                    Periodo de vigencia
                </p>
                <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                    Recibo de pago · {{ $sale->invoice_number }}
                </p>
            </div>
            <div class="grid gap-4 bg-gray-50/80 p-4 dark:bg-gray-950/60 sm:grid-cols-2">
                <div>
                    <label
                        class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400"
                        for="sales-recibo-desde-{{ $sale->getKey() }}"
                    >
                        Desde
                    </label>
                    <input
                        id="sales-recibo-desde-{{ $sale->getKey() }}"
                        type="date"
                        x-model="desde"
                        class="w-full rounded-2xl border border-gray-200/90 bg-white/95 px-4 py-2.5 text-sm text-gray-950 shadow-[inset_0_1px_2px_rgba(0,0,0,0.06)] outline-none ring-1 ring-gray-950/5 focus:border-primary-400 focus:ring-2 focus:ring-primary-500/30 dark:border-white/10 dark:bg-gray-900/80 dark:text-white dark:focus:border-primary-500"
                    />
                </div>
                <div>
                    <label
                        class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400"
                        for="sales-recibo-hasta-{{ $sale->getKey() }}"
                    >
                        Hasta
                    </label>
                    <input
                        id="sales-recibo-hasta-{{ $sale->getKey() }}"
                        type="date"
                        x-model="hasta"
                        class="w-full rounded-2xl border border-gray-200/90 bg-white/95 px-4 py-2.5 text-sm text-gray-950 shadow-[inset_0_1px_2px_rgba(0,0,0,0.06)] outline-none ring-1 ring-gray-950/5 focus:border-primary-400 focus:ring-2 focus:ring-primary-500/30 dark:border-white/10 dark:bg-gray-900/80 dark:text-white dark:focus:border-primary-500"
                    />
                </div>
            </div>
            <div class="border-t border-gray-200/80 px-4 py-3 dark:border-white/10">
                <button
                    type="button"
                    @click="regenerate()"
                    :disabled="loading"
                    class="aviso-btn-ios-primary inline-flex w-full items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98] disabled:opacity-60 sm:w-auto"
                >
                    <span x-show="! loading">Generar vista previa</span>
                    <span x-show="loading" x-cloak>Generando PDF…</span>
                </button>
            </div>
        </article>

        <p x-show="error" x-cloak class="text-sm text-danger-600 dark:text-danger-400" x-text="error"></p>

        <div x-show="regenerated" x-cloak x-transition class="space-y-4">
            <article
                class="overflow-hidden rounded-3xl border border-gray-200/80 bg-white/80 shadow-sm backdrop-blur-md dark:border-white/10 dark:bg-gray-900/70"
            >
                <div
                    class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200/80 px-4 py-3 dark:border-white/10"
                >
                    <div>
                        <p class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">
                            Venta
                        </p>
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                            Vista previa del recibo de pago
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span
                            class="rounded-full bg-sky-100 px-2.5 py-1 text-xs font-medium text-sky-700 dark:bg-sky-500/20 dark:text-sky-300"
                        >
                            PDF
                        </span>
                        <span
                            class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300"
                        >
                            Listo
                        </span>
                    </div>
                </div>

                <div class="bg-gray-50/80 p-3 dark:bg-gray-950/60">
                    <iframe
                        x-bind:src="previewUrl"
                        title="Vista previa recibo de pago"
                        class="h-[min(72vh,800px)] w-full rounded-2xl border-0 bg-white dark:bg-gray-900"
                        loading="lazy"
                    ></iframe>
                </div>

                <div class="border-t border-gray-200/80 px-4 py-3 dark:border-white/10">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Use la barra del visor PDF para ampliar. Puede descargar el archivo desde el menú de acciones de la tabla.
                    </p>
                </div>
            </article>
        </div>
    </div>
@endif
