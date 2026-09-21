@props(['whiteCompany' => null])

@php
    $config = $whiteCompany ? \Illuminate\Support\Js::from([
        'previewUrl' => route('administration.white-companies.sales-report.preview', $whiteCompany),
        'sendUrl' => route('administration.white-companies.sales-report.send', $whiteCompany),
        'defaultRecipient' => (string) ($whiteCompany->email ?? ''),
        'defaultPhone' => (string) ($whiteCompany->phone ?? ''),
        'defaultFrom' => now()->startOfMonth()->format('d/m/Y'),
        'defaultTo' => now()->format('d/m/Y'),
    ]) : null;
@endphp

@if (! $whiteCompany)
    <p class="text-sm text-gray-500 dark:text-gray-400">No hay empresa aliada asociada.</p>
@else
    <div
        wire:ignore
        class="fi-scoped max-h-[min(90vh,920px)] space-y-4 overflow-y-auto pr-1"
        x-data="window.whiteCompanySalesReportPanel({{ $config }})"
    >
        {{-- Paso 1: rango --}}
        <article class="overflow-hidden rounded-3xl border border-gray-200/80 bg-white/80 shadow-sm backdrop-blur-md dark:border-white/10 dark:bg-gray-900/70">
            <div class="border-b border-gray-200/80 px-4 py-3 dark:border-white/10">
                <p class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">Paso 1</p>
                <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Período del reporte</p>
            </div>
            <div class="flex flex-col gap-3 bg-gray-50/80 p-4 dark:bg-gray-950/60 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400" for="sr-from-{{ $whiteCompany->getKey() }}">Desde</label>
                    <input id="sr-from-{{ $whiteCompany->getKey() }}" type="text" x-model="from" @input="resetPreview()" placeholder="dd/mm/aaaa"
                        class="w-full rounded-2xl border border-gray-200/90 bg-white/95 px-4 py-2.5 text-sm text-gray-950 outline-none ring-1 ring-gray-950/5 focus:border-primary-400 focus:ring-2 focus:ring-primary-500/30 dark:border-white/10 dark:bg-gray-900/80 dark:text-white">
                </div>
                <div class="flex-1">
                    <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400" for="sr-to-{{ $whiteCompany->getKey() }}">Hasta</label>
                    <input id="sr-to-{{ $whiteCompany->getKey() }}" type="text" x-model="to" @input="resetPreview()" placeholder="dd/mm/aaaa"
                        class="w-full rounded-2xl border border-gray-200/90 bg-white/95 px-4 py-2.5 text-sm text-gray-950 outline-none ring-1 ring-gray-950/5 focus:border-primary-400 focus:ring-2 focus:ring-primary-500/30 dark:border-white/10 dark:bg-gray-900/80 dark:text-white">
                </div>
                <button type="button" @click="generate()" :disabled="loading"
                    class="aviso-btn-ios-primary inline-flex shrink-0 items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98] disabled:opacity-60">
                    <span x-show="! loading">Generar vista previa</span>
                    <span x-show="loading" x-cloak>Generando…</span>
                </button>
            </div>
        </article>

        <p x-show="error" x-cloak class="rounded-2xl bg-danger-50 px-4 py-3 text-sm text-danger-700 dark:bg-danger-500/10 dark:text-danger-400" x-text="error"></p>
        <p x-show="emptyMessage" x-cloak class="rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-200" x-text="emptyMessage"></p>

        {{-- Paso 2: vista previa --}}
        <template x-if="report">
            <div class="space-y-4">
                <article class="overflow-hidden rounded-3xl border border-gray-200/80 bg-white/80 shadow-sm backdrop-blur-md dark:border-white/10 dark:bg-gray-900/70">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200/80 px-4 py-3 dark:border-white/10">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">Paso 2 · Revise antes de enviar</p>
                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100" x-text="report.rows_count + ' afiliación(es) · ' + money(report.totals.sale_price)"></p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <span class="rounded-full bg-sky-100 px-2.5 py-1 font-medium text-sky-700 dark:bg-sky-500/20 dark:text-sky-300"
                                  x-text="'Neta TDG ' + money(report.totals.neta_tdg)"></span>
                            <span class="rounded-full bg-emerald-100 px-2.5 py-1 font-medium text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300"
                                  x-text="'Neta ' + report.company_name + ' ' + money(report.totals.neta_partner)"></span>
                        </div>
                    </div>
                    <div class="bg-gray-50/80 p-3 dark:bg-gray-950/60">
                        <iframe x-bind:src="documentUrl" title="Vista previa del reporte de ventas"
                            class="h-[min(56vh,640px)] w-full rounded-2xl border-0 bg-white dark:bg-gray-900" loading="lazy"></iframe>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-200/80 px-4 py-2 text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">
                        <span x-text="report.filename"></span>
                        <span>Llave: <span class="font-mono font-semibold text-gray-700 dark:text-gray-200" x-text="report.security_key"></span></span>
                        <span x-text="'Generado por ' + report.generated_by"></span>
                    </div>
                </article>

                {{-- Paso 3: destinatarios y decisión --}}
                <article class="overflow-hidden rounded-3xl border border-gray-200/80 bg-white/80 shadow-sm backdrop-blur-md dark:border-white/10 dark:bg-gray-900/70">
                    <div class="border-b border-gray-200/80 px-4 py-3 dark:border-white/10">
                        <p class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">Paso 3</p>
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">¿A quién se envía?</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Correos y/o WhatsApp del equipo de administración de la empresa aliada. Nada se envía hasta que pulse «Enviar estado de cuenta».
                        </p>
                    </div>
                    <div class="space-y-3 bg-gray-50/80 p-4 dark:bg-gray-950/60">
                        <p class="text-xs font-medium text-gray-600 dark:text-gray-400">Correo electrónico</p>

                        <div class="flex flex-wrap gap-2" x-show="recipients.length > 0" x-cloak>
                            <template x-for="email in recipients" :key="email">
                                <span class="inline-flex items-center gap-2 rounded-full bg-primary-100 px-3 py-1.5 text-xs font-medium text-primary-800 dark:bg-primary-500/20 dark:text-primary-200">
                                    <span x-text="email"></span>
                                    <button type="button" @click="removeRecipient(email)" class="font-bold" aria-label="Quitar">&times;</button>
                                </span>
                            </template>
                        </div>

                        <div class="flex flex-col gap-2 sm:flex-row">
                            <input type="email" x-model="newRecipient" @keydown.enter.prevent="addRecipient()" placeholder="correo@empresa.com"
                                class="min-w-0 flex-1 rounded-2xl border border-gray-200/90 bg-white/95 px-4 py-2.5 text-sm text-gray-950 outline-none ring-1 ring-gray-950/5 focus:border-primary-400 focus:ring-2 focus:ring-primary-500/30 dark:border-white/10 dark:bg-gray-900/80 dark:text-white">
                            <button type="button" @click="addRecipient()"
                                class="ticket-btn-ios-gray shrink-0 rounded-full px-5 py-2.5 text-sm font-semibold">Agregar</button>
                        </div>

                        <div class="border-t border-gray-200/80 pt-3 dark:border-white/10">
                            <p class="mb-2 text-xs font-medium text-gray-600 dark:text-gray-400">
                                WhatsApp (opcional)
                            </p>

                            <div class="mb-2 flex flex-wrap gap-2" x-show="phones.length > 0" x-cloak>
                                <template x-for="phone in phones" :key="phone">
                                    <span class="inline-flex items-center gap-2 rounded-full bg-emerald-100 px-3 py-1.5 text-xs font-medium text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-200">
                                        <span x-text="phone"></span>
                                        <button type="button" @click="removePhone(phone)" class="font-bold" aria-label="Quitar">&times;</button>
                                    </span>
                                </template>
                            </div>

                            <div class="flex flex-col gap-2 sm:flex-row">
                                <input type="tel" x-model="newPhone" @keydown.enter.prevent="addPhone()" placeholder="584141234567"
                                    class="min-w-0 flex-1 rounded-2xl border border-gray-200/90 bg-white/95 px-4 py-2.5 text-sm text-gray-950 outline-none ring-1 ring-gray-950/5 focus:border-primary-400 focus:ring-2 focus:ring-primary-500/30 dark:border-white/10 dark:bg-gray-900/80 dark:text-white">
                                <button type="button" @click="addPhone()"
                                    class="ticket-btn-ios-gray shrink-0 rounded-full px-5 py-2.5 text-sm font-semibold">Agregar</button>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200/80 pt-3 dark:border-white/10">
                            <p class="text-xs text-gray-500 dark:text-gray-400"
                               x-text="recipients.length + ' correo(s) · ' + phones.length + ' WhatsApp'"></p>
                            <button type="button" @click="send()" :disabled="sending || totalDestinations === 0"
                                class="aviso-btn-ios-success inline-flex shrink-0 items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98] disabled:opacity-60">
                                <span x-show="! sending">Enviar estado de cuenta</span>
                                <span x-show="sending" x-cloak>Enviando…</span>
                            </button>
                        </div>

                        <p x-show="successMessage" x-cloak
                           class="rounded-2xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"
                           x-text="successMessage"></p>
                    </div>
                </article>
            </div>
        </template>
    </div>
@endif
