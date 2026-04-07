@props([
    'collection' => null,
])

@php
    $regenerateAsyncUrl = $collection ? route('aviso-cobro.regenerate-async', $collection) : '';
    $sendEmailUrl = $collection ? route('aviso-cobro.send-email', $collection) : '';
    $avisoCobroConfig = \Illuminate\Support\Js::from([
        'regenerateUrl' => $regenerateAsyncUrl,
        'sendEmailUrl' => $sendEmailUrl,
    ]);
@endphp

@if (! $collection)
    <p class="text-sm text-gray-500 dark:text-gray-400">No hay cobranza asociada.</p>
@else
    <div
        wire:ignore
        class="fi-scoped space-y-4"
        x-data="window.avisoCobroPanel({{ $avisoCobroConfig }})"
        x-init="regenerate()"
    >
        <div
            x-show="loading && ! regenerated"
            x-cloak
            class="overflow-hidden rounded-3xl border border-gray-200/80 bg-white/80 p-6 text-center shadow-sm backdrop-blur-md dark:border-white/10 dark:bg-gray-900/70"
        >
            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                Regenerando aviso de cobro…
            </p>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                En un momento verá la vista previa del PDF.
            </p>
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
                            Listo
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

                <div class="border-t border-gray-200/80 px-4 py-3 dark:border-white/10">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Use la barra del visor PDF para ampliar. Abajo puede enviar el aviso al correo del afiliado.
                    </p>
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
                        Correo al afiliado
                    </p>
                </div>
                <div class="flex flex-col gap-4 bg-gray-50/80 p-4 dark:bg-gray-950/60 sm:flex-row sm:items-end">
                    <div class="min-w-0 flex-1">
                        <label
                            class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400"
                            for="collections-aviso-email-{{ $collection->getKey() }}"
                        >
                            Correo destino (opcional)
                        </label>
                        <input
                            id="collections-aviso-email-{{ $collection->getKey() }}"
                            type="email"
                            x-model="optionalEmail"
                            class="w-full rounded-2xl border border-gray-200/90 bg-white/95 px-4 py-2.5 text-sm text-gray-950 shadow-[inset_0_1px_2px_rgba(0,0,0,0.06)] outline-none ring-1 ring-gray-950/5 placeholder:text-gray-400 focus:border-primary-400 focus:ring-2 focus:ring-primary-500/30 dark:border-white/10 dark:bg-gray-900/80 dark:text-white dark:placeholder:text-gray-500 dark:focus:border-primary-500"
                            placeholder="Vacío = correo del afiliado (CC administración)"
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
