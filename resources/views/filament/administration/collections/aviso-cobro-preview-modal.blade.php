@props([
    'collection' => null,
])

@php
    $regenerateAsyncUrl = $collection ? route('aviso-cobro.regenerate-async', $collection) : '';
    $sendEmailUrl = $collection ? route('aviso-cobro.send-email', $collection) : '';
    $noticeDefaults = $collection ? \App\Support\Collections\CollectionNotice::formDefaults($collection) : [];
    $avisoCobroConfig = \Illuminate\Support\Js::from([
        'regenerateUrl' => $regenerateAsyncUrl,
        'sendEmailUrl' => $sendEmailUrl,
        'testEmailDefault' => (string) (auth()->user()?->email ?? ''),
        'form' => $collection ? \Illuminate\Support\Arr::only($noticeDefaults, ['full_name', 'document', 'address', 'phone', 'email', 'emission_date', 'period_from', 'period_to']) : null,
    ]);
    $inputClass = 'w-full rounded-2xl border border-gray-200/90 bg-white/95 px-4 py-2.5 text-sm text-gray-950 shadow-[inset_0_1px_2px_rgba(0,0,0,0.06)] outline-none ring-1 ring-gray-950/5 placeholder:text-gray-400 focus:border-primary-400 focus:ring-2 focus:ring-primary-500/30 dark:border-white/10 dark:bg-gray-900/80 dark:text-white dark:placeholder:text-gray-500 dark:focus:border-primary-500';
    $labelClass = 'mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400';
    $key = $collection?->getKey();
@endphp

@if (! $collection)
    <p class="text-sm text-gray-500 dark:text-gray-400">No hay cobranza asociada.</p>
