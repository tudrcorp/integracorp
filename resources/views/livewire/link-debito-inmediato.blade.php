<div class="flex items-center justify-center min-h-screen p-4 md:p-8">
    <!-- Main Card Container -->
    <div
        class="w-full max-w-5xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 shadow-sm rounded-xl overflow-hidden flex flex-col md:flex-row">

        <!-- Sidebar / Order Summary (Flux Style) -->
        <div
            class="w-full md:w-5/12 bg-zinc-50 dark:bg-zinc-800/50 p-8 flex flex-col justify-between border-b md:border-b-0 md:border-r border-zinc-200 dark:border-zinc-800">
            <div class="space-y-10">
                <!-- Logo -->
                <div class="flex items-start">
                    <img src="{{ asset('image/logoNewPdf.png') }}" alt="Logo Agencia TDG"
                        class="h-10 w-auto object-contain opacity-90">
                </div>

                <!-- Price Info -->
                <div class="space-y-2">
                    <p class="text-xs font-bold uppercase tracking-widest text-zinc-400 dark:text-zinc-500">Total a
                        pagar</p>
                    <div class="flex items-baseline gap-2">
                        <span
                            class="text-5xl font-black text-zinc-900 dark:text-zinc-50 tracking-tighter">$150.00</span>
                        <span class="text-zinc-400 font-medium">USD</span>
                    </div>
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">≈ Bs. 5.430,00 VES</p>
                </div>

                <!-- Item Detail -->
                <div class="space-y-4 pt-6 border-t border-zinc-200 dark:border-zinc-800">
                    <div class="flex items-start gap-4">
                        <div
                            class="size-12 bg-zinc-200 dark:bg-zinc-700 rounded-lg flex-shrink-0 flex items-center justify-center text-zinc-600 dark:text-zinc-400">
                            <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-semibold text-zinc-900 dark:text-zinc-100 uppercase">Plan Especial</h3>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400 leading-snug">Acceso completo a
                                herramientas de gestión, CRM y soporte prioritario.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Sidebar -->
            {{-- <div class="mt-12 space-y-4">
                <div class="flex items-center gap-2 text-zinc-500 dark:text-zinc-400 text-xs font-medium">
                    <svg class="size-4 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                            clip-rule="evenodd" />
                    </svg>
                    Pago seguro cifrado (AES-256)
                </div>
            </div> --}}
            <!-- Footer Sidebar RESALTADO -->
            <div class="mt-12">
                <div
                    class="relative p-5 rounded-2xl bg-white dark:bg-zinc-800 overflow-hidden shadow-sm dark:shadow-lg border border-zinc-200 dark:border-zinc-700 transition-colors duration-300">
                    <!-- Decoración visual de fondo -->
                    <div class="absolute -right-4 -top-4 size-24 bg-[#23bdf2]/10 rounded-full blur-2xl"></div>
            
                    <div class="relative space-y-4">
                        <div class="flex items-center gap-3">
                            <div class="size-8 rounded-full bg-emerald-500/20 flex items-center justify-center shrink-0">
                                <flux:icon.shield-check variant="solid" class="size-5 text-emerald-500" />
                            </div>
                            <div class="space-y-0.5">
                                <p class="text-xs font-bold text-zinc-900 dark:text-zinc-100 uppercase tracking-wider">Conexión Segura</p>
                                <p class="text-[10px] text-zinc-900 dark:text-zinc-100 font-medium">Cifrado de grado bancario (AES-256)</p>
                            </div>
                        </div>
            
                        <div
                            class="pt-3 border-t border-zinc-800 dark:border-zinc-700 flex items-center justify-between gap-4 text-[10px] font-bold text-zinc-500 uppercase tracking-[0.15em]">
                            <div class="flex items-center gap-1.5">
                                <span class="size-1.5 rounded-full bg-emerald-500 dark:bg-emerald-500 animate-pulse"></span>
                                SSL ACTIVO
                            </div>
                            <div class="flex items-center gap-1">
                                <flux:icon.lock-closed class="size-3" />
                                PCI COMPLIANT
                            </div>
                        </div>
                    </div>
                </div>
            
                <p class="mt-4 text-[10px] text-center text-zinc-400 dark:text-zinc-500 font-medium px-2 leading-relaxed italic">
                    "Tu seguridad es nuestra prioridad. No almacenamos datos sensibles de tu cuenta bancaria."
                </p>
            </div>
        </div>

        <!-- Main Form Section (Flux UI Components) -->
        <div class="w-full md:w-7/12 p-8 md:p-12">
            <div class="max-w-md mx-auto space-y-8">
                <header>
                    <h2 class="text-2xl font-bold text-zinc-900 dark:text-zinc-50 tracking-tight">Detalles de
                        Facturación</h2>
                    <p class="text-zinc-500 dark:text-zinc-400 text-sm mt-1">Completa los datos para procesar tu
                        pago mediante débito inmediato.</p>
                </header>

                <!-- Simulación de Componentes Flux UI -->
                <form wire:submit.prevent="processPayment" class="space-y-6">

                    <!-- Grid para Nombre/Apellido -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <flux:input label="Nombre" placeholder="Ej. Carlos" wire:model="first_name" />
                        </div>
                        <div class="space-y-2">
                            <flux:input label="Apellido" placeholder="Ej. Sosa" wire:model="last_name" />
                        </div>
                    </div>

                    <flux:radio.group wire:model="document_type" label="Tipo de Documento" variant="cards" class="max-sm:flex-col">
                        <flux:radio value="V" label="Venezolano" checked />
                        <flux:radio value="E" label="Extranjero" />
                        <flux:radio value="J" label="Jurídico" />
                    </flux:radio.group>


                    <!-- Cédula -->
                    <div class="space-y-2">
                        <flux:input label="Número de Documento" placeholder="12345678" wire:model="ci"/>
                    </div>

                    <!-- Banco (Flux Select Style) -->
                    <div class="space-y-2">
                        <flux:field>
                            <flux:select label="Banco" wire:model="bank_id" searchable variant="listbox" placeholder="selecciona un banco" class="!rounded-lg !shadow-md">
                                <flux:select.option value="0172">0172 - Bancamiga</flux:select.option>
                                <flux:select.option value="0102">0102 - Banco de Venezuela</flux:select.option>
                                <flux:select.option value="0104">0104 - Banco Venezolano de Credito</flux:select.option>
                                <flux:select.option value="0105">0105 - Banco Mercantil</flux:select.option>
                                <flux:select.option value="0108">0108 - Banco Provincial</flux:select.option>
                                <flux:select.option value="0114">0114 - Bancaribe</flux:select.option>
                                <flux:select.option value="0115">0115 - Banco Exterior</flux:select.option>
                                <flux:select.option value="0128">0128 - Banco Caroni</flux:select.option>
                                <flux:select.option value="0134">0134 - Banesco</flux:select.option>
                                <flux:select.option value="0137">0137 - Banco Sofitasa</flux:select.option>
                                <flux:select.option value="0138">0138 - Banco Plaza</flux:select.option>
                                <flux:select.option value="0146">0146 - Bangente</flux:select.option>
                                <flux:select.option value="0151">0151 - Fondo Comun</flux:select.option>
                                <flux:select.option value="0156">0156 - 100% Banco</flux:select.option>
                                <flux:select.option value="0157">0157 - Banco Del Sur</flux:select.option>
                                <flux:select.option value="0163">0163 - Banco Del Tesoro</flux:select.option>
                                <flux:select.option value="0168">0168 - Bancrecer</flux:select.option>
                                <flux:select.option value="0169">0169 - R4</flux:select.option>
                                <flux:select.option value="0171">0171 - Banco Activo</flux:select.option>
                                <flux:select.option value="0174">0174 - Banplus</flux:select.option>
                                <flux:select.option value="0175">0175 - Banco Digital de los Trabajadores</flux:select.option>
                                <flux:select.option value="0177">0177 - Banfanb</flux:select.option>
                                <flux:select.option value="0178">0178 - N58 Banco Digital</flux:select.option>
                                <flux:select.option value="0191">0191 - Banco Nacional de Crédito</flux:select.option>
                                <flux:select.option value="0601">0601 - Instituto Minucipal de Credito Popular
                                </flux:select.option>
                            </flux:select>
                        </flux:field>
                    </div>

                    <!-- Número de Cuenta -->
                    <div class="space-y-2">
                        <flux:input label="Número de Cuenta" wire:model="account_number" icon:trailing="credit-card" mask="9999 9999 9999 9999 9999" placeholder="4444-4444-4444-4444" />
                    </div>

                    <!-- Botón Principal (Flux Button Native Style) -->
                    <div class="pt-4">
                        <flux:button type="button" wire:click="processPayment"
                            class="w-full h-11 !bg-[#23bdf2] dark:bg-white text-white dark:text-zinc-900 font-semibold text-sm rounded-lg hover:bg-zinc-800 dark:hover:bg-zinc-100 transition-all shadow-md active:scale-[0.98] disabled:opacity-70 disabled:pointer-events-none flex items-center justify-center gap-2">
                            <span
                                class="flex items-center justify-center !text-white dark:!text-zinc-900 uppercase">Finalizar
                                Pago</span>
                        </flux:button>
                    </div>

                </form>

                <!-- Sellos de Confianza (Resaltados) -->
                <div class="pt-6">
                    <div class="flex flex-col items-center gap-4">
                        <div
                            class="flex items-center gap-8 opacity-60 dark:opacity-40 grayscale hover:grayscale-0 transition-all duration-500">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/5/5e/Visa_Inc._logo.svg" alt="Visa" class="h-4">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/2/2a/Mastercard-logo.svg" alt="Mastercard"
                                class="h-6">
                            <div class="h-6 w-px bg-zinc-200 dark:bg-zinc-700"></div>
                            <img src="https://upload.wikimedia.org/wikipedia/commons/b/b5/PayPal.svg" alt="PayPal" class="h-5">
                        </div>
                        <div class="flex items-center gap-2 text-zinc-400 dark:text-zinc-500">
                            <flux:icon.shield-check variant="solid" class="size-4" />
                            <p class="text-[10px] font-bold uppercase tracking-[0.2em]">Secure Checkout by TuDrEnCasa</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>