@props([
    'sale' => null,
    'emailOptions' => null,
])

@php
    $regenerateAsyncUrl = $sale ? route('administration.sales.recibo-pago.regenerate-async', $sale) : '';
    $reciboPagoConfig = \Illuminate\Support\Js::from([
        'regenerateUrl' => $regenerateAsyncUrl,
        'sendUrl' => $sale ? route('administration.sales.recibo-pago.send-email', $sale) : '',
        'emailOptions' => $emailOptions,
        'testEmailDefault' => (string) (auth()->user()?->email ?? ''),
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
                    <span x-show="! loading">Generar recibo</span>
                    <span x-show="loading" x-cloak>Generando recibo…</span>
                </button>
            </div>
        </article>

        <p x-show="error" x-cloak class="text-sm text-danger-600 dark:text-danger-400" x-text="error"></p>

        <div x-show="regenerated" x-cloak x-transition class="space-y-4">
            <article
                class="overflow-hidden rounded-3xl border border-gray-200/80 bg-white/80 shadow-sm backdrop-blur-md dark:border-white/10 dark:bg-gray-900/70"
            >
                <div
                    class="flex flex-wrap items-center justify-between gap-3 px-4 py-3"
                    :class="showPreview ? 'border-b border-gray-200/80 dark:border-white/10' : ''"
                >
                    <div class="flex items-center gap-3">
                        <span class="inline-flex size-9 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">
                            <x-heroicon-o-document-check class="size-5" />
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                                Recibo de pago listo
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                PDF generado con el periodo indicado. Puede revisarlo antes de enviarlo.
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="showPreview = ! showPreview"
                        :aria-expanded="showPreview.toString()"
                        class="inline-flex w-full shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-full border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition-all duration-200 hover:bg-gray-50 active:scale-[0.98] sm:w-auto dark:border-white/10 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-white/5"
                    >
                        <x-heroicon-o-eye class="size-4 shrink-0" x-show="! showPreview" />
                        <x-heroicon-o-eye-slash class="size-4 shrink-0" x-show="showPreview" x-cloak />
                        <span x-text="showPreview ? 'Ocultar vista previa' : 'Ver vista previa'">Ver vista previa</span>
                    </button>
                </div>

                {{-- El visor se monta solo al abrirlo: así el PDF no se descarga si el analista no lo pide. --}}
                <template x-if="showPreview">
                    <div>
                        <div class="bg-gray-50/80 p-3 dark:bg-gray-950/60">
                            <iframe
                                x-bind:src="previewUrl"
                                title="Vista previa recibo de pago"
                                class="h-[min(72vh,800px)] w-full rounded-2xl border-0 bg-white dark:bg-gray-900"
                            ></iframe>
                        </div>
                        <div class="border-t border-gray-200/80 px-4 py-3 dark:border-white/10">
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Use la barra del visor PDF para ampliar. Puede descargar el archivo desde el menú de acciones de la tabla.
                            </p>
                        </div>
                    </div>
                </template>
            </article>

            @if ($emailOptions)
                <article
                    class="overflow-hidden rounded-3xl border border-gray-200/80 bg-white/80 shadow-sm backdrop-blur-md dark:border-white/10 dark:bg-gray-900/70"
                >
                    <div class="border-b border-gray-200/80 px-4 py-3 dark:border-white/10">
                        <p class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">
                            Envío por correo
                        </p>
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                            Elija el copy de cada destinatario
                        </p>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            Cada destinatario recibe su propio correo con el recibo adjunto. Elija «No enviar» para omitir uno.
                        </p>
                    </div>

                    <div class="grid gap-4 bg-gray-50/80 p-4 dark:bg-gray-950/60 lg:grid-cols-2">
                        @foreach (['affiliate' => 'heroicon-o-user', 'responsible' => 'heroicon-o-building-office-2'] as $kind => $icon)
                            @php($option = $emailOptions[$kind])
                            <section
                                wire:key="recibo-copy-{{ $kind }}-{{ $sale->getKey() }}"
                                class="flex flex-col gap-3 rounded-2xl border border-slate-200/80 bg-white/90 p-4 dark:border-white/10 dark:bg-white/5"
                            >
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex size-8 items-center justify-center rounded-full {{ $kind === 'affiliate' ? 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300' : 'bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300' }}">
                                        <x-dynamic-component :component="$icon" class="size-4" />
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $option['label'] }}</p>
                                        @if ($option['emails'] !== [])
                                            <p class="truncate text-xs text-emerald-700 dark:text-emerald-300">{{ implode(', ', $option['emails']) }}</p>
                                        @else
                                            <p class="text-xs text-amber-700 dark:text-amber-300">Sin correo válido registrado: solo puede probarse.</p>
                                        @endif
                                    </div>
                                </div>

                                <div>
                                    <label
                                        class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400"
                                        for="sales-recibo-copy-{{ $kind }}-{{ $sale->getKey() }}"
                                    >
                                        Copy del correo
                                    </label>
                                    <select
                                        id="sales-recibo-copy-{{ $kind }}-{{ $sale->getKey() }}"
                                        x-model="{{ $kind === 'affiliate' ? 'affiliateTemplateId' : 'responsibleTemplateId' }}"
                                        @change="confirming = false; sendSuccess = null"
                                        :disabled="sending || sendingTest"
                                        class="w-full rounded-2xl border border-gray-200/90 bg-white/95 px-4 py-2.5 text-sm text-gray-950 outline-none ring-1 ring-gray-950/5 focus:border-primary-400 focus:ring-2 focus:ring-primary-500/30 disabled:opacity-60 dark:border-white/10 dark:bg-gray-900/80 dark:text-white"
                                    >
                                        <option value="">No enviar</option>
                                        @foreach ($option['templates'] as $template)
                                            <option value="{{ $template['id'] }}">{{ $template['name'] }}</option>
                                        @endforeach
                                    </select>
                                    @if ($option['templates'] === [])
                                        <p class="mt-1.5 text-xs text-amber-700 dark:text-amber-300">
                                            No hay copys activos para este destinatario. Agréguelos en Administración → Copys de recibos.
                                        </p>
                                    @endif
                                </div>

                                <template x-if="templateFor('{{ $kind }}')">
                                    <div class="rounded-2xl border border-gray-200/80 bg-gray-50/80 dark:border-white/10 dark:bg-gray-950/40">
                                        <p class="border-b border-gray-200/80 px-3 py-2 text-xs dark:border-white/10">
                                            <span class="text-gray-400 dark:text-gray-500">Asunto:</span>
                                            <span class="font-semibold text-gray-800 dark:text-gray-100" x-text="templateFor('{{ $kind }}').subject"></span>
                                        </p>
                                        <div
                                            class="max-h-64 overflow-y-auto px-3 py-2 text-sm leading-relaxed text-gray-700 dark:text-gray-200"
                                            x-html="templateFor('{{ $kind }}').body_html"
                                        ></div>
                                    </div>
                                </template>
                                <p
                                    x-show="! templateFor('{{ $kind }}') && hasEmails('{{ $kind }}')"
                                    x-cloak
                                    class="text-xs text-gray-500 dark:text-gray-400"
                                >
                                    No se enviará correo a este destinatario.
                                </p>
                            </section>
                        @endforeach
                    </div>

                    <div class="space-y-3 border-t border-gray-200/80 px-4 py-3 dark:border-white/10">
                        @include('filament.administration.document-emails.test-send', [
                            'inputId' => 'sales-recibo-test-email-'.$sale->getKey(),
                        ])

                        @include('filament.administration.document-emails.witnesses-summary', [
                            'cc' => $emailOptions['cc_emails'] ?? [],
                            'bcc' => $emailOptions['bcc_emails'] ?? [],
                            'phones' => $emailOptions['witness_phones'] ?? [],
                            'settingLabel' => 'Testigos de recibos de pago',
                        ])

                        <p x-show="isStale" x-cloak class="text-sm text-amber-700 dark:text-amber-300">
                            Cambió el periodo de vigencia. Genere de nuevo la vista previa para enviar el recibo correcto.
                        </p>
                        <p x-show="sendError" x-cloak class="text-sm text-danger-600 dark:text-danger-400" x-text="sendError"></p>
                        <p x-show="sendSuccess" x-cloak class="text-sm font-medium text-emerald-700 dark:text-emerald-300" x-text="sendSuccess"></p>

                        <div x-show="! confirming">
                            <button
                                type="button"
                                @click="startSend()"
                                :disabled="! canSend || sendingTest"
                                class="aviso-btn-ios-primary inline-flex w-full items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98] disabled:opacity-60 sm:w-auto"
                            >
                                <x-heroicon-o-paper-airplane class="size-4" />
                                <span>Enviar recibo por correo</span>
                            </button>
                        </div>

                        <div
                            x-show="confirming"
                            x-cloak
                            class="space-y-3 rounded-2xl border border-primary-200/80 bg-primary-50/70 p-4 dark:border-primary-500/20 dark:bg-primary-950/20"
                        >
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">¿Confirma el envío?</p>
                            <ul class="space-y-1 text-sm text-gray-700 dark:text-gray-200">
                                @foreach (['affiliate', 'responsible'] as $kind)
                                    <li x-show="templateFor('{{ $kind }}')">
                                        <span class="font-medium">{{ $emailOptions[$kind]['label'] }}</span>
                                        → {{ implode(', ', $emailOptions[$kind]['emails']) }}
                                        con «<span x-text="templateFor('{{ $kind }}')?.name"></span>»
                                    </li>
                                @endforeach
                            </ul>
                            <div class="flex flex-col gap-2 sm:flex-row">
                                <button
                                    type="button"
                                    @click="send()"
                                    :disabled="sending"
                                    class="aviso-btn-ios-primary inline-flex items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98] disabled:opacity-60"
                                >
                                    <span x-show="! sending">Sí, enviar</span>
                                    <span x-show="sending" x-cloak>Enviando…</span>
                                </button>
                                <button
                                    type="button"
                                    @click="confirming = false"
                                    :disabled="sending"
                                    class="inline-flex items-center justify-center rounded-full border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 disabled:opacity-60 dark:border-white/10 dark:bg-gray-900 dark:text-gray-200"
                                >
                                    Cancelar
                                </button>
                            </div>
                        </div>
                    </div>
                </article>
            @endif
        </div>
    </div>
@endif
