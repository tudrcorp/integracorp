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
        x-on:keydown.enter="if ($event.target.tagName !== 'TEXTAREA') { $event.preventDefault(); $event.stopPropagation(); if (editing) { regenerate() } else if ($event.target.type === 'email') { sendEmail() } }"
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

        {{-- 2 · Vista previa y envío --}}
        <div x-show="regenerated && ! editing" x-cloak x-transition class="space-y-4">
            <article
                class="overflow-hidden rounded-3xl border border-gray-200/80 bg-white/80 shadow-sm backdrop-blur-md dark:border-white/10 dark:bg-gray-900/70"
            >
                <div
                    class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200/80 px-4 py-3 dark:border-white/10"
                >
                    <div>
                        <p class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">
                            Paso 2 de 2
                        </p>
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                            Vista previa del aviso de cobro
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            x-on:click="editing = true"
                            class="rounded-full bg-sky-100 px-2.5 py-1 text-xs font-medium text-sky-700 dark:bg-sky-500/20 dark:text-sky-300"
                        >
                            Editar datos
                        </button>
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
                            class="{{ $labelClass }}"
                            for="collections-aviso-email-{{ $key }}"
                        >
                            Correo destino (opcional)
                        </label>
                        <input
                            id="collections-aviso-email-{{ $key }}"
                            type="email"
                            x-model="optionalEmail"
                            class="{{ $inputClass }}"
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
