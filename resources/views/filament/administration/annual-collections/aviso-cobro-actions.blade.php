@props([
    'collection' => null,
])

@php
    $regenerateAsyncUrl = $collection ? route('aviso-cobro.regenerate-async', $collection) : '';
    $sendEmailUrl = $collection ? route('aviso-cobro.send-email', $collection) : '';
    $downloadRouteUrl = $collection ? route('aviso-cobro.download', $collection) : '';
    $pdfFilename = $collection ? 'ADP-' . $collection->collection_invoice_number . '.pdf' : '';
    $avisoCobroConfig = \Illuminate\Support\Js::from([
        'regenerateUrl' => $regenerateAsyncUrl,
        'sendEmailUrl' => $sendEmailUrl,
    ]);
@endphp

@if (! $collection)
    <p class="text-sm text-gray-500 dark:text-gray-400">No hay cobranza asociada para generar el aviso.</p>
@else
    <div
        wire:ignore
        class="fi-scoped space-y-4"
        x-data="window.avisoCobroPanel({{ $avisoCobroConfig }})"
    >
        <div class="aviso-actions-wrap">
            <button
                type="button"
                @click="regenerate()"
                :disabled="loading"
                class="aviso-btn-ios-warning shrink-0 inline-flex cursor-pointer items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight no-underline transition-all duration-200 active:scale-[0.98] disabled:opacity-60"
            >
                <svg
                    class="size-5 shrink-0"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M16.0228 9.34841H21.0154V9.34663M2.98413 19.6444V14.6517M2.98413 14.6517L7.97677 14.6517M2.98413 14.6517L6.16502 17.8347C7.15555 18.8271 8.41261 19.58 9.86436 19.969C14.2654 21.1483 18.7892 18.5364 19.9685 14.1353M4.03073 9.86484C5.21 5.46374 9.73377 2.85194 14.1349 4.03121C15.5866 4.4202 16.8437 5.17312 17.8342 6.1655L21.0154 9.34663M21.0154 4.3558V9.34663"
                    />
                </svg>
                <span x-show="! loading">Regenerar PDF</span>
                <span x-show="loading" x-cloak>Regenerando…</span>
            </button>
            <a
                href="{{ $downloadRouteUrl }}"
                target="_blank"
                rel="noopener noreferrer"
                class="aviso-btn-ios-success shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight no-underline transition-all duration-200 active:scale-[0.98]"
            >
                <svg
                    class="size-5 shrink-0"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"
                    />
                </svg>
                Generar y descargar PDF
            </a>
        </div>

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
                            Cobranza
                        </p>
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                            Vista previa del aviso de cobro
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
                            Regenerado
                        </span>
                    </div>
                </div>

                <div class="bg-gray-50/80 p-3 dark:bg-gray-950/60">
                    <iframe
                        x-bind:src="previewUrl"
                        title="Vista previa aviso de cobro"
                        class="h-[min(72vh,800px)] w-full rounded-2xl border-0 bg-white dark:bg-gray-900"
                        loading="lazy"
                    ></iframe>
                </div>

                <div
                    class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200/80 px-4 py-3 dark:border-white/10"
                >
                    <p class="max-w-prose text-xs text-gray-500 dark:text-gray-400">
                        Use la barra del visor PDF para ampliar o descargar.
                    </p>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <a
                            x-bind:href="directUrl || '#'"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="ticket-btn-ios-gray inline-flex items-center justify-center rounded-full px-3 py-1.5 text-xs font-semibold tracking-tight no-underline transition-all duration-200 active:scale-[0.98]"
                            :class="directUrl ? '' : 'pointer-events-none opacity-50'"
                        >
                            Abrir en pestaña
                        </a>
                        <a
                            x-bind:href="directUrl || '#'"
                            x-bind:download="'{{ $pdfFilename }}'"
                            class="aviso-btn-ios-success inline-flex items-center justify-center rounded-full px-3 py-1.5 text-xs font-semibold tracking-tight no-underline transition-all duration-200 active:scale-[0.98]"
                            :class="directUrl ? '' : 'pointer-events-none opacity-50'"
                        >
                            Descargar
                        </a>
                    </div>
                </div>
            </article>

            <article
                class="overflow-hidden rounded-3xl border border-gray-200/80 bg-white/80 shadow-sm backdrop-blur-md dark:border-white/10 dark:bg-gray-900/70"
            >
                <div
                    class="border-b border-gray-200/80 px-4 py-3 dark:border-white/10"
                >
                    <p class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        Envío
                    </p>
                    <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                        Correo electrónico
                    </p>
                </div>
                <div class="flex flex-col gap-4 bg-gray-50/80 p-4 dark:bg-gray-950/60 sm:flex-row sm:items-end">
                    <div class="min-w-0 flex-1">
                        <label
                            class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400"
                            for="aviso-email-optional-{{ $collection->getKey() }}"
                        >
                            Correo destino (opcional)
                        </label>
                        <input
                            id="aviso-email-optional-{{ $collection->getKey() }}"
                            type="email"
                            x-model="optionalEmail"
                            class="w-full rounded-2xl border border-gray-200/90 bg-white/95 px-4 py-2.5 text-sm text-gray-950 shadow-[inset_0_1px_2px_rgba(0,0,0,0.06)] outline-none ring-1 ring-gray-950/5 placeholder:text-gray-400 focus:border-primary-400 focus:ring-2 focus:ring-primary-500/30 dark:border-white/10 dark:bg-gray-900/80 dark:text-white dark:placeholder:text-gray-500 dark:focus:border-primary-500"
                            placeholder="Vacío = correo del afiliado"
                            autocomplete="email"
                        />
                    </div>
                    <button
                        type="button"
                        @click="sendEmail()"
                        :disabled="sendingEmail"
                        class="aviso-btn-ios-primary inline-flex shrink-0 items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98] disabled:opacity-60"
                    >
                        <svg
                            class="size-5 shrink-0"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"
                            />
                        </svg>
                        <span x-show="! sendingEmail">Enviar por correo</span>
                        <span x-show="sendingEmail" x-cloak>Enviando…</span>
                    </button>
                </div>
                <p
                    x-show="emailMessage"
                    x-cloak
                    class="border-t border-gray-200/80 px-4 py-3 text-sm text-emerald-600 dark:border-white/10 dark:text-emerald-400"
                    x-text="emailMessage"
                ></p>
            </article>
        </div>
    </div>
@endif
