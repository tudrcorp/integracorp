@props([
    'sale' => null,
])

@php
    $previewUrl = $sale ? \App\Filament\Administration\Resources\Sales\Tables\SalesTable::reciboPagoPreviewUrl($sale) : null;
    $reciboPagoConfig = \Illuminate\Support\Js::from([
        'mode' => 'view',
        'previewUrl' => $previewUrl,
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
        <p
            x-show="error"
            x-cloak
            class="text-sm text-danger-600 dark:text-danger-400"
            x-text="error"
        ></p>

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
                            Vista previa del recibo de pago · {{ $sale->invoice_number }}
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
                        Use la barra del visor PDF para ampliar. Para guardar el archivo use el botón «Descargar PDF» del modal.
                    </p>
                </div>
            </article>
        </div>
    </div>
@endif
