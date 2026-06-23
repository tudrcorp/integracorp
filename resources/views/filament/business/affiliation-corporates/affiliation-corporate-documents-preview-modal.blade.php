@props([
    'affiliationCorporate' => null,
])

@php
    $regenerateAsyncUrl = $affiliationCorporate ? route('business.affiliation-corporate-documents.regenerate-async', $affiliationCorporate) : '';
    $sendEmailUrl = $affiliationCorporate ? route('business.affiliation-corporate-documents.send-email', $affiliationCorporate) : '';
    $statusUrlTemplate = $affiliationCorporate ? route('business.affiliation-corporate-documents.status', ['affiliationCorporate' => $affiliationCorporate, 'taskId' => '__TASK_ID__']) : '';
    $panelConfig = \Illuminate\Support\Js::from([
        'regenerateUrl' => $regenerateAsyncUrl,
        'sendEmailUrl' => $sendEmailUrl,
        'statusUrlTemplate' => $statusUrlTemplate,
    ]);
@endphp

@if (! $affiliationCorporate)
    <p class="text-sm text-gray-500 dark:text-gray-400">No hay afiliación corporativa asociada.</p>
@else
    <div
        wire:ignore
        class="fi-scoped max-h-[min(90vh,920px)] space-y-4 overflow-y-auto pr-1"
        x-data="window.affiliationDocumentsPanel({{ $panelConfig }})"
        x-init="regenerate()"
    >
        <div
            x-show="loading && ! regenerated"
            x-cloak
            class="overflow-hidden rounded-3xl border border-gray-200/80 bg-white/80 p-8 text-center shadow-sm backdrop-blur-md dark:border-white/10 dark:bg-gray-900/70"
        >
            <div class="flex flex-col items-center gap-4">
                <svg
                    class="size-12 shrink-0 animate-spin text-primary-600 dark:text-primary-400"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path
                        class="opacity-75"
                        fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                    ></path>
                </svg>
                <div>
                    <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                        Generando certificado y tarjetas corporativas...
                    </p>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Si hay más de 3 afiliados, el sistema procesa por lotes automáticamente para mantener el rendimiento.
                    </p>
                </div>
                <div x-show="progressPercentage !== null" x-cloak class="w-full max-w-md space-y-2">
                    <div class="flex items-center justify-between text-[0.72rem] font-semibold text-gray-600 dark:text-gray-300">
                        <span>Progreso de generación</span>
                        <span x-text="`${Math.max(0, Math.min(100, progressPercentage ?? 0))}%`"></span>
                    </div>
                    <div class="h-2.5 w-full overflow-hidden rounded-full bg-gray-200/90 dark:bg-gray-700/60">
                        <div
                            class="h-full rounded-full bg-gradient-to-r from-primary-500 via-primary-400 to-sky-400 transition-all duration-500 ease-out"
                            x-bind:style="`width: ${Math.max(0, Math.min(100, progressPercentage ?? 0))}%`"
                        ></div>
                    </div>
                    <p
                        x-show="totalJobs !== null && totalJobs > 0"
                        x-cloak
                        class="text-[0.68rem] text-gray-500 dark:text-gray-400"
                        x-text="`Lotes procesados: ${processedJobs ?? 0} de ${totalJobs}`"
                    ></p>
                    <p
                        x-show="progressPercentage !== null"
                        x-cloak
                        class="text-[0.68rem] font-medium text-primary-700 dark:text-primary-300"
                        x-text="`ETA estimado: ${formatEta(etaSeconds)}`"
                    ></p>
                </div>
            </div>
        </div>

        <p x-show="error" x-cloak class="text-sm text-danger-600 dark:text-danger-400" x-text="error"></p>
        <p x-show="loadingMessage" x-cloak class="text-xs text-gray-500 dark:text-gray-400" x-text="loadingMessage"></p>

        <div x-show="regenerated" x-cloak x-transition class="space-y-4">
            <article class="overflow-hidden rounded-3xl border border-gray-200/80 bg-white/80 shadow-sm backdrop-blur-md dark:border-white/10 dark:bg-gray-900/70">
                <div class="border-b border-gray-200/80 px-4 py-3 dark:border-white/10">
                    <p class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">Documentos generados</p>
                    <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                        Vista previa optimizada para lotes masivos
                    </p>
                </div>

                <div class="space-y-3 bg-gray-50/80 p-3 dark:bg-gray-950/60">
                    <div class="grid gap-2 md:grid-cols-2">
                        <template x-for="(doc, index) in documents" :key="index">
                            <button
                                type="button"
                                @click="setActiveDocument(index)"
                                class="w-full rounded-2xl border px-3 py-2 text-left transition-colors"
                                :class="activeDocumentIndex === index
                                    ? 'border-primary-500 bg-primary-50/80 text-primary-900 dark:border-primary-400 dark:bg-primary-900/20 dark:text-primary-100'
                                    : 'border-gray-200 bg-white/90 text-gray-700 hover:border-primary-300 dark:border-white/10 dark:bg-gray-900/70 dark:text-gray-200'"
                            >
                                <p class="text-[0.68rem] uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                    <span x-text="doc.kind === 'certificate' ? 'Certificado' : 'Tarjeta'"></span>
                                </p>
                                <p class="truncate text-sm font-semibold" x-text="doc.label"></p>
                                <p class="truncate text-xs text-gray-500 dark:text-gray-400" x-text="doc.filename"></p>
                            </button>
                        </template>
                    </div>

                    <template x-if="activeDocument()">
                        <div class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white dark:border-white/10 dark:bg-gray-900">
                            <iframe
                                x-bind:src="activeDocument().previewUrl"
                                x-bind:title="'Vista previa — ' + activeDocument().label"
                                class="h-[min(56vh,640px)] w-full border-0 bg-white dark:bg-gray-900"
                                loading="lazy"
                            ></iframe>
                        </div>
                    </template>
                </div>
            </article>

            <article
                class="overflow-hidden rounded-3xl border border-gray-200/80 bg-white/80 shadow-sm backdrop-blur-md dark:border-white/10 dark:bg-gray-900/70"
            >
                <div class="border-b border-gray-200/80 px-4 py-3 dark:border-white/10">
                    <p class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        Envio
                    </p>
                    <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                        Correo al agente o agencia
                    </p>
                </div>
                <div class="flex flex-col gap-4 bg-gray-50/80 p-4 dark:bg-gray-950/60 sm:flex-row sm:items-end">
                    <div class="min-w-0 flex-1">
                        <label
                            class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400"
                            for="affiliation-corporate-docs-email-{{ $affiliationCorporate->getKey() }}"
                        >
                            Correo destino (opcional)
                        </label>
                        <input
                            id="affiliation-corporate-docs-email-{{ $affiliationCorporate->getKey() }}"
                            type="email"
                            x-model="optionalEmail"
                            class="w-full rounded-2xl border border-gray-200/90 bg-white/95 px-4 py-2.5 text-sm text-gray-950 shadow-[inset_0_1px_2px_rgba(0,0,0,0.06)] outline-none ring-1 ring-gray-950/5 placeholder:text-gray-400 focus:border-primary-400 focus:ring-2 focus:ring-primary-500/30 dark:border-white/10 dark:bg-gray-900/80 dark:text-white dark:placeholder:text-gray-500 dark:focus:border-primary-500"
                            placeholder="Vacio = correo del agente o agencia (CC afiliaciones@tudrencasa.com)"
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
                            x-show="! sendingEmail"
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
                        <svg
                            x-show="sendingEmail"
                            x-cloak
                            class="size-5 shrink-0 animate-spin"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            ></path>
                        </svg>
                        <span x-show="! sendingEmail">Enviar por correo</span>
                        <span x-show="sendingEmail" x-cloak>Enviando...</span>
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
