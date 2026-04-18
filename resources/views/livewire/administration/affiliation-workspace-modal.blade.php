@php
    $paymentMethod = (string) ($paymentForm['payment_method'] ?? '');
    $multipleUsdMethod = (string) ($paymentForm['payment_method_usd'] ?? '');

    $showBcv = in_array($paymentMethod, ['MULTIPLE', 'PAGO MOVIL VES', 'TRANSFERENCIA VES'], true);
    $showUsdSimple = in_array($paymentMethod, ['ZELLE', 'TRANSFERENCIA US$', 'EFECTIVO US$', 'LINK DE PAGO'], true);
    $showVesSimple = in_array($paymentMethod, ['PAGO MOVIL VES', 'TRANSFERENCIA VES'], true);
    $showMultiple = $paymentMethod === 'MULTIPLE';

    $iosInputClass = 'w-full rounded-2xl border border-slate-200/90 bg-white/95 px-3.5 py-2.5 text-[13px] font-medium text-slate-800 shadow-sm transition placeholder:text-slate-400 focus:border-sky-500 focus:ring-4 focus:ring-sky-500/15 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:border-sky-400 dark:focus:ring-sky-400/20';
    $iosInputReadonlyClass = 'w-full rounded-2xl border border-slate-200/90 bg-slate-100 px-3.5 py-2.5 text-[13px] font-semibold text-slate-700 shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200';
    $iosSelectClass = 'w-full rounded-2xl border border-slate-200/90 bg-white/95 px-3.5 py-2.5 text-[13px] font-medium text-slate-800 shadow-sm transition focus:border-sky-500 focus:ring-4 focus:ring-sky-500/15 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-sky-400 dark:focus:ring-sky-400/20';
    $iosTextareaClass = 'w-full rounded-2xl border border-slate-200/90 bg-white/95 px-3.5 py-2.5 text-[13px] font-medium text-slate-800 shadow-sm transition placeholder:text-slate-400 focus:border-sky-500 focus:ring-4 focus:ring-sky-500/15 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:border-sky-400 dark:focus:ring-sky-400/20';
    $iosFileClass = 'w-full rounded-2xl border border-slate-200/90 bg-white/95 px-2 py-2 text-[13px] font-medium text-slate-700 shadow-sm transition file:mr-3 file:rounded-full file:border-0 file:px-3.5 file:py-1.5 file:text-xs file:font-semibold hover:file:brightness-95 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';
    $iosFileUsdClass = $iosFileClass.' file:bg-sky-600 file:text-white';
    $iosFileVesClass = $iosFileClass.' file:bg-emerald-600 file:text-white';
    $iosLabelClass = 'text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400';
@endphp

