<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Agencia - iOS Minimalist</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "SF Pro Text", "Helvetica Neue", Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            letter-spacing: -0.015em;
            background-color: #050505;
        }

        ::-webkit-scrollbar {
            width: 0px;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.98) translateY(10px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .animate-glass {
            animation: fadeIn 0.8s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
        }

        select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%238E8E93'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.5rem center;
            background-size: 1rem;
        }

        @media screen and (max-width: 768px) {

            input,
            select,
            textarea {
                font-size: 16px !important;
            }
        }
    </style>
</head>

<body class="min-h-screen w-full relative selection:bg-blue-500/30">

    <div class="fixed inset-0 z-0">
        <img src="{{ asset('image/i2.jpg') }}" alt="Fondo" class="w-full h-full object-cover">
        <div class="absolute inset-0 bg-gradient-to-b from-black/40 via-black/60 to-black/80 backdrop-blur-[2px]"></div>
    </div>

    <div class="relative z-10 w-full min-h-screen flex items-center justify-center p-4 sm:p-6 md:p-12">
        <div
            class="w-full max-w-4xl bg-white/10 backdrop-blur-[40px] border border-white/10 shadow-[0_32px_64px_rgba(0,0,0,0.4)] rounded-[30px] sm:rounded-[40px] p-6 sm:p-10 md:p-14 animate-glass my-8">

            <div
                class="mb-10 sm:mb-16 flex flex-col-reverse sm:flex-row justify-between items-center sm:items-start gap-6 text-center sm:text-left">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-semibold text-white tracking-tight mb-2">Registro</h1>
                    <p class="text-white/40 text-xs sm:text-sm font-medium uppercase tracking-widest">Contacto Agencia /
                        Agente</p>
                </div>
                <div class="w-auto flex items-center justify-center">
                    <img src="{{ asset('image/logoTDG.png') }}" alt="Logo"
                        class="h-10 sm:h-12 md:h-16 w-auto drop-shadow-lg">
                </div>
            </div>

            <form id="agencyForm" class="space-y-10 sm:space-y-12">
                @csrf
                <div class="space-y-8">
                    <div class="flex items-center gap-4 mb-8 sm:mb-12">
                        <div class="h-[1px] flex-1 bg-white/10"></div>
                        <h2
                            class="text-white/60 text-[10px] sm:text-xs font-bold uppercase tracking-[0.2em] text-center">
                            Información de Contacto</h2>
                        <div class="h-[1px] flex-1 bg-white/10"></div>
                    </div>

                    <div class="grid grid-cols-1 gap-y-10 sm:gap-y-12">
                        <div
                            class="relative border-b border-white/10 pb-2 focus-within:border-white/60 transition-colors">
                            <label
                                class="block text-[10px] sm:text-[11px] font-bold text-white/70 uppercase tracking-tighter mb-1">Nombre
                                legal <span class="text-white/90">*</span></label>
                            <input type="text" required name="legal_name" placeholder="Ej. Global Solutions Ltd."
                                class="w-full bg-transparent border-none p-0 text-base sm:text-lg text-white placeholder-white/10 focus:ring-0 focus:outline-none">
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-10">
                            <div
                                class="relative border-b border-white/10 pb-2 focus-within:border-white/60 transition-colors">
                                <label
                                    class="block text-[10px] sm:text-[11px] font-bold text-white/70 uppercase tracking-tighter mb-1">Teléfono
                                    <span class="text-white/90">*</span></label>
                                <input type="tel" required name="phone" placeholder="+00 000 000"
                                    class="w-full bg-transparent border-none p-0 text-base sm:text-lg text-white placeholder-white/10 focus:ring-0 focus:outline-none">
                            </div>
                            <div
                                class="relative border-b border-white/10 pb-2 focus-within:border-white/60 transition-colors">
                                <label
                                    class="block text-[10px] sm:text-[11px] font-bold text-white/70 uppercase tracking-tighter mb-1">Email
                                    <span class="text-white/90">*</span></label>
                                <input type="email" required name="email" placeholder="email@agencia.com"
                                    class="w-full bg-transparent border-none p-0 text-base sm:text-lg text-white placeholder-white/10 focus:ring-0 focus:outline-none">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-8 pt-4">
                    <div class="flex items-center gap-4 mb-8">
                        <div class="h-[1px] flex-1 bg-white/5"></div>
                        <h2 class="text-white/30 text-[10px] font-bold uppercase tracking-[0.2em]">Localización</h2>
                        <div class="h-[1px] flex-1 bg-white/5"></div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-x-8 gap-y-10">
                        <div class="relative border-b border-white/10 pb-2">
                            <label
                                class="block text-[10px] sm:text-[11px] font-bold text-white/70 uppercase tracking-tighter mb-1">País</label>
                            <select id="country" name="country_id" required
                                class="w-full bg-transparent border-none p-0 text-base sm:text-lg text-white focus:ring-0 focus:outline-none cursor-pointer">
                                <option value="" class="bg-zinc-900">Cargando...</option>
                            </select>
                        </div>
                        <div class="relative border-b border-white/10 pb-2">
                            <label
                                class="block text-[10px] sm:text-[11px] font-bold text-white/70 uppercase tracking-tighter mb-1">Estado</label>
                            <select id="state" name="state_id" required disabled
                                class="w-full bg-transparent border-none p-0 text-base sm:text-lg text-white focus:ring-0 focus:outline-none cursor-pointer disabled:opacity-20">
                                <option value="" class="bg-zinc-900">Seleccionar País</option>
                            </select>
                        </div>
                        <div class="relative border-b border-white/10 pb-2">
                            <label
                                class="block text-[10px] sm:text-[11px] font-bold text-white/70 uppercase tracking-tighter mb-1">Ciudad</label>
                            <select id="city" name="city_id" required disabled
                                class="w-full bg-transparent border-none p-0 text-base sm:text-lg text-white focus:ring-0 focus:outline-none cursor-pointer disabled:opacity-20">
                                <option value="" class="bg-zinc-900">Seleccionar Estado</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="pt-6 sm:pt-10 flex flex-col items-center sm:items-end gap-6">
                    <button type="submit" id="submitBtn"
                        class="w-full sm:w-auto group relative px-12 py-4 bg-white text-black font-bold text-sm uppercase tracking-[0.2em] rounded-full overflow-hidden transition-all hover:px-16 active:scale-95 focus:outline-none shadow-xl disabled:opacity-50">
                        <span id="btnText" class="relative z-10">Confirmar Cita</span>
                        <div
                            class="absolute inset-0 bg-blue-400 translate-y-full group-hover:translate-y-0 transition-transform duration-300">
                        </div>
                    </button>

                    <!-- Notificación inline debajo del botón -->
                    <div id="successMessage"
                        class="hidden w-full sm:w-auto flex items-center justify-center gap-3 py-3 px-6 rounded-2xl bg-white/5 border border-white/10 animate-pulse transition-opacity duration-500">
                        <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin='round' stroke-width='2' d='M5 13l4 4L19 7'>
                            </path>
                        </svg>
                        <span class="text-white text-xs font-bold uppercase tracking-widest">Registro enviado con
                            éxito</span>
                    </div>

                    <p id="formStatus" class="text-sm font-medium hidden"></p>
                </div>
            </form>
        </div>
    </div>

    <script>
        const form = document.getElementById('agencyForm');
        const submitBtn = document.getElementById('submitBtn');
        const btnText = document.getElementById('btnText');
        const formStatus = document.getElementById('formStatus');
        const successMessage = document.getElementById('successMessage');
        const countrySelect = document.getElementById('country');
        const stateSelect = document.getElementById('state');
        const citySelect = document.getElementById('city');

        const getApiUrl = (path) => `${window.location.origin}${path.startsWith('/') ? '' : '/'}${path}`;

        window.addEventListener('DOMContentLoaded', loadCountries);

        async function loadCountries() {
            try {
                const res = await fetch(getApiUrl('/api/countries'));
                const data = await res.json();
                countrySelect.innerHTML = '<option value="" class="bg-zinc-900">Seleccionar</option>';
                data.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.id;
                    opt.textContent = c.name;
                    opt.className = "bg-zinc-900";
                    countrySelect.appendChild(opt);
                });
            } catch (e) { countrySelect.innerHTML = '<option value="" class="bg-zinc-900">Error</option>'; }
        }

        countrySelect.addEventListener('change', async () => {
            stateSelect.disabled = true;
            citySelect.disabled = true;
            if (!countrySelect.value) return;
            try {
                const res = await fetch(getApiUrl(`/api/countries/${countrySelect.value}/states`));
                const states = await res.json();
                stateSelect.innerHTML = '<option value="" class="bg-zinc-900">Seleccionar</option>';
                states.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s.id;
                    opt.textContent = s.definition || s.name;
                    opt.className = "bg-zinc-900";
                    stateSelect.appendChild(opt);
                });
                stateSelect.disabled = false;
            } catch (e) { console.error(e); }
        });

        stateSelect.addEventListener('change', async () => {
            citySelect.disabled = true;
            if (!stateSelect.value) return;
            try {
                const res = await fetch(getApiUrl(`/api/states/${stateSelect.value}/cities`));
                const cities = await res.json();
                citySelect.innerHTML = '<option value="" class="bg-zinc-900">Seleccionar</option>';
                cities.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.id;
                    opt.textContent = c.definition || c.name;
                    opt.className = "bg-zinc-900";
                    citySelect.appendChild(opt);
                });
                citySelect.disabled = false;
            } catch (e) { console.error(e); }
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!submitBtn) return;

            submitBtn.disabled = true;
            btnText.textContent = 'Procesando...';
            formStatus.classList.add('hidden');
            successMessage.classList.add('hidden');

            const formData = new FormData(form);
            try {
                const res = await fetch(getApiUrl('/api/info/store'), {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                if (res.ok) {
                    // Mostrar mensaje inline debajo del botón
                    successMessage.classList.remove('hidden');

                    // Limpiar formulario y estados
                    form.reset();
                    stateSelect.disabled = true;
                    citySelect.disabled = true;

                    // Ocultar la notificación automáticamente después de 5 segundos (5000 ms)
                    setTimeout(() => {
                        successMessage.classList.add('hidden');
                    }, 5000);

                } else {
                    const errorData = await res.json();
                    throw new Error(errorData.message || 'Error en el servidor');
                }
            } catch (e) {
                formStatus.textContent = e.message || 'Error al guardar.';
                formStatus.classList.remove('hidden');
                formStatus.classList.add('text-red-400');
            } finally {
                submitBtn.disabled = false;
                btnText.textContent = 'Confirmar Cita';
            }
        });
    </script>
</body>

</html>