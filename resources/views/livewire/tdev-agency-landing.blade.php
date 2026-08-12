<div class="relative flex h-dvh max-h-dvh items-center justify-center overflow-hidden px-4 py-3 sm:px-6 sm:py-4">
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

    <section class="animate-glass relative z-10 flex max-h-full w-full max-w-2xl">
        <div class="glass-panel flex max-h-full w-full flex-col px-5 py-5 sm:px-8 sm:py-6">
            <div class="relative z-10 mx-auto flex w-full max-w-lg flex-col items-center overflow-y-auto overscroll-contain">
                <div class="mb-3 flex items-center justify-center sm:mb-4">
                    @if ($agency->logoUrl())
                        <img src="{{ $agency->logoUrl() }}" alt="{{ $agency->name }}"
                            class="h-28 w-auto max-w-[14rem] object-contain drop-shadow-xl sm:h-36 sm:max-w-[18rem]">
                    @else
                        <div class="flex h-28 w-28 items-center justify-center rounded-2xl bg-[color:var(--accent-soft)] text-4xl font-semibold text-[color:var(--accent)] sm:h-36 sm:w-36">
                            {{ strtoupper(substr($agency->name, 0, 1)) }}
                        </div>
                    @endif
                </div>

                <p class="text-[10px] font-bold uppercase tracking-[0.32em] text-[color:var(--accent)] sm:text-[11px]">
                    Tu Doctor En Viajes
                </p>

                <h1 class="mt-1.5 text-balance text-2xl font-semibold tracking-tight text-[color:var(--text-primary)] sm:mt-2 sm:text-3xl">
                    {{ $agency->name }}
                </h1>

                <p class="mt-1 text-xs font-medium text-[color:var(--text-muted)] sm:text-sm">
                    Agencia principal · Nivel 2
                </p>

                <div class="mt-4 w-full max-w-xl px-1 text-center sm:mt-5">
                    <p class="whitespace-nowrap text-[clamp(0.78rem,2.7vw,1.125rem)] font-semibold leading-snug tracking-tight text-[color:var(--text-primary)]">
                        {{ $agency->resolvedLandingSloganLine1() }}
                    </p>
                    <p class="mt-1.5 whitespace-nowrap text-[clamp(0.78rem,2.7vw,1.125rem)] font-medium leading-snug tracking-tight text-[color:var(--text-secondary)] sm:mt-2">
                        {{ $agency->resolvedLandingSloganLine2() }}
                    </p>
                </div>

                <div class="mt-4 h-px w-full max-w-xs bg-[color:var(--field-border)] sm:mt-5"></div>

                <p class="mt-4 text-[10px] font-semibold uppercase tracking-[0.22em] text-[color:var(--text-muted)] sm:mt-5 sm:text-xs">
                    ¿Cómo deseas registrarte?
                </p>

                <div class="mt-3 flex w-full flex-col items-center justify-center gap-2.5 sm:mt-4 sm:flex-row sm:gap-3">
                    <a href="{{ $agencyRegistrationUrl }}"
                        class="btn-accent inline-flex min-h-11 w-full max-w-xs items-center justify-center px-6 sm:min-h-12 sm:w-auto sm:min-w-[12.5rem]">
                        Registrar agencia
                    </a>
                    <a href="{{ $freelanceAgentRegistrationUrl }}"
                        class="inline-flex min-h-11 w-full max-w-xs items-center justify-center rounded-full border border-[color:var(--field-border)] bg-[color:var(--field-bg)] px-6 text-sm font-semibold text-[color:var(--text-primary)] shadow-[var(--glass-shadow)] backdrop-blur-xl transition hover:scale-[1.01] active:scale-[0.98] sm:min-h-12 sm:w-auto sm:min-w-[12.5rem]">
                        Agente freelance
                    </a>
                </div>

                <p class="mt-3 max-w-md text-center text-[11px] leading-snug text-[color:var(--text-muted)] sm:mt-4 sm:text-xs">
                    Registra una agencia asociada (nivel 3) o únete como agente freelance de esta red.
                </p>

                <div class="mt-4 flex items-center justify-center sm:mt-5">
                    <img src="{{ asset('image/logo-tdev.png') }}" alt="Tu Doctor En Viajes"
                        class="h-12 w-auto max-w-[10rem] object-contain drop-shadow-lg sm:h-14 sm:max-w-[12rem]">
                </div>
            </div>
        </div>
    </section>
</div>
