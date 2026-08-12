<div class="relative min-h-screen px-4 py-8 sm:px-6 lg:px-10">
    <div
        x-data="{
            theme: document.documentElement.getAttribute('data-theme') || 'light',
            toggleTheme() {
                this.theme = this.theme === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', this.theme);
                localStorage.setItem('tdev-agent-theme', this.theme);
            },
        }"
        class="fixed right-4 top-4 z-50 sm:right-6 sm:top-6"
    >
        <button type="button" class="theme-toggle" @click="toggleTheme()"
            :aria-label="theme === 'dark' ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro'">
            <svg x-show="theme === 'dark'" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
            </svg>
            <svg x-show="theme === 'light'" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M21.752 15.002A9.718 9.718 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
            </svg>
            <span x-text="theme === 'dark' ? 'Claro' : 'Oscuro'"></span>
        </button>
    </div>

    <div class="mx-auto w-full max-w-3xl animate-glass">
        <header class="mb-8">
            <div class="glass-panel px-5 py-5 sm:px-7 sm:py-6">
                <div class="relative z-10 flex flex-col items-center gap-5 sm:flex-row sm:justify-between">
                    <div class="flex flex-col items-center gap-4 sm:flex-row sm:gap-6">
                        @if ($agency->logoUrl())
                            <img src="{{ $agency->logoUrl() }}" alt="{{ $agency->name }}"
                                class="h-28 w-auto max-w-[16rem] object-contain drop-shadow-lg sm:h-24 sm:max-w-[16rem]">
                            <div class="h-px w-12 bg-[color:var(--field-border)] sm:h-20 sm:w-px"></div>
                        @endif
                        <img src="{{ asset('image/logo-tdev.png') }}" alt="Tu Doctor En Viajes"
                            class="h-20 w-auto max-w-[14rem] object-contain drop-shadow-lg sm:h-24 sm:max-w-[16rem]">
                    </div>

                    <div class="text-center sm:text-right">
                        <p class="text-[11px] font-bold uppercase tracking-[0.28em] text-[color:var(--accent)]">
                            Agentes TDEV
                            @if ($agency->isLevelThree())
                                · Nivel 3
                            @endif
                        </p>
                        <h1 class="mt-1 text-2xl font-semibold tracking-tight text-[color:var(--text-primary)] sm:text-3xl">
                            Registro de agente
                        </h1>
                        <p class="mt-1 text-sm text-[color:var(--text-secondary)]">
                            Agencia <span class="font-semibold text-[color:var(--text-primary)]">{{ $agency->name }}</span>
                        </p>
                        @if ($agency->isLevelThree() && $agency->parentAgency)
                            <p class="mt-1 text-xs text-[color:var(--text-muted)]">
                                Asociada a {{ $agency->parentAgency->name }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </header>

        @if ($submitted)
            <div class="glass-panel p-8 text-center sm:p-12">
                <div class="relative z-10">
                    <div
                        class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-[color:var(--success-bg)] text-2xl text-[color:var(--success-text)] shadow-inner">
                        ✓
                    </div>
                    <h2 class="text-2xl font-semibold text-[color:var(--text-primary)]">Registro completado</h2>
                    <p class="mt-3 text-sm text-[color:var(--text-secondary)]">
                        Tu información quedó asociada a <span class="font-semibold">{{ $agency->name }}</span>.
                    </p>
                    @if ($registeredAtDisplay)
                        <p class="mt-2 text-xs font-medium uppercase tracking-[0.18em] text-[color:var(--text-muted)]">
                            Fecha y hora: {{ $registeredAtDisplay }}
                        </p>
                    @endif
                    <div class="mt-8">
                        <button type="button" wire:click="startNewRegistration" class="btn-accent">
                            Registrar otro agente
                        </button>
                    </div>
                </div>
            </div>
        @else
            <form wire:submit="submit" class="space-y-6">
                <section class="glass-panel p-6 sm:p-8">
                    <div class="relative z-10">
                        <div class="mb-6 flex items-center gap-4">
                            <div class="h-px flex-1 bg-[color:var(--field-border)]"></div>
                            <h2 class="text-[11px] font-bold uppercase tracking-[0.22em] text-[color:var(--text-muted)]">
                                Datos del agente
                            </h2>
                            <div class="h-px flex-1 bg-[color:var(--field-border)]"></div>
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-[color:var(--text-secondary)]">
                                    Nombre y apellido <span class="text-[color:var(--accent)]">*</span>
                                </label>
                                <input type="text" wire:model="fullName" class="field-input"
                                    placeholder="Ej: María Pérez" autocomplete="name">
                                @error('fullName')
                                    <p class="mt-2 text-sm text-[color:var(--error-text)]">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-[color:var(--text-secondary)]">
                                    Cargo
                                </label>
                                <input type="text" wire:model="position" class="field-input"
                                    placeholder="Ej: Asesor comercial" autocomplete="organization-title">
                                @error('position')
                                    <p class="mt-2 text-sm text-[color:var(--error-text)]">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-[color:var(--text-secondary)]">
                                    Correo
                                </label>
                                <input type="email" wire:model="email" class="field-input"
                                    placeholder="nombre@correo.com" autocomplete="email">
                                @error('email')
                                    <p class="mt-2 text-sm text-[color:var(--error-text)]">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-[color:var(--text-secondary)]">
                                    Teléfono
                                </label>
                                <input type="tel" wire:model="phone" class="field-input"
                                    placeholder="+58 412 0000000" autocomplete="tel">
                                @error('phone')
                                    <p class="mt-2 text-sm text-[color:var(--error-text)]">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-[color:var(--text-secondary)]">
                                    Fecha de nacimiento
                                </label>
                                <input type="date" wire:model="birthDate" class="field-input">
                                @error('birthDate')
                                    <p class="mt-2 text-sm text-[color:var(--error-text)]">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-8 flex flex-col items-center gap-3 sm:flex-row sm:justify-between">
                            <p class="text-xs text-[color:var(--text-muted)]">
                                Los campos marcados con * son obligatorios.
                            </p>
                            <button type="submit" class="btn-accent w-full sm:w-auto" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="submit">Enviar registro</span>
                                <span wire:loading wire:target="submit">Guardando…</span>
                            </button>
                        </div>
                    </div>
                </section>
            </form>
        @endif

        <p class="mt-8 text-center text-[11px] uppercase tracking-[0.2em] text-[color:var(--text-muted)]">
            Tu Doctor En Viajes · TDEV
        </p>
    </div>
</div>