<div x-data="{ tab: 'resumen' }" class="rounded-3xl border border-slate-200/70 bg-gradient-to-br from-white via-slate-50/80 to-white p-4 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.45)] dark:border-slate-700/70 dark:from-slate-900 dark:via-slate-900/95 dark:to-slate-900">
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <button type="button" x-on:click="tab = 'resumen'" x-bind:class="tab === 'resumen' ? 'bg-sky-600 text-white shadow-sky-500/30 scale-[1.01]' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200 scale-100'" class="rounded-full px-4 py-2 text-sm font-semibold shadow transition-all duration-250 ease-out active:scale-[0.98]">
            Datos del afiliado
        </button>
        <button type="button" x-on:click="tab = 'pago'" x-bind:class="tab === 'pago' ? 'bg-emerald-600 text-white shadow-emerald-500/30 scale-[1.01]' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200 scale-100'" class="rounded-full px-4 py-2 text-sm font-semibold shadow transition-all duration-250 ease-out active:scale-[0.98]">
            Cargar comprobante
        </button>
        <button type="button" x-on:click="tab = 'pagos'" x-bind:class="tab === 'pagos' ? 'bg-amber-600 text-white shadow-amber-500/30 scale-[1.01]' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200 scale-100'" class="rounded-full px-4 py-2 text-sm font-semibold shadow transition-all duration-250 ease-out active:scale-[0.98]">
            Pagos y aprobación
        </button>
    </div>

    <div class="h-[68vh] max-h-[42rem] min-h-[30rem] overflow-y-auto pr-1 sm:h-[36rem]">
    <section x-show="tab === 'resumen'" x-cloak class="space-y-4">
        <div class="rounded-2xl border border-slate-200/70 bg-white/90 p-4 dark:border-slate-700 dark:bg-slate-900/70">
            <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Información principal</h3>
            <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">Resumen esencial para consulta rápida.</p>
            <dl class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/70"><dt class="text-xs text-slate-500 dark:text-slate-400">Código</dt><dd class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $affiliation->code }}</dd></div>
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/70"><dt class="text-xs text-slate-500 dark:text-slate-400">Estatus</dt><dd class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $affiliation->status }}</dd></div>
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/70"><dt class="text-xs text-slate-500 dark:text-slate-400">Titular</dt><dd class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $affiliation->full_name_ti }}</dd></div>
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/70"><dt class="text-xs text-slate-500 dark:text-slate-400">Pagador</dt><dd class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $affiliation->full_name_payer }}</dd></div>
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/70"><dt class="text-xs text-slate-500 dark:text-slate-400">Plan</dt><dd class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $affiliation->plan?->description ?? 'N/A' }}</dd></div>
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/70"><dt class="text-xs text-slate-500 dark:text-slate-400">Cobertura</dt><dd class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $affiliation->coverage?->price ? $affiliation->coverage->price.' US$' : 'N/A' }}</dd></div>
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/70"><dt class="text-xs text-slate-500 dark:text-slate-400">Frecuencia de pago</dt><dd class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $affiliation->payment_frequency }}</dd></div>
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/70"><dt class="text-xs text-slate-500 dark:text-slate-400">Total a pagar</dt><dd class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ number_format((float) $affiliation->total_amount, 2) }} US$</dd></div>
            </dl>
        </div>
    </section>

    <section x-show="tab === 'pago'" x-cloak class="space-y-4">
        <div class="rounded-2xl border border-emerald-200/70 bg-white/90 p-4 dark:border-emerald-700/40 dark:bg-slate-900/70">
            <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Formulario de comprobante</h3>
            <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">Misma funcionalidad del formulario original, reestructurada con UX iOS y validación contextual.</p>

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="space-y-1">
                    <span class="{{ $iosLabelClass }}">Total a pagar</span>
                    <input type="number" step="0.01" wire:model.live="paymentForm.total_amount" class="{{ $iosInputClass }}" />
                    @error('paymentForm.total_amount') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                </label>
                <label class="space-y-1">
                    <span class="{{ $iosLabelClass }}">Fecha del comprobante</span>
                    <input type="date" wire:model.live="paymentForm.date_payment_voucher" class="{{ $iosInputClass }}" />
                    @error('paymentForm.date_payment_voucher') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                </label>
            </div>

            <label class="mt-4 block space-y-1">
                <span class="{{ $iosLabelClass }}">Método de pago</span>
                <select wire:model.live="paymentForm.payment_method" class="{{ $iosSelectClass }}">
                    <option value="">Seleccione</option>
                    @foreach ($paymentMethodOptions as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('paymentForm.payment_method') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
            </label>

            @if ($showBcv)
                <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50/80 p-3 dark:border-slate-700 dark:bg-slate-800/70">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="space-y-1">
                            <span class="{{ $iosLabelClass }}">Tasa BCV</span>
                            <input type="number" step="0.01" wire:model.live="paymentForm.tasa_bcv" class="{{ $iosInputClass }}" />
                            @error('paymentForm.tasa_bcv') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="space-y-1">
                            <span class="{{ $iosLabelClass }}">Monto a pagar en VES</span>
                            <input type="number" step="0.01" wire:model.live="paymentForm.pay_amount_ves" readonly class="{{ $iosInputReadonlyClass }}" />
                            @error('paymentForm.pay_amount_ves') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                    </div>
                </div>
            @endif

            @if ($showUsdSimple)
                <div class="mt-4 rounded-2xl border border-sky-200 bg-sky-50/80 p-3 dark:border-sky-700/40 dark:bg-sky-900/10">
                    <p class="mb-3 text-xs font-semibold text-sky-700 dark:text-sky-300">Información de pago en US$</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @if (in_array($paymentMethod, ['ZELLE', 'TRANSFERENCIA US$', 'LINK DE PAGO'], true))
                            <label class="space-y-1">
                                <span class="{{ $iosLabelClass }}">Nombre del titular</span>
                                <input type="text" wire:model.live="paymentForm.name_ti_usd" class="{{ $iosInputClass }}" />
                                @error('paymentForm.name_ti_usd') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>
                        @endif

                        @if (in_array($paymentMethod, ['TRANSFERENCIA US$', 'EFECTIVO US$'], true))
                            <label class="space-y-1">
                                <span class="{{ $iosLabelClass }}">Banco US$</span>
                                <select wire:model.live="paymentForm.bank_usd" class="{{ $iosSelectClass }}">
                                    <option value="">Seleccione</option>
                                    @foreach (($paymentMethod === 'EFECTIVO US$' ? $usdBankCashOptions : $usdBankOptions) as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('paymentForm.bank_usd') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>
                        @endif

                        @if (in_array($paymentMethod, ['ZELLE', 'LINK DE PAGO'], true))
                            <label class="space-y-1">
                                <span class="{{ $iosLabelClass }}">Referencia US$</span>
                                <input type="text" wire:model.live="paymentForm.reference_payment_usd" class="{{ $iosInputClass }}" />
                                @error('paymentForm.reference_payment_usd') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>
                        @endif

                        <label class="space-y-1 sm:col-span-2">
                            <span class="{{ $iosLabelClass }}">Comprobante US$</span>
                            <input type="file" wire:model="paymentForm.document_usd" class="{{ $iosFileUsdClass }}" />
                            @error('paymentForm.document_usd') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                    </div>
                </div>
            @endif

            @if ($showVesSimple)
                <div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50/80 p-3 dark:border-emerald-700/40 dark:bg-emerald-900/10">
                    <p class="mb-3 text-xs font-semibold text-emerald-700 dark:text-emerald-300">Información de pago en VES</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="space-y-1">
                            <span class="{{ $iosLabelClass }}">Banco VES</span>
                            <select wire:model.live="paymentForm.bank_ves" class="{{ $iosSelectClass }}">
                                <option value="">Seleccione</option>
                                @foreach ($vesBankOptions as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('paymentForm.bank_ves') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="space-y-1">
                            <span class="{{ $iosLabelClass }}">Referencia VES</span>
                            <input type="text" wire:model.live="paymentForm.reference_payment_ves" maxlength="6" class="{{ $iosInputClass }}" />
                            @error('paymentForm.reference_payment_ves') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="space-y-1 sm:col-span-2">
                            <span class="{{ $iosLabelClass }}">Comprobante VES</span>
                            <input type="file" wire:model="paymentForm.document_ves" class="{{ $iosFileVesClass }}" />
                            @error('paymentForm.document_ves') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                    </div>
                </div>
            @endif

            @if ($showMultiple)
                <div class="mt-4 rounded-2xl border border-violet-200 bg-violet-50/80 p-3 dark:border-violet-700/40 dark:bg-violet-900/10">
                    <p class="mb-3 text-xs font-semibold text-violet-700 dark:text-violet-300">Pago múltiple (US$ + VES)</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="space-y-1">
                            <span class="{{ $iosLabelClass }}">Método US$</span>
                            <select wire:model.live="paymentForm.payment_method_usd" class="{{ $iosSelectClass }}">
                                <option value="">Seleccione</option>
                                @foreach ($usdMethodOptions as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('paymentForm.payment_method_usd') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="space-y-1">
                            <span class="{{ $iosLabelClass }}">Monto US$</span>
                            <input type="number" step="0.01" wire:model.live="paymentForm.pay_amount_usd" class="{{ $iosInputClass }}" />
                            @error('paymentForm.pay_amount_usd') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>

                        @if (in_array($multipleUsdMethod, ['ZELLE', 'TRANSFERENCIA US$'], true))
                            <label class="space-y-1">
                                <span class="{{ $iosLabelClass }}">Titular US$</span>
                                <input type="text" wire:model.live="paymentForm.name_ti_usd" class="{{ $iosInputClass }}" />
                                @error('paymentForm.name_ti_usd') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>
                        @endif

                        @if (in_array($multipleUsdMethod, ['TRANSFERENCIA US$', 'EFECTIVO US$'], true))
                            <label class="space-y-1">
                                <span class="{{ $iosLabelClass }}">Banco US$</span>
                                <select wire:model.live="paymentForm.bank_usd" class="{{ $iosSelectClass }}">
                                    <option value="">Seleccione</option>
                                    @foreach ($usdBankOptions as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('paymentForm.bank_usd') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>
                        @endif

                        @if ($multipleUsdMethod === 'ZELLE')
                            <label class="space-y-1">
                                <span class="{{ $iosLabelClass }}">Referencia US$</span>
                                <input type="text" wire:model.live="paymentForm.reference_payment_usd" class="{{ $iosInputClass }}" />
                                @error('paymentForm.reference_payment_usd') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>
                        @endif

                        <label class="space-y-1">
                            <span class="{{ $iosLabelClass }}">Método VES</span>
                            <select wire:model.live="paymentForm.payment_method_ves" class="{{ $iosSelectClass }}">
                                <option value="">Seleccione</option>
                                @foreach ($vesMethodOptions as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('paymentForm.payment_method_ves') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="space-y-1">
                            <span class="{{ $iosLabelClass }}">Banco VES</span>
                            <select wire:model.live="paymentForm.bank_ves" class="{{ $iosSelectClass }}">
                                <option value="">Seleccione</option>
                                @foreach ($vesBankMultipleOptions as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('paymentForm.bank_ves') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="space-y-1">
                            <span class="{{ $iosLabelClass }}">Referencia VES</span>
                            <input type="text" wire:model.live="paymentForm.reference_payment_ves" maxlength="6" class="{{ $iosInputClass }}" />
                            @error('paymentForm.reference_payment_ves') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>

                        <label class="space-y-1 sm:col-span-2">
                            <span class="{{ $iosLabelClass }}">Comprobante US$</span>
                            <input type="file" wire:model="paymentForm.document_usd" class="{{ $iosFileUsdClass }}" />
                            @error('paymentForm.document_usd') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="space-y-1 sm:col-span-2">
                            <span class="{{ $iosLabelClass }}">Comprobante VES</span>
                            <input type="file" wire:model="paymentForm.document_ves" class="{{ $iosFileVesClass }}" />
                            @error('paymentForm.document_ves') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                    </div>
                </div>
            @endif

            <label class="mt-4 block space-y-1">
                <span class="{{ $iosLabelClass }}">Observaciones</span>
                <textarea rows="2" wire:model.live="paymentForm.observations_payment" class="{{ $iosTextareaClass }}"></textarea>
            </label>

            <div class="mt-4 flex justify-end">
                <button type="button" wire:click="savePayment" wire:loading.attr="disabled" wire:target="savePayment" class="inline-flex items-center gap-2 rounded-full bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-emerald-500/25 transition hover:bg-emerald-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="savePayment">Guardar comprobante</span>
                    <span wire:loading wire:target="savePayment">Guardando...</span>
                </button>
            </div>
        </div>
    </section>

    <section x-show="tab === 'pagos'" x-cloak class="space-y-4">
        <div class="rounded-2xl border border-amber-200/70 bg-white/90 p-4 dark:border-amber-700/40 dark:bg-slate-900/70">
            <p class="mb-4 text-xs font-semibold text-amber-700 dark:text-amber-300">Filtros rápidos</p>
            <div class="grid gap-4 sm:grid-cols-3">
                <label class="space-y-1">
                    <span class="{{ $iosLabelClass }}">Estatus</span>
                    <select wire:model.live="paymentsStatusFilter" class="{{ $iosSelectClass }}">
                        <option value="all">Todos</option>
                        <option value="PENDIENTE">Pendiente</option>
                        <option value="APROBADO">Aprobado</option>
                    </select>
                </label>
                <label class="space-y-1">
                    <span class="{{ $iosLabelClass }}">Método</span>
                    <select wire:model.live="paymentsMethodFilter" class="{{ $iosSelectClass }}">
                        <option value="all">Todos</option>
                        @foreach ($paymentMethodOptions as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="space-y-1">
                    <span class="{{ $iosLabelClass }}">Referencia / Factura</span>
                    <input type="text" wire:model.live.debounce.300ms="paymentsReferenceFilter" placeholder="Ej: 000123 o FAC-..." class="{{ $iosInputClass }}" />
                </label>
            </div>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-amber-200/70 bg-white/90 p-1.5 dark:border-amber-700/40 dark:bg-slate-900/70">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50/80 dark:bg-slate-800/80">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-300">Método</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-300">Banco</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-300">Referencia</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-300">Monto</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-300">Estatus</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-600 dark:text-slate-300">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($paidMemberships as $payment)
                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/60">
                            <td class="px-4 py-3 text-xs text-slate-700 dark:text-slate-200">{{ $payment->payment_method }}</td>
                            <td class="px-4 py-3 text-xs text-slate-700 dark:text-slate-200">{{ $payment->bank_usd !== 'N/A' ? $payment->bank_usd : $payment->bank_ves }}</td>
                            <td class="px-4 py-3 text-xs text-slate-700 dark:text-slate-200">{{ $payment->reference_payment_usd !== 'N/A' ? $payment->reference_payment_usd : $payment->reference_payment_ves }}</td>
                            <td class="px-4 py-3 text-xs text-slate-700 dark:text-slate-200">
                                {{ number_format((float) ($payment->pay_amount_usd ?? 0), 2) }} US$
                                @if (($payment->pay_amount_ves ?? 'N/A') !== 'N/A' && (float) $payment->pay_amount_ves > 0)
                                    <span class="block text-[11px] text-slate-500 dark:text-slate-400">{{ number_format((float) $payment->pay_amount_ves, 2) }} VES</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-1 text-[11px] font-semibold {{ $payment->status === 'APROBADO' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-700/30 dark:text-emerald-200' : 'bg-amber-100 text-amber-700 dark:bg-amber-700/30 dark:text-amber-200' }}">
                                    {{ $payment->status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if ($payment->status !== 'APROBADO')
                                    <button type="button" wire:click="openApprove({{ $payment->id }})" class="rounded-full bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-700">
                                        Aprobar
                                    </button>
                                @else
                                    <span class="text-[11px] text-slate-400">Aprobado</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-7 text-center text-sm text-slate-500 dark:text-slate-400">No hay pagos cargados para esta afiliación con los filtros actuales.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($approvingPaidMembershipId !== null)
            <div class="rounded-2xl border border-emerald-200/70 bg-emerald-50/70 p-6 dark:border-emerald-700/40 dark:bg-emerald-900/20">
                <h4 class="text-base font-semibold text-emerald-800 dark:text-emerald-200">Aprobar pago #{{ $approvingPaidMembershipId }}</h4>
                <p class="mb-4 mt-1 text-sm text-emerald-700/90 dark:text-emerald-300/90">Selecciona avisos de cobro (si aplica) y confirma la aprobación.</p>

                @if (count($availableCollections) > 0)
                    <div class="mb-5 rounded-2xl border border-emerald-200/70 bg-white/80 p-4 dark:border-emerald-700/40 dark:bg-slate-900/60">
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <span class="text-xs font-semibold uppercase tracking-[0.08em] text-emerald-700 dark:text-emerald-300">Avisos de cobro por pagar</span>
                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-700/30 dark:text-emerald-200">
                                Seleccionados: {{ count($approveCollections) }}
                            </span>
                        </div>

                        <div class="mb-4 grid gap-3 sm:grid-cols-[1fr_auto_auto]">
                            <input
                                type="text"
                                wire:model.live.debounce.250ms="approveCollectionsSearch"
                                placeholder="Buscar por ID o fecha"
                                class="{{ $iosInputClass }}"
                            />
                            <button
                                type="button"
                                wire:click="selectAllApproveCollections"
                                class="rounded-full border border-emerald-300 bg-emerald-50 px-4 py-2.5 text-xs font-semibold text-emerald-700 transition-all duration-200 ease-out hover:bg-emerald-100 hover:shadow-sm active:scale-[0.98] dark:border-emerald-600/60 dark:bg-emerald-900/20 dark:text-emerald-200 dark:hover:bg-emerald-900/35"
                            >
                                Seleccionar todo
                            </button>
                            <button
                                type="button"
                                wire:click="clearApproveCollections"
                                class="rounded-full border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-semibold text-slate-700 transition-all duration-200 ease-out hover:bg-slate-100 hover:shadow-sm active:scale-[0.98] dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                            >
                                Limpiar
                            </button>
                        </div>

                        <div class="max-h-56 space-y-2.5 overflow-y-auto rounded-xl border border-emerald-200/60 bg-white/70 p-2.5 dark:border-emerald-700/30 dark:bg-slate-900/60">
                            @forelse ($this->filteredAvailableCollections as $collectionId => $nextPaymentDate)
                                <label
                                    x-data="{ pulse: false }"
                                    class="group flex cursor-pointer items-center justify-between gap-3 rounded-xl border px-3.5 py-3 transition-all duration-220 ease-out motion-safe:hover:-translate-y-[1px] motion-safe:hover:shadow-md
                                    {{ in_array((int) $collectionId, $approveCollections, true)
                                        ? 'border-emerald-300 bg-emerald-50/70 shadow-sm ring-2 ring-emerald-500/20 dark:border-emerald-500/60 dark:bg-emerald-900/25 dark:ring-emerald-400/25'
                                        : 'border-slate-200/80 bg-white hover:border-emerald-300 hover:bg-emerald-50/40 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-emerald-500/50 dark:hover:bg-emerald-900/20'
                                    }}"
                                >
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">ID {{ $collectionId }}</p>
                                        <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $nextPaymentDate }}</p>
                                    </div>
                                    <div class="relative">
                                        <input
                                            type="checkbox"
                                            value="{{ $collectionId }}"
                                            wire:model.live="approveCollections"
                                            x-on:change="if ($event.target.checked) { pulse = true; setTimeout(() => pulse = false, 360) }"
                                            class="peer size-5 rounded border-slate-300 text-emerald-600 transition-transform duration-150 group-hover:scale-105 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-800"
                                        />
                                        <span
                                            x-show="pulse"
                                            x-transition.opacity.duration.150ms
                                            class="pointer-events-none absolute -inset-1 rounded-full bg-emerald-400/40 animate-ping"
                                        ></span>
                                        <span class="pointer-events-none absolute -right-1.5 -top-1.5 inline-flex h-4 w-4 items-center justify-center rounded-full bg-emerald-600 text-[10px] font-bold text-white opacity-0 scale-75 transition-all duration-200 peer-checked:opacity-100 peer-checked:scale-100">
                                            ✓
                                        </span>
                                    </div>
                                </label>
                            @empty
                                <div class="rounded-xl border border-dashed border-slate-300 px-3 py-6 text-center text-sm text-slate-500 dark:border-slate-600 dark:text-slate-400">
                                    No hay resultados para la búsqueda actual.
                                </div>
                            @endforelse
                        </div>
                    </div>
                @else
                    <p class="mb-4 text-sm text-emerald-800 dark:text-emerald-200">No hay avisos pendientes, se aplicará aprobación directa.</p>
                @endif

                <div class="flex flex-wrap items-center justify-end gap-2.5">
                    <button type="button" wire:click="cancelApprove" class="rounded-full bg-slate-200 px-5 py-2.5 text-xs font-semibold text-slate-700 transition-all duration-200 ease-out hover:bg-slate-300 hover:shadow-sm active:scale-[0.98] dark:bg-slate-700 dark:text-slate-100 dark:hover:bg-slate-600">Cancelar</button>
                    <button type="button" wire:click="approvePayment" wire:loading.attr="disabled" wire:target="approvePayment" class="rounded-full bg-emerald-600 px-5 py-2.5 text-xs font-semibold text-white transition-all duration-200 ease-out hover:bg-emerald-700 hover:shadow-md hover:shadow-emerald-500/25 active:scale-[0.98] disabled:opacity-60">
                        <span wire:loading.remove wire:target="approvePayment">Confirmar aprobación</span>
                        <span wire:loading wire:target="approvePayment">Procesando...</span>
                    </button>
                </div>
            </div>
        @endif
    </section>
    </div>
</div>