@else
    <div
        wire:ignore
        class="fi-scoped space-y-4"
        x-data="window.avisoCobroPanel({{ $avisoCobroConfig }})"
        {{-- La ventana de la modal de Filament ya es un <form wire:submit>: aquí no puede haber otro form, y Enter
             en un campo enviaría el de Filament (ejecuta la acción y cierra la modal). --}}
        x-on:keydown.enter="if ($event.target.tagName !== 'TEXTAREA') { $event.preventDefault(); $event.stopPropagation(); if (editing) { regenerate() } }"
    >
        <style>
            .aviso-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px 16px; }
            .aviso-form-grid .is-wide { grid-column: 1 / -1; }
            .aviso-form-error { margin-top: 4px; font-size: 12px; color: rgb(220 38 38); }
            .dark .aviso-form-error { color: rgb(248 113 113); }
            @media (max-width: 640px) { .aviso-form-grid { grid-template-columns: minmax(0, 1fr); } }
        </style>

        {{-- 1 · Datos del aviso (editables) --}}
        <article
            x-show="editing"
            class="overflow-hidden rounded-3xl border border-gray-200/80 bg-white/80 shadow-sm backdrop-blur-md dark:border-white/10 dark:bg-gray-900/70"
        >
            <div class="border-b border-gray-200/80 px-4 py-3 dark:border-white/10">
                <p class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">Paso 1 de 2</p>
                <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Revise los datos del aviso</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Los cambios valen solo para este PDF; la cuota no se modifica. Frecuencia de pago: {{ $noticeDefaults['frequency'] !== '' ? $noticeDefaults['frequency'] : 'sin dato' }}.
                    @if ($noticeDefaults['last_regenerated'])
                        Última regeneración: {{ $noticeDefaults['last_regenerated'] }}.
                    @endif
                </p>
            </div>

            <div class="bg-gray-50/80 p-4 dark:bg-gray-950/60" role="form" aria-label="Datos del aviso de cobro">
                <div class="aviso-form-grid">
                    <div class="is-wide">
                        <label class="{{ $labelClass }}" for="aviso-{{ $key }}-name">Nombre del cliente</label>
                        <input id="aviso-{{ $key }}-name" type="text" maxlength="200" required x-model="form.full_name" class="{{ $inputClass }}" autocomplete="off">
                        <p class="aviso-form-error" x-show="fieldErrors.full_name" x-text="fieldErrors.full_name" x-cloak></p>
                    </div>
                    <div>
                        <label class="{{ $labelClass }}" for="aviso-{{ $key }}-document">Documento (C.I. / R.I.F.)</label>
                        <input id="aviso-{{ $key }}-document" type="text" maxlength="40" x-model="form.document" class="{{ $inputClass }}" autocomplete="off">
                        <p class="aviso-form-error" x-show="fieldErrors.document" x-text="fieldErrors.document" x-cloak></p>
                    </div>
                    <div>
                        <label class="{{ $labelClass }}" for="aviso-{{ $key }}-phone">Teléfono</label>
                        <input id="aviso-{{ $key }}-phone" type="tel" maxlength="60" x-model="form.phone" class="{{ $inputClass }}" autocomplete="off">
                        <p class="aviso-form-error" x-show="fieldErrors.phone" x-text="fieldErrors.phone" x-cloak></p>
                    </div>
                    <div class="is-wide">
                        <label class="{{ $labelClass }}" for="aviso-{{ $key }}-email">Correo</label>
                        <input id="aviso-{{ $key }}-email" type="email" maxlength="190" x-model="form.email" class="{{ $inputClass }}" autocomplete="off">
                        <p class="aviso-form-error" x-show="fieldErrors.email" x-text="fieldErrors.email" x-cloak></p>
                    </div>
                    <div class="is-wide">
                        <label class="{{ $labelClass }}" for="aviso-{{ $key }}-address">Dirección</label>
                        <textarea id="aviso-{{ $key }}-address" rows="2" maxlength="500" x-model="form.address" class="{{ $inputClass }}"></textarea>
                        <p class="aviso-form-error" x-show="fieldErrors.address" x-text="fieldErrors.address" x-cloak></p>
                    </div>
                    <div class="is-wide">
                        <label class="{{ $labelClass }}" for="aviso-{{ $key }}-emission">Fecha de emisión</label>
                        <input id="aviso-{{ $key }}-emission" type="date" required x-model="dates.emission_date" class="{{ $inputClass }}">
                        <p class="aviso-form-error" x-show="fieldErrors.emission_date" x-text="fieldErrors.emission_date" x-cloak></p>
                    </div>
                    <div>
                        <label class="{{ $labelClass }}" for="aviso-{{ $key }}-from">Período de cobro: desde</label>
                        <input id="aviso-{{ $key }}-from" type="date" required x-model="dates.period_from" class="{{ $inputClass }}">
                        <p class="aviso-form-error" x-show="fieldErrors.period_from" x-text="fieldErrors.period_from" x-cloak></p>
                    </div>
                    <div>
                        <label class="{{ $labelClass }}" for="aviso-{{ $key }}-to">Período de cobro: hasta</label>
                        <input id="aviso-{{ $key }}-to" type="date" required x-model="dates.period_to" x-bind:min="dates.period_from" class="{{ $inputClass }}">
                        <p class="aviso-form-error" x-show="fieldErrors.period_to" x-text="fieldErrors.period_to" x-cloak></p>
                    </div>
                </div>

                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    En el PDF se imprimirá al final de la descripción: <strong>PERIODO DE COBRO: <span x-text="periodPreview()"></span></strong>
                </p>

                <div class="mt-4 flex flex-wrap items-center justify-end gap-3">
                    <button
                        type="button"
                        x-show="regenerated"
                        x-cloak
                        x-on:click="editing = false"
                        class="inline-flex shrink-0 items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold tracking-tight text-gray-700 ring-1 ring-gray-950/10 transition-all duration-200 active:scale-[0.98] dark:text-gray-200 dark:ring-white/10"
                    >
                        Volver a la vista previa
                    </button>
                    <button
                        type="button"
                        x-on:click="regenerate()"
                        x-bind:disabled="loading"
                        class="aviso-btn-ios-primary inline-flex shrink-0 items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98] disabled:opacity-60"
                    >
                        <span x-show="! loading">Regenerar PDF</span>
                        <span x-show="loading" x-cloak>Regenerando…</span>
                    </button>
                </div>
            </div>
        </article>

        <p x-show="error" x-cloak class="text-sm text-danger-600 dark:text-danger-400" role="alert" x-text="error"></p>

        {{-- 2 · Documento listo, vista previa opcional y envío --}}
        <div x-show="regenerated && ! editing" x-cloak x-transition class="space-y-4">
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
                            <p class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">Paso 2 de 2</p>
                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Aviso de cobro listo</p>
                        </div>
                    </div>
                    <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                        <button
                            type="button"
                            x-on:click="editing = true; confirming = false"
                            class="inline-flex flex-1 shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-full border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition-all duration-200 hover:bg-gray-50 active:scale-[0.98] sm:flex-none dark:border-white/10 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-white/5"
                        >
                            <x-heroicon-o-pencil-square class="size-4 shrink-0" />
                            <span>Editar datos</span>
                        </button>
                        <button
                            type="button"
                            x-on:click="showPreview = ! showPreview"
                            x-bind:aria-expanded="showPreview.toString()"
                            class="inline-flex flex-1 shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-full border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition-all duration-200 hover:bg-gray-50 active:scale-[0.98] sm:flex-none dark:border-white/10 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-white/5"
                        >
                            <x-heroicon-o-eye class="size-4 shrink-0" x-show="! showPreview" />
                            <x-heroicon-o-eye-slash class="size-4 shrink-0" x-show="showPreview" x-cloak />
                            <span x-text="showPreview ? 'Ocultar vista previa' : 'Ver vista previa'">Ver vista previa</span>
                        </button>
                    </div>
                </div>

                {{-- El visor se monta solo al abrirlo: así el PDF no se descarga si el analista no lo pide. --}}
                <template x-if="showPreview">
                    <div>
                        <div class="bg-gray-50/80 p-3 dark:bg-gray-950/60">
                            <iframe
                                x-bind:src="previewUrl"
                                title="Vista previa aviso de cobro"
                                class="h-[min(72vh,800px)] w-full rounded-2xl border-0 bg-white dark:bg-gray-900"
                            ></iframe>
                        </div>
                        <div class="border-t border-gray-200/80 px-4 py-3 dark:border-white/10">
                            <p class="text-xs text-gray-500 dark:text-gray-400">Use la barra del visor PDF para ampliar.</p>
                        </div>
                    </div>
                </template>
            </article>

            <article
                class="overflow-hidden rounded-3xl border border-gray-200/80 bg-white/80 shadow-sm backdrop-blur-md dark:border-white/10 dark:bg-gray-900/70"
            >
                <div class="border-b border-gray-200/80 px-4 py-3 dark:border-white/10">
                    <p class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">Envío por correo</p>
                    <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Elija el copy de cada destinatario</p>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        Cada destinatario recibe su propio correo con el aviso adjunto. Elija «No enviar» para omitir uno.
                    </p>
                </div>

                <p x-show="! emailOptions" x-cloak class="px-4 py-3 text-sm text-amber-700 dark:text-amber-300">
                    No se pudieron cargar los copys. Cierre la ventana y vuelva a abrirla.
                </p>

                <template x-if="emailOptions">
                    <div>
                        <div class="grid gap-4 bg-gray-50/80 p-4 dark:bg-gray-950/60 lg:grid-cols-2">
                            @foreach (['affiliate' => ['heroicon-o-user', 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300'], 'responsible' => ['heroicon-o-building-office-2', 'bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300']] as $kind => [$icon, $iconClass])
                                <section class="flex flex-col gap-3 rounded-2xl border border-slate-200/80 bg-white/90 p-4 dark:border-white/10 dark:bg-white/5">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex size-8 items-center justify-center rounded-full {{ $iconClass }}">
                                            <x-dynamic-component :component="$icon" class="size-4" />
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-gray-900 dark:text-white" x-text="emailOptions.{{ $kind }}.label"></p>
                                            <p x-show="hasEmails('{{ $kind }}')" class="truncate text-xs text-emerald-700 dark:text-emerald-300" x-text="emailOptions.{{ $kind }}.emails.join(', ')"></p>
                                            <p x-show="! hasEmails('{{ $kind }}')" class="text-xs text-amber-700 dark:text-amber-300">Sin correo válido registrado: solo puede probarse.</p>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="{{ $labelClass }}" for="aviso-copy-{{ $kind }}-{{ $key }}">Copy del correo</label>
                                        <select
                                            id="aviso-copy-{{ $kind }}-{{ $key }}"
                                            x-model="{{ $kind === 'affiliate' ? 'affiliateTemplateId' : 'responsibleTemplateId' }}"
                                            x-on:change="confirming = false; emailMessage = null"
                                            x-bind:disabled="sendingEmail || sendingTest"
                                            class="{{ $inputClass }} disabled:opacity-60"
                                        >
                                            <option value="">No enviar</option>
                                            <template x-for="template in emailOptions.{{ $kind }}.templates" x-bind:key="template.id">
                                                <option x-bind:value="String(template.id)" x-text="template.name" x-bind:selected="String(template.id) === {{ $kind === 'affiliate' ? 'affiliateTemplateId' : 'responsibleTemplateId' }}"></option>
                                            </template>
                                        </select>
                                        <p x-show="emailOptions.{{ $kind }}.templates.length === 0" class="mt-1.5 text-xs text-amber-700 dark:text-amber-300">
                                            No hay copys activos para este destinatario. Agréguelos en Administración → Copys de avisos de cobro.
                                        </p>
                                    </div>

                                    <template x-if="templateFor('{{ $kind }}')">
                                        <div class="rounded-2xl border border-gray-200/80 bg-gray-50/80 dark:border-white/10 dark:bg-gray-950/40">
                                            <p class="border-b border-gray-200/80 px-3 py-2 text-xs dark:border-white/10">
                                                <span class="text-gray-400 dark:text-gray-500">Asunto:</span>
                                                <span class="font-semibold text-gray-800 dark:text-gray-100" x-text="templateFor('{{ $kind }}').subject"></span>
                                            </p>
                                            <div class="max-h-64 overflow-y-auto px-3 py-2 text-sm leading-relaxed text-gray-700 dark:text-gray-200" x-html="templateFor('{{ $kind }}').body_html"></div>
                                        </div>
                                    </template>
                                </section>
                            @endforeach
                        </div>

                        <div class="space-y-3 border-t border-gray-200/80 px-4 py-3 dark:border-white/10">
                            <label class="flex items-start gap-3 rounded-2xl border border-gray-200/80 bg-white/90 px-3 py-2.5 dark:border-white/10 dark:bg-white/5" x-bind:class="! emailOptions.payment_methods_available ? 'opacity-60' : ''">
                                <input
                                    type="checkbox"
                                    x-model="attachPaymentMethods"
                                    x-on:change="confirming = false"
                                    x-bind:disabled="sendingEmail || ! emailOptions.payment_methods_available"
                                    class="mt-0.5 size-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-white/20 dark:bg-gray-900"
                                >
                                <span>
                                    <span class="block text-sm font-medium text-gray-900 dark:text-white">Adjuntar métodos de pago</span>
                                    <span class="block text-xs text-gray-500 dark:text-gray-400" x-text="emailOptions.payment_methods_available ? 'Agrega el PDF de métodos de pago nacionales e internacionales a cada correo.' : 'El documento de métodos de pago no está cargado en el sistema.'"></span>
                                </span>
                            </label>

                            @include('filament.administration.document-emails.test-send', [
                                'inputId' => 'collections-aviso-email-'.$key,
                                'inputClass' => $inputClass,
                                'labelClass' => $labelClass,
                            ])

                            <div class="space-y-0.5 text-xs text-gray-500 dark:text-gray-400">
                                <p x-show="(emailOptions.cc_emails || []).length">Copia visible (CC): <span x-text="(emailOptions.cc_emails || []).join(', ')"></span></p>
                                <p x-show="(emailOptions.bcc_emails || []).length">Copia oculta (CCO): <span x-text="(emailOptions.bcc_emails || []).join(', ')"></span></p>
                                <p x-show="(emailOptions.witness_phones || []).length">WhatsApp testigo con el PDF: <span x-text="(emailOptions.witness_phones || []).join(', ')"></span></p>
                                <p x-show="! (emailOptions.cc_emails || []).length && ! (emailOptions.bcc_emails || []).length && ! (emailOptions.witness_phones || []).length">Sin testigos activos.</p>
                                <p>Los testigos se configuran en Negocios → Centro de notificaciones → «Testigos de avisos de cobro».</p>
                            </div>
                            <p x-show="emailError" x-cloak class="text-sm text-danger-600 dark:text-danger-400" role="alert" x-text="emailError"></p>
                            <p x-show="emailMessage" x-cloak class="text-sm font-medium text-emerald-700 dark:text-emerald-300" x-text="emailMessage"></p>

                            <div x-show="! confirming">
                                <button
                                    type="button"
                                    x-on:click="startSend()"
                                    x-bind:disabled="! canSend || sendingTest"
                                    class="aviso-btn-ios-primary inline-flex w-full items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98] disabled:opacity-60 sm:w-auto"
                                >
                                    <x-heroicon-o-paper-airplane class="size-4" />
                                    <span>Enviar aviso por correo</span>
                                </button>
                            </div>

                            <div x-show="confirming" x-cloak class="space-y-3 rounded-2xl border border-primary-200/80 bg-primary-50/70 p-4 dark:border-primary-500/20 dark:bg-primary-950/20">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white" x-text="'¿Confirma el envío?'"></p>
                                <ul class="space-y-1 text-sm text-gray-700 dark:text-gray-200">
                                    <template x-for="line in confirmationLines()" x-bind:key="line">
                                        <li x-text="line"></li>
                                    </template>
                                </ul>
                                <div class="flex flex-col gap-2 sm:flex-row">
                                    <button
                                        type="button"
                                        x-on:click="sendEmail()"
                                        x-bind:disabled="sendingEmail"
                                        class="aviso-btn-ios-primary inline-flex items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98] disabled:opacity-60"
                                    >
                                        <span x-show="! sendingEmail">Sí, enviar</span>
                                        <span x-show="sendingEmail" x-cloak>Enviando…</span>
                                    </button>
                                    <button
                                        type="button"
                                        x-on:click="confirming = false"
                                        x-bind:disabled="sendingEmail"
                                        class="inline-flex items-center justify-center rounded-full border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 disabled:opacity-60 dark:border-white/10 dark:bg-gray-900 dark:text-gray-200"
                                    >
                                        Cancelar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </article>
        </div>
    </div>
@endif
