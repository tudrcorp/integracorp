<!-- Contenedor Principal -->
<div class="relative z-10 w-full h-full flex items-center justify-center p-6 overflow-y-auto">

    <!-- Card Translucida (Ancho incrementado y opacidad realzada) -->
    <div
        class="w-full max-w-4xl bg-white/10 backdrop-blur-[40px] border border-white/10 shadow-[0_32px_64px_rgba(0,0,0,0.4)] rounded-[40px] p-10 sm:p-14 animate-glass">

        <!-- Header Minimalista con Logo -->
        <div class="mb-16 flex justify-between items-start">
            <div>
                <h1 class="text-3xl font-semibold text-white tracking-tight mb-2">Registro</h1>
                <p class="text-white/40 text-sm font-medium uppercase tracking-widest">Contacto Agencia / Agente</p>
            </div>

            <!-- Logo de la Empresa -->
            <div class="w-auto h-16 flex items-center justify-center">
                <img src="{{ asset('image/logoTDG.png') }}" alt="Logo"
                    class="h-12 md:h-14 lg:h-16 w-auto drop-shadow-lg">
            </div>
        </div>

        <form action="#" class="space-y-12" onsubmit="event.preventDefault();">
        
            <!-- SECCIÓN: Formulario de contacto de Agencia / Agente -->
            <div class="space-y-8">
                <div class="flex items-center gap-4 mb-12">
                    <div class="h-[1px] flex-1 bg-white/10"></div>
                    <h2 class="text-white/60 text-md font-bold uppercase tracking-[0.2em] whitespace-nowrap">Formulario de
                        contacto de Agencia / Agente</h2>
                    <div class="h-[1px] flex-1 bg-white/10"></div>
                </div>
        
                <div class="grid grid-cols-1 gap-y-12">
                    <!-- Nombre legal -->
                    <div class="relative border-b border-white/10 pb-2 focus-within:border-white/40 transition-colors">
                        <label class="block text-[11px] font-bold text-white/30 uppercase tracking-tighter mb-1">Nombre legal de
                            Agencia / Agente <span class="text-white/50">*</span></label>
                        <input type="text" placeholder="Ej. Global Solutions Ltd."
                            class="w-full bg-transparent border-none p-0 text-lg text-white placeholder-white/10 focus:ring-0 focus:outline-none">
                    </div>
        
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-10">
                        <!-- Teléfono -->
                        <div class="relative border-b border-white/10 pb-2 focus-within:border-white/40 transition-colors">
                            <label class="block text-[11px] font-bold text-white/30 uppercase tracking-tighter mb-1">Número de
                                teléfono <span class="text-white/50">*</span></label>
                            <input type="tel" placeholder="Incluya código de país"
                                class="w-full bg-transparent border-none p-0 text-lg text-white placeholder-white/10 focus:ring-0 focus:outline-none">
                        </div>
        
                        <!-- Correo electrónico -->
                        <div class="relative border-b border-white/10 pb-2 focus-within:border-white/40 transition-colors">
                            <label class="block text-[11px] font-bold text-white/30 uppercase tracking-tighter mb-1">Correo
                                electrónico <span class="text-white/50">*</span></label>
                            <input type="email" placeholder="email@agencia.com"
                                class="w-full bg-transparent border-none p-0 text-lg text-white placeholder-white/10 focus:ring-0 focus:outline-none">
                        </div>
                    </div>
        
                    <!-- Página Web -->
                    <div class="relative border-b border-white/10 pb-2 focus-within:border-white/40 transition-colors">
                        <label class="block text-[11px] font-bold text-white/30 uppercase tracking-tighter mb-1">Página
                            web</label>
                        <input type="url" placeholder="www.tuagencia.com"
                            class="w-full bg-transparent border-none p-0 text-lg text-white placeholder-white/10 focus:ring-0 focus:outline-none">
                    </div>
                </div>
            </div>
        
            <!-- SECCIÓN: Ubicación Geográfica (Opcional o complementaria) -->
            <div class="space-y-8 pt-4">
                <div class="flex items-center gap-4 mb-8">
                    <div class="h-[1px] flex-1 bg-white/5"></div>
                        <h2 class="text-white/30 text-md font-bold uppercase tracking-[0.2em] whitespace-nowrap">Localización
                    </h2>
                    <div class="h-[1px] flex-1 bg-white/5"></div>
                </div>
        
                <div class="grid grid-cols-1 md:grid-cols-3 gap-x-8 gap-y-8">
                    <div class="relative border-b border-white/10 pb-2">
                        <label class="block text-[11px] font-bold text-white/30 uppercase tracking-tighter mb-1">País</label>
                        <select id="country"
                            class="w-full bg-transparent border-none p-0 text-lg text-white focus:ring-0 focus:outline-none cursor-pointer"
                            onchange="loadStates()">
                            <option value="" class="bg-zinc-900">Seleccionar</option>
                            <option value="VE" class="bg-zinc-900">Venezuela</option>
                            <option value="MX" class="bg-zinc-900">México</option>
                            <option value="US" class="bg-zinc-900">Estados Unidos</option>
                        </select>
                    </div>
        
                    <div class="relative border-b border-white/10 pb-2">
                        <label class="block text-[11px] font-bold text-white/30 uppercase tracking-tighter mb-1">Estado</label>
                        <select id="state" disabled
                            class="w-full bg-transparent border-none p-0 text-lg text-white focus:ring-0 focus:outline-none cursor-pointer disabled:opacity-20"
                            onchange="loadCities()">
                            <option value="" class="bg-zinc-900">Región</option>
                        </select>
                    </div>
        
                    <div class="relative border-b border-white/10 pb-2">
                        <label class="block text-[11px] font-bold text-white/30 uppercase tracking-tighter mb-1">Ciudad</label>
                        <select id="city" disabled
                            class="w-full bg-transparent border-none p-0 text-lg text-white focus:ring-0 focus:outline-none cursor-pointer disabled:opacity-20">
                            <option value="" class="bg-zinc-900">Localidad</option>
                        </select>
                    </div>
                </div>
            </div>
        
            <!-- Botón de Envío -->
            <div class="pt-10 flex justify-end">
                <button type="submit"
                    class="group relative px-12 py-4 bg-white text-black font-bold text-sm uppercase tracking-[0.2em] rounded-full overflow-hidden transition-all hover:px-16 active:scale-95 focus:outline-none shadow-xl">
                    <span class="relative z-10">Crear Cuenta</span>
                    <div
                        class="absolute inset-0 bg-blue-400 translate-y-full group-hover:translate-y-0 transition-transform duration-300">
                    </div>
                </button>
            </div>
        </form>
    </div>
</div>