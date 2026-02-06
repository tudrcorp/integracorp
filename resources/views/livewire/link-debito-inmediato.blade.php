<div class="flex items-center justify-center min-h-screen bg-zinc-50 dark:bg-zinc-950 p-0 md:p-6 lg:p-8">
    <!-- Main Card Container: Ajustado con max-h para evitar el efecto infinito -->
    <div
        class="w-full max-w-5xl md:max-h-[850px] bg-white dark:bg-zinc-900 border-none md:border md:border-zinc-200 md:dark:border-zinc-800 shadow-none md:shadow-xl md:rounded-[2rem] overflow-hidden flex flex-col md:flex-row md:h-screen md:h-auto rounded-2xl">

        <!-- Sidebar / Order Summary (Compacto en móvil) -->
        <div
            class="w-full md:w-[42%] bg-zinc-100/80 dark:bg-zinc-800/40 p-6 md:p-10 flex flex-col justify-between border-b md:border-b-0 md:border-r border-zinc-200/60 dark:border-zinc-800/60 overflow-y-auto">
            
            <div class="space-y-6 md:space-y-12">
                <!-- Logo -->
                <div class="flex items-center justify-between md:block">
                    <img src="{{ asset('image/logoNewPdf.png') }}" alt="Logo"
                        class="h-7 md:h-10 w-auto object-contain opacity-90">
                    
                    <!-- Badge móvil de seguridad rápida -->
                    <div class="md:hidden flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20">
                        <flux:icon.shield-check variant="solid" class="size-3.5 text-emerald-600 dark:text-emerald-400" />
                        <span class="text-[9px] font-bold text-emerald-700 dark:text-emerald-400 uppercase tracking-tight">Seguro</span>
                    </div>
                </div>

                <!-- Price Info -->
                <div class="flex flex-row md:flex-col items-center md:items-start justify-between md:justify-start gap-2">
                    <div class="space-y-0.5">
                        <p class="hidden md:block text-[10px] font-bold uppercase tracking-[0.2em] text-zinc-400 dark:text-zinc-500">Monto Final</p>
                        <h1 class="text-3xl md:text-5xl font-black text-zinc-900 dark:text-zinc-50 tracking-tighter">
                            $150<span class="text-xl md:text-2xl font-bold">.00</span>
                        </h1>
                        <p class="text-[10px] md:text-xs font-medium text-zinc-500 dark:text-zinc-400 opacity-80">≈ Bs. 5.430,00 VES</p>
                    </div>
                    
                    <div class="md:pt-6 w-auto md:w-full">
                        <div class="flex items-center gap-3 px-4 py-2 md:p-4 rounded-2xl bg-white dark:bg-zinc-800 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
                            <div class="hidden md:flex size-10 bg-[#23bdf2]/10 rounded-xl items-center justify-center text-[#23bdf2]">
                                <flux:icon.lock-open class="size-5" />
                            </div>
                            <div class="text-right md:text-left">
                                <h3 class="font-bold text-zinc-900 dark:text-zinc-100 text-[11px] md:text-sm uppercase tracking-tight">Plan Especial</h3>
                                <p class="hidden md:block text-[10px] text-zinc-500 dark:text-zinc-400">Acceso VIP y soporte 24/7</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Trust Footer (Desktop Only) -->
            <div class="hidden md:block mt-8">
                <div class="relative p-5 rounded-2xl bg-zinc-900 dark:bg-zinc-800/80 overflow-hidden border border-zinc-800 shadow-2xl">
                    <div class="absolute -right-4 -top-4 size-24 bg-[#23bdf2]/10 rounded-full blur-2xl"></div>
                    <div class="relative space-y-4">
                        <div class="flex items-center gap-3">
                            <div class="size-8 rounded-full bg-emerald-500/20 flex items-center justify-center shrink-0">
                                <flux:icon.shield-check variant="solid" class="size-5 text-emerald-500" />
                            </div>
                            <div class="space-y-0.5">
                                <p class="text-xs font-bold text-white uppercase tracking-wider">Cifrado Bancario</p>
                                <p class="text-[10px] text-zinc-400 font-medium">Protocolo AES-256 Activo</p>
                            </div>
                        </div>
                        <div class="pt-3 border-t border-zinc-700 flex items-center justify-between text-[9px] font-black text-zinc-500 uppercase tracking-widest">
                            <span>SSL PROTECTED</span>
                            <span>PCI COMPLIANT</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Form Section -->
        <div class="w-full md:w-[58%] p-6 md:p-12 lg:p-16 bg-white dark:bg-zinc-900 overflow-y-auto">
            <div class="max-w-md mx-auto space-y-8">
                <header class="space-y-1">
                    <h2 class="text-2xl font-black text-zinc-900 dark:text-zinc-50 tracking-tight">Facturación</h2>
                    <p class="text-zinc-400 dark:text-zinc-500 text-xs md:text-sm">Introduce tus datos para el débito inmediato.</p>
                </header>

                <form wire:submit.prevent="processPayment" class="space-y-5">
                    <!-- Name Grid -->
                    <div class="grid grid-cols-2 gap-4">
                        <flux:input label="Nombre" placeholder="Carlos" wire:model="first_name" class="!rounded-xl" />
                        <flux:input label="Apellido" placeholder="Sosa" wire:model="last_name" class="!rounded-xl" />
                    </div>

                    <!-- Doc Type & Number -->
                    <div class="flex gap-3 items-end">
                        <div class="w-24 shrink-0">
                            <flux:select label="Tipo" wire:model="document_type" class="!rounded-xl">
                                <flux:select.option value="V">V</flux:select.option>
                                <flux:select.option value="E">E</flux:select.option>
                                <flux:select.option value="J">J</flux:select.option>
                            </flux:select>
                        </div>
                        <div class="flex-1">
                            <flux:input label="Nro. Documento" placeholder="12345678" wire:model="ci" class="!rounded-xl" />
                        </div>
                    </div>

                    <!-- Bank Selection -->
                    <flux:select label="Entidad Bancaria" wire:model="bank_id" searchable variant="listbox" placeholder="Selecciona tu banco" class="!rounded-xl">
                        <flux:select.option value="0172">0172 - Bancamiga</flux:select.option>
                        <flux:select.option value="0102">0102 - Banco de Venezuela</flux:select.option>
                        <flux:select.option value="0134">0134 - Banesco</flux:select.option>
                        <flux:select.option value="0105">0105 - Banco Mercantil</flux:select.option>
                    </flux:select>

                    <!-- Account Number -->
                    <flux:input label="Cuenta Bancaria (20 dígitos)" wire:model="account_number" mask="9999 9999 9999 9999 9999" placeholder="0102 0000 00 0000000000" class="!rounded-xl font-mono" />

                    <!-- Submit Button -->
                    <div class="pt-4">
                        <flux:button type="submit"
                            class="w-full h-14 !bg-[#23bdf2] dark:!bg-white text-white dark:!text-zinc-950 font-black uppercase text-[11px] tracking-[0.2em] rounded-2xl shadow-xl shadow-[#23bdf2]/20 dark:shadow-none hover:scale-[1.01] active:scale-[0.98] transition-all">
                            Finalizar Pago
                        </flux:button>
                    </div>
                </form>

                <!-- Footer Badges -->
                <div class="pt-8 flex flex-col items-center gap-6 border-t border-zinc-100 dark:border-zinc-800">
                    <div class="flex items-center gap-6 opacity-30 grayscale contrast-125">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/5/5e/Visa_Inc._logo.svg" alt="Visa" class="h-3 md:h-4">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/2/2a/Mastercard-logo.svg" alt="Mastercard" class="h-5 md:h-6">
                        <div class="h-4 w-px bg-zinc-300 dark:bg-zinc-700"></div>
                        <img src="https://upload.wikimedia.org/wikipedia/commons/b/b5/PayPal.svg" alt="PayPal" class="h-4 md:h-5">
                    </div>
                    <div class="flex items-center gap-2 text-zinc-400">
                        <flux:icon.lock-closed class="size-3" />
                        <p class="text-[9px] font-black uppercase tracking-[0.3em]">Cifrado de extremo a extremo</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>