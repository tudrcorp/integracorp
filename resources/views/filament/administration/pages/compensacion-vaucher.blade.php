<x-filament-panels::page>
    <div class="max-w-md">
        <div class="rounded-2xl border border-slate-200/70 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-900/80">
            <div class="flex items-center gap-3">
                <label for="voucher-search" class="sr-only">Voucher</label>
                <div class="relative flex-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400 dark:text-slate-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.45 4.39l3.58 3.58a.75.75 0 1 1-1.06 1.06l-3.58-3.58A7 7 0 0 1 2 9Z" clip-rule="evenodd"/>
                    </svg>
                    <input
                        id="voucher-search"
                        type="text"
                        wire:model.defer="voucherSearch"
                        wire:keydown.enter.prevent="searchVouchers"
                        placeholder="Buscar voucher... ej. TG-R34H78"
                        class="w-full rounded-lg border-0 bg-slate-100/80 py-2 pl-9 pr-3 text-sm text-slate-900 outline-none ring-1 ring-slate-200/80 transition placeholder:text-slate-400 focus:bg-white focus:ring-2 focus:ring-amber-400/50 dark:bg-slate-800/60 dark:text-white dark:ring-white/10 dark:placeholder:text-slate-500 dark:focus:bg-slate-800 dark:focus:ring-amber-400/40"
                    />
                </div>
                <button
                    type="button"
                    wire:click="searchVouchers"
                    wire:loading.attr="disabled"
                    wire:target="searchVouchers"
                    class="inline-flex shrink-0 items-center justify-center rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-400 active:scale-[0.97] disabled:opacity-60"
                >
                    <svg wire:loading wire:target="searchVouchers" class="mr-1.5 size-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.37 0 0 5.37 0 12h4z"></path></svg>
                    <span wire:loading.remove wire:target="searchVouchers">Buscar</span>
                    <span wire:loading wire:target="searchVouchers">Buscando...</span>
                </button>
            </div>
            <p class="mt-2 text-[11px] text-slate-400 dark:text-slate-500">Presione ENTER o haga clic en Buscar.</p>
        </div>
    </div>

    <div
        x-data="{ open: $wire.entangle('isResultsModalOpen') }"
        x-cloak
        x-show="open"
        x-transition.opacity
        class="fixed inset-0 z-[90] flex items-center justify-center p-4 sm:p-6"
        role="dialog"
        aria-modal="true"
        aria-label="Compensación de voucher"
    >
        <div class="absolute inset-0 bg-slate-950/55 backdrop-blur-[2px]" x-on:click="$wire.closeResultsModal()"></div>

        <div
            x-data="{
                activeTab: 'pago',
                savedAt: { pago: null, estatus: null, comision: null },
                agoText: { pago: null, estatus: null, comision: null },
                _agoTimer: null,
                init() {
                    this._agoTimer = setInterval(() => {
                        ['pago', 'estatus', 'comision'].forEach(tab => {
                            if (this.savedAt[tab]) {
                                const secs = Math.floor((Date.now() - this.savedAt[tab]) / 1000);
                                if (secs < 5) { this.agoText[tab] = 'hace un momento'; }
                                else if (secs < 60) { this.agoText[tab] = 'hace ' + secs + 's'; }
                                else {
                                    const mins = Math.floor(secs / 60);
                                    const rem = secs % 60;
                                    this.agoText[tab] = 'hace ' + mins + 'm ' + String(rem).padStart(2, '0') + 's';
                                }
                            }
                        });
                    }, 1000);
                    Livewire.on('tab-saved', ({ tab }) => {
                        this.savedAt[tab] = Date.now();
                        this.agoText[tab] = 'hace un momento';
                    });
                },
                destroy() {
                    if (this._agoTimer) { clearInterval(this._agoTimer); }
                },
            }"
            class="relative z-[91] max-h-[92vh] w-full max-w-7xl overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-2xl dark:border-white/10 dark:bg-slate-900"
        >
            <div class="flex items-center justify-between border-b border-slate-200/80 px-5 py-4 dark:border-white/10">
                <div>
                    <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">
                        Resultado de compensación
                    </h3>
                    <p class="text-xs text-slate-600 dark:text-slate-400">
                        Registros encontrados: <span class="font-semibold">{{ count($this->resultRows) }}</span>
                    </p>
                </div>
                <button
                    type="button"
                    wire:click="closeResultsModal"
                    class="inline-flex items-center justify-center rounded-full p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white"
                    aria-label="Cerrar modal"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.22 4.22a.75.75 0 0 1 1.06 0L10 8.94l4.72-4.72a.75.75 0 1 1 1.06 1.06L11.06 10l4.72 4.72a.75.75 0 1 1-1.06 1.06L10 11.06l-4.72 4.72a.75.75 0 1 1-1.06-1.06L8.94 10 4.22 5.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>

            <div class="max-h-[calc(92vh-4.25rem)] overflow-y-auto">
                <section class="border-b border-slate-200/80 bg-slate-50/80 px-5 py-4 dark:border-white/10 dark:bg-slate-800/40">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-2xl border border-slate-200/80 bg-white px-4 py-3 dark:border-white/10 dark:bg-slate-900/80">
                            <p class="text-[0.7rem] uppercase tracking-wide text-slate-500 dark:text-slate-400">Vouchers</p>
                            <p class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ count($this->resultRows) }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200/80 bg-white px-4 py-3 dark:border-white/10 dark:bg-slate-900/80">
                            <p class="text-[0.7rem] uppercase tracking-wide text-slate-500 dark:text-slate-400">Total PVP</p>
                            <p class="text-xl font-semibold text-slate-900 dark:text-slate-100">US$ {{ number_format($this->resultTotalMontoPvp, 2, ',', '.') }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200/80 bg-white px-4 py-3 dark:border-white/10 dark:bg-slate-900/80">
                            <p class="text-[0.7rem] uppercase tracking-wide text-slate-500 dark:text-slate-400">Total comisión</p>
                            <p class="text-xl font-semibold text-slate-900 dark:text-slate-100">US$ {{ number_format($this->resultTotalMontoComision, 2, ',', '.') }}</p>
                        </div>
                    </div>

                    <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200/80 dark:border-white/10">
                        <div class="max-h-52 overflow-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-xs dark:divide-white/10">
                                <thead class="bg-slate-100/80 dark:bg-slate-800/80">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Voucher</th>
                                        <th class="px-3 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Pasajero</th>
                                        <th class="px-3 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Agencia</th>
                                        <th class="px-3 py-2 text-right font-semibold text-slate-700 dark:text-slate-200">PVP</th>
                                        <th class="px-3 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Estatus</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white dark:divide-white/10 dark:bg-slate-900/60">
                                    @foreach ($this->resultRows as $row)
                                        <tr>
                                            <td class="px-3 py-2 font-mono text-slate-800 dark:text-slate-200">{{ $row['vaucher'] }}</td>
                                            <td class="px-3 py-2 text-slate-700 dark:text-slate-300">{{ $row['pasajero'] }}</td>
                                            <td class="px-3 py-2 text-slate-700 dark:text-slate-300">{{ $row['agencia'] }}</td>
                                            <td class="px-3 py-2 text-right text-slate-800 dark:text-slate-200">US$ {{ number_format((float) $row['monto_pvp_precio_de_venta'], 2, ',', '.') }}</td>
                                            <td class="px-3 py-2 text-slate-700 dark:text-slate-300">{{ $row['estatus_vaucher'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section class="space-y-4 px-5 py-5">
                    <div class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-2 dark:border-white/10 dark:bg-slate-800/40">
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <button type="button" x-on:click="activeTab='pago'" x-bind:class="activeTab==='pago' ? 'bg-white text-amber-700 ring-2 ring-amber-400/40 dark:bg-slate-900 dark:text-amber-300' : 'bg-transparent text-slate-700 hover:bg-white/70 dark:text-slate-200 dark:hover:bg-slate-900/60'" class="inline-flex items-center justify-center gap-1 rounded-xl px-3 py-2 text-xs font-semibold transition">
                                <span>Formulario 1</span><span class="hidden sm:inline">· Pago</span>
                            </button>
                            <button type="button" x-on:click="activeTab='estatus'" x-bind:class="activeTab==='estatus' ? 'bg-white text-amber-700 ring-2 ring-amber-400/40 dark:bg-slate-900 dark:text-amber-300' : 'bg-transparent text-slate-700 hover:bg-white/70 dark:text-slate-200 dark:hover:bg-slate-900/60'" class="inline-flex items-center justify-center gap-1 rounded-xl px-3 py-2 text-xs font-semibold transition">
                                <span>Formulario 2</span><span class="hidden sm:inline">· Estatus</span>
                            </button>
                            <button type="button" x-on:click="activeTab='comision'" x-bind:class="activeTab==='comision' ? 'bg-white text-amber-700 ring-2 ring-amber-400/40 dark:bg-slate-900 dark:text-amber-300' : 'bg-transparent text-slate-700 hover:bg-white/70 dark:text-slate-200 dark:hover:bg-slate-900/60'" class="inline-flex items-center justify-center gap-1 rounded-xl px-3 py-2 text-xs font-semibold transition">
                                <span>Formulario 3</span><span class="hidden sm:inline">· Comisión</span>
                            </button>
                            <button type="button" x-on:click="activeTab='pendiente'" x-bind:class="activeTab==='pendiente' ? 'bg-white text-amber-700 ring-2 ring-amber-400/40 dark:bg-slate-900 dark:text-amber-300' : 'bg-transparent text-slate-700 hover:bg-white/70 dark:text-slate-200 dark:hover:bg-slate-900/60'" class="inline-flex items-center justify-center gap-1 rounded-xl px-3 py-2 text-xs font-semibold transition">
                                <span>Formulario 4</span><span class="hidden sm:inline">· Pendiente</span>
                            </button>
                        </div>
                    </div>

                    <div x-show="activeTab==='pago'" x-cloak x-transition.opacity.duration.200ms>
                        <form wire:submit.prevent="savePaymentTab" class="space-y-4">
                            <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
                                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Carga y datos de pago</p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Este formulario actualiza de una vez todos los vouchers encontrados en la búsqueda.</p>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="rounded-2xl border border-slate-200/80 bg-white p-4 dark:border-white/10 dark:bg-slate-900/70">
                                    <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Comprobante de pago</label>
                                    <input type="file" wire:model="paymentForm.comprobante_pago" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs dark:border-white/10 dark:bg-slate-800 dark:text-slate-100">
                                    <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">JPG, PNG, WebP, GIF o PDF (máx. 5MB).</p>
                                    @error('paymentForm.comprobante_pago') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                </div>
                                <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50/70 p-4 dark:border-emerald-400/30 dark:bg-emerald-500/10">
                                    <label class="mb-1 block text-xs font-medium text-emerald-700 dark:text-emerald-300">Total sumatoria vouchers</label>
                                    <input type="text" value="US$ {{ number_format($this->resultTotalMontoPvp, 2, ',', '.') }}" disabled class="w-full rounded-xl border border-emerald-200 bg-white px-3 py-2 text-sm font-semibold text-emerald-700 dark:border-emerald-500/30 dark:bg-slate-900 dark:text-emerald-300">
                                    <p class="mt-1 text-[11px] text-emerald-700/80 dark:text-emerald-300/80">Referencia consolidada para validar el monto de la compensación.</p>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Forma de pago</label>
                                        <select wire:model="paymentForm.forma_pago" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs dark:border-white/10 dark:bg-slate-800 dark:text-slate-100">
                                            <option value="">Seleccione...</option>
                                            @foreach ($this->formaPagoOptions() as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Estatus de pago</label>
                                        <select wire:model="paymentForm.estatus_pago" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs dark:border-white/10 dark:bg-slate-800 dark:text-slate-100">
                                            <option value="">Seleccione...</option>
                                            @foreach ($this->statusPagoOptions() as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Entidad bancaria</label>
                                        <input type="text" wire:model.defer="paymentForm.entidad_bancaria_receptora" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs dark:border-white/10 dark:bg-slate-800 dark:text-slate-100">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Referencia bancaria</label>
                                        <input type="text" wire:model.defer="paymentForm.referencia_bancaria_pago_vaucher_credito" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs dark:border-white/10 dark:bg-slate-800 dark:text-slate-100">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Tasa BCV</label>
                                        <input type="number" step="0.0001" wire:model.defer="paymentForm.tasa_bcv" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs dark:border-white/10 dark:bg-slate-800 dark:text-slate-100">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Monto abonado</label>
                                        <input type="number" step="0.01" wire:model.defer="paymentForm.monto_abonado_en_cuenta_vaucher_credito" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs dark:border-white/10 dark:bg-slate-800 dark:text-slate-100">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Fecha de pago voucher</label>
                                        <input type="date" wire:model.defer="paymentForm.fecha_pago_vaucher_credito" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs dark:border-white/10 dark:bg-slate-800 dark:text-slate-100">
                                    </div>
                                </div>
                            </div>

                            <div class="sticky bottom-0 z-10 flex items-center justify-between rounded-2xl border border-slate-200/80 bg-white/90 px-4 py-3 backdrop-blur dark:border-white/10 dark:bg-slate-900/90">
                                <div class="space-y-1">
                                    <p class="text-xs text-slate-500 dark:text-slate-400">Guardar aquí no cierra la modal.</p>
                                    <p wire:loading.delay.short wire:target="savePaymentTab" class="text-[11px] font-medium text-emerald-700 dark:text-emerald-300">
                                        Guardando Formulario 1 (Pago)...
                                    </p>
                                    <p x-show="agoText.pago" x-cloak x-transition.opacity class="inline-flex items-center gap-1 text-[11px] font-medium text-sky-600 dark:text-sky-400">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="size-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5Z" clip-rule="evenodd"/></svg>
                                        <span x-text="'Último guardado ' + agoText.pago"></span>
                                    </p>
                                </div>
                                <button type="submit" wire:loading.attr="disabled" wire:target="savePaymentTab" class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-semibold text-white transition hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-70">
                                    <svg wire:loading wire:target="savePaymentTab" class="size-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.37 0 0 5.37 0 12h4z"></path>
                                    </svg>
                                    <span wire:loading.remove wire:target="savePaymentTab">Guardar formulario de pago</span>
                                    <span wire:loading wire:target="savePaymentTab">Guardando...</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <div x-show="activeTab==='estatus'" x-cloak x-transition.opacity.duration.200ms>
                        <form wire:submit.prevent="saveStatusTab" class="space-y-4">
                            <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
                                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Gestión de estatus de voucher</p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Ideal para cambios operativos globales en todos los resultados de la búsqueda.</p>
                            </div>

                            <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Estatus de voucher</label>
                                        <select wire:model="statusForm.estatus_vaucher" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs dark:border-white/10 dark:bg-slate-800 dark:text-slate-100">
                                            <option value="">Seleccione...</option>
                                            @foreach ($this->statusVaucherOptions() as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="rounded-xl border border-amber-200/80 bg-amber-50/70 p-3 dark:border-amber-400/30 dark:bg-amber-500/10">
                                        <p class="text-xs font-semibold text-amber-700 dark:text-amber-300">Regla importante</p>
                                        <p class="mt-1 text-[11px] text-amber-700/90 dark:text-amber-200">Si seleccionas <strong>Anulado</strong>, debes registrar observación; también se actualizan estados relacionados automáticamente.</p>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Observación (obligatoria si Anulado)</label>
                                        <textarea wire:model.defer="statusForm.observacion_anulacion" rows="5" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs dark:border-white/10 dark:bg-slate-800 dark:text-slate-100" placeholder="Motivo, detalle o contexto del cambio..."></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="sticky bottom-0 z-10 flex items-center justify-between rounded-2xl border border-slate-200/80 bg-white/90 px-4 py-3 backdrop-blur dark:border-white/10 dark:bg-slate-900/90">
                                <div class="space-y-1">
                                    <p class="text-xs text-slate-500 dark:text-slate-400">Los cambios aplican a todos los vouchers encontrados.</p>
                                    <p wire:loading.delay.short wire:target="saveStatusTab" class="text-[11px] font-medium text-amber-700 dark:text-amber-300">
                                        Guardando Formulario 2 (Estatus)...
                                    </p>
                                    <p x-show="agoText.estatus" x-cloak x-transition.opacity class="inline-flex items-center gap-1 text-[11px] font-medium text-sky-600 dark:text-sky-400">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="size-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5Z" clip-rule="evenodd"/></svg>
                                        <span x-text="'Último guardado ' + agoText.estatus"></span>
                                    </p>
                                </div>
                                <button type="submit" wire:loading.attr="disabled" wire:target="saveStatusTab" class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-semibold text-white transition hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-70">
                                    <svg wire:loading wire:target="saveStatusTab" class="size-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.37 0 0 5.37 0 12h4z"></path>
                                    </svg>
                                    <span wire:loading.remove wire:target="saveStatusTab">Guardar estatus</span>
                                    <span wire:loading wire:target="saveStatusTab">Guardando...</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <div x-show="activeTab==='comision'" x-cloak x-transition.opacity.duration.200ms>
                        <form wire:submit.prevent="saveCommissionTab" class="space-y-4">
                            <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
                                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Cálculo masivo de comisión</p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Define porcentaje y recalcula automáticamente en todos los resultados de la búsqueda.</p>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="rounded-2xl border border-slate-200/80 bg-white p-4 dark:border-white/10 dark:bg-slate-900/70">
                                    <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Porcentaje de comisión (%)</label>
                                    <input type="number" step="0.0001" min="0" wire:model.defer="commissionForm.porcentaje_comision" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs dark:border-white/10 dark:bg-slate-800 dark:text-slate-100">
                                </div>
                                <div class="rounded-2xl border border-indigo-200/80 bg-indigo-50/70 p-4 dark:border-indigo-400/30 dark:bg-indigo-500/10">
                                    <label class="mb-1 block text-xs font-medium text-indigo-700 dark:text-indigo-300">Total comisión actual</label>
                                    <input type="text" value="US$ {{ number_format($this->resultTotalMontoComision, 2, ',', '.') }}" disabled class="w-full rounded-xl border border-indigo-200 bg-white px-3 py-2 text-sm font-semibold text-indigo-700 dark:border-indigo-500/30 dark:bg-slate-900 dark:text-indigo-300">
                                </div>
                            </div>

                            <div class="sticky bottom-0 z-10 flex items-center justify-between rounded-2xl border border-slate-200/80 bg-white/90 px-4 py-3 backdrop-blur dark:border-white/10 dark:bg-slate-900/90">
                                <div class="space-y-1">
                                    <p class="text-xs text-slate-500 dark:text-slate-400">Recalcula comisión sin salir de la modal.</p>
                                    <p wire:loading.delay.short wire:target="saveCommissionTab" class="text-[11px] font-medium text-indigo-700 dark:text-indigo-300">
                                        Guardando Formulario 3 (Comisión)...
                                    </p>
                                    <p x-show="agoText.comision" x-cloak x-transition.opacity class="inline-flex items-center gap-1 text-[11px] font-medium text-sky-600 dark:text-sky-400">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="size-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5Z" clip-rule="evenodd"/></svg>
                                        <span x-text="'Último guardado ' + agoText.comision"></span>
                                    </p>
                                </div>
                                <button type="submit" wire:loading.attr="disabled" wire:target="saveCommissionTab" class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-semibold text-white transition hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-70">
                                    <svg wire:loading wire:target="saveCommissionTab" class="size-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.37 0 0 5.37 0 12h4z"></path>
                                    </svg>
                                    <span wire:loading.remove wire:target="saveCommissionTab">Calcular y guardar comisión</span>
                                    <span wire:loading wire:target="saveCommissionTab">Guardando...</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <div x-show="activeTab==='pendiente'" x-cloak x-transition.opacity.duration.200ms class="rounded-2xl border border-dashed border-slate-300/80 bg-slate-50 px-4 py-10 text-center dark:border-white/15 dark:bg-slate-800/40">
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-200">Formulario 4 pendiente por definición funcional.</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Cuando me indiques los campos, lo implementamos en esta pestaña.</p>
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-filament-panels::page>
