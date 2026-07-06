<div class="relative min-h-screen px-4 py-8 sm:px-6 lg:px-10">
    <div
        x-data="{
            theme: document.documentElement.getAttribute('data-theme') || 'light',
            toggleTheme() {
                this.theme = this.theme === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', this.theme);
                localStorage.setItem('tdg-associate-theme', this.theme);
            },
        }"
        class="fixed right-4 top-4 z-50 sm:right-6 sm:top-6">
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

    <div class="mx-auto w-full max-w-5xl animate-glass">
        <div class="mb-8 flex flex-col items-center gap-6 text-center sm:flex-row sm:justify-between sm:text-left">
            <div class="order-2 sm:order-1">
                <p class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--text-muted)]">Tu Doctor Group</p>
                <h1 class="mt-2 text-2xl font-semibold tracking-tight text-[color:var(--text-primary)] sm:text-3xl">
                    Registro de Afiliado
                </h1>
                <p class="mt-2 max-w-xl text-sm text-[color:var(--text-secondary)]">
                    Complete el formulario para registrarse bajo la empresa
                    <span class="font-semibold">{{ $company->name }}</span>.
                </p>
            </div>
            <div class="order-1 sm:order-2">
                <img src="{{ asset('image/logoNewTDG.png') }}" alt="Tu Doctor Group"
                    class="logo-light h-12 w-auto drop-shadow-md sm:h-14">
                <img src="{{ asset('image/logoTDG.png') }}" alt="Tu Doctor Group"
                    class="logo-dark h-12 w-auto drop-shadow-md sm:h-14">
            </div>
        </div>

        @if ($submitted)
            <div
                class="rounded-[2rem] border border-[color:var(--glass-border)] bg-[color:var(--glass-bg)] p-8 text-center shadow-[var(--glass-shadow)] backdrop-blur-[40px] sm:p-12">
                <div
                    class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-[color:var(--success-bg)] text-2xl text-[color:var(--success-text)]">
                    ✓
                </div>
                <h2 class="text-2xl font-semibold text-[color:var(--text-primary)]">Registro completado</h2>
                <p class="mt-3 text-sm text-[color:var(--text-secondary)]">
                    Su información fue registrada correctamente.
                </p>
                @if ($registeredAtDisplay)
                    <p class="mt-2 text-xs font-medium uppercase tracking-[0.18em] text-[color:var(--text-muted)]">
                        Fecha y hora de registro: {{ $registeredAtDisplay }}
                    </p>
                @endif
                <div class="mt-8">
                    <button type="button" wire:click="startNewRegistration"
                        class="inline-flex items-center justify-center rounded-full bg-[color:var(--accent)] px-8 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-500/25 transition hover:scale-[0.98] active:scale-[0.96]">
                        Realizar nuevo registro
                    </button>
                    <p class="mt-3 text-xs text-[color:var(--text-muted)]">
                        Puede registrar otro afiliado bajo la misma empresa.
                    </p>
                </div>
            </div>
        @else
            <form wire:submit="submit" class="space-y-6">
                <section
                    class="rounded-[2rem] border border-[color:var(--glass-border)] bg-[color:var(--glass-bg)] p-6 shadow-[var(--glass-shadow)] backdrop-blur-[40px] sm:p-8">
                    <div class="mb-6 flex items-center gap-4">
                        <div class="h-px flex-1 bg-[color:var(--field-border)]"></div>
                        <h2 class="text-[11px] font-bold uppercase tracking-[0.22em] text-[color:var(--text-muted)]">
                            Responsable
                        </h2>
                        <div class="h-px flex-1 bg-[color:var(--field-border)]"></div>
                    </div>

                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-[color:var(--text-secondary)]">
                            Cédula del responsable <span class="text-[color:var(--accent)]">*</span>
                        </label>
                        <input type="text" wire:model.live.debounce.400ms="responsibleIdentityCard"
                            placeholder="Ej: V-12345678"
                            class="w-full rounded-2xl border border-[color:var(--field-border)] bg-white/50 px-4 py-3 text-[color:var(--text-primary)] outline-none transition focus:border-[color:var(--field-focus)] focus:ring-4 focus:ring-blue-500/10 dark:bg-white/5">
                        @error('responsibleIdentityCard')
                            <p class="mt-2 text-sm text-[color:var(--error-text)]">{{ $message }}</p>
                        @enderror
                        @error('resolvedResponsibleId')
                            <p class="mt-2 text-sm text-[color:var(--error-text)]">{{ $message }}</p>
                        @enderror

                        <div wire:loading wire:target="responsibleIdentityCard" class="mt-3 text-sm text-[color:var(--text-secondary)]">
                            Validando responsable...
                        </div>

                        <div wire:loading.remove wire:target="responsibleIdentityCard">
                            @if ($resolvedResponsibleName)
                                <div
                                    class="mt-3 rounded-2xl border border-[color:var(--success-text)]/20 bg-[color:var(--success-bg)] px-4 py-3">
                                    <p class="text-sm font-semibold text-[color:var(--text-primary)]">{{ $resolvedResponsibleName }}</p>
                                    <p class="mt-1 text-xs text-[color:var(--success-text)]">Responsable verificado para esta empresa</p>
                                </div>

                                @if ($responsibleDaysExhausted)
                                    <div
                                        class="mt-4 rounded-2xl border border-[color:var(--error-text)]/20 bg-[color:var(--error-bg)] px-4 py-4">
                                        <p class="text-sm font-semibold text-[color:var(--error-text)]">
                                            Registro no disponible
                                        </p>
                                        <p class="mt-2 text-sm text-[color:var(--text-secondary)]">
                                            Este responsable ha consumido los
                                            {{ number_format((int) $resolvedResponsibleContractedDays, 0, ',', '.') }}
                                            días contratados. No es posible registrar un nuevo afiliado.
                                        </p>
                                    </div>
                                @else
                                    @if ($responsibleRemainingDays !== null)
                                        <div
                                            class="mt-4 rounded-2xl border border-[color:var(--accent)]/20 bg-[color:var(--accent)]/5 px-4 py-3">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-[color:var(--text-muted)]">
                                                Días restantes del responsable
                                            </p>
                                            <p class="mt-1 text-2xl font-bold text-[color:var(--accent)]">
                                                {{ number_format($responsibleRemainingDays, 0, ',', '.') }}
                                                <span class="text-sm font-semibold text-[color:var(--text-secondary)]">
                                                    {{ abs($responsibleRemainingDays) === 1 ? 'día' : 'días' }}
                                                </span>
                                            </p>
                                            <p class="mt-2 text-xs text-[color:var(--text-muted)]">
                                                {{ number_format((int) $resolvedResponsibleConsumedDays, 0, ',', '.') }}
                                                de
                                                {{ number_format((int) $resolvedResponsibleContractedDays, 0, ',', '.') }}
                                                días contratados ya fueron consumidos. Cada registro consume 1 día.
                                            </p>
                                        </div>
                                    @endif
                                @endif
                            @elseif (filled($responsibleIdentityCard))
                                <p class="mt-3 text-sm text-[color:var(--error-text)]">
                                    No se encontró un responsable con esa cédula en esta empresa.
                                </p>
                            @else
                                <p class="mt-3 text-sm text-[color:var(--text-muted)]">
                                    Ingrese la cédula del responsable para validar.
                                </p>
                            @endif
                        </div>
                    </div>
                </section>

                @unless ($responsibleDaysExhausted)
                <section
                    class="rounded-[2rem] border border-[color:var(--glass-border)] bg-[color:var(--glass-bg)] p-6 shadow-[var(--glass-shadow)] backdrop-blur-[40px] sm:p-8">
                    <div class="mb-6 flex items-center gap-4">
                        <div class="h-px flex-1 bg-[color:var(--field-border)]"></div>
                        <h2 class="text-[11px] font-bold uppercase tracking-[0.22em] text-[color:var(--text-muted)]">
                            Datos personales
                        </h2>
                        <div class="h-px flex-1 bg-[color:var(--field-border)]"></div>
                    </div>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-4">
                        <div class="md:col-span-2 xl:col-span-2">
                            <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-[color:var(--text-secondary)]">
                                Nombre y Apellido <span class="text-[color:var(--accent)]">*</span>
                            </label>
                            <input type="text" wire:model="fullName" placeholder="Ej: María González"
                                class="w-full rounded-2xl border border-[color:var(--field-border)] bg-white/50 px-4 py-3 outline-none transition focus:border-[color:var(--field-focus)] focus:ring-4 focus:ring-blue-500/10 dark:bg-white/5">
                            @error('fullName')<p class="mt-2 text-sm text-[color:var(--error-text)]">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-[color:var(--text-secondary)]">
                                Documento de Identidad <span class="text-[color:var(--accent)]">*</span>
                            </label>
                            <input type="text" wire:model="identityCard" placeholder="Ej: 12345678"
                                class="w-full rounded-2xl border border-[color:var(--field-border)] bg-white/50 px-4 py-3 outline-none transition focus:border-[color:var(--field-focus)] focus:ring-4 focus:ring-blue-500/10 dark:bg-white/5">
                            @error('identityCard')<p class="mt-2 text-sm text-[color:var(--error-text)]">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-[color:var(--text-secondary)]">
                                Fecha de Nacimiento <span class="text-[color:var(--accent)]">*</span>
                            </label>
                            <input type="date" wire:model.live="birthDate"
                                class="w-full rounded-2xl border border-[color:var(--field-border)] bg-white/50 px-4 py-3 outline-none transition focus:border-[color:var(--field-focus)] focus:ring-4 focus:ring-blue-500/10 dark:bg-white/5">
                            @error('birthDate')<p class="mt-2 text-sm text-[color:var(--error-text)]">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-[color:var(--text-secondary)]">
                                Edad
                            </label>
                            <div
                                class="flex min-h-[50px] items-center rounded-2xl border border-[color:var(--field-border)] bg-white/30 px-4 py-3 dark:bg-white/5">
                                <span class="text-lg font-semibold text-[color:var(--text-primary)]">{{ $age ?? '—' }}</span>
                                <span class="ml-2 text-xs uppercase tracking-wide text-[color:var(--text-muted)]">años</span>
                            </div>
                        </div>

                        <div>
                            <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-[color:var(--text-secondary)]">
                                Sexo <span class="text-[color:var(--accent)]">*</span>
                            </label>
                            <select wire:model="sex"
                                class="w-full rounded-2xl border border-[color:var(--field-border)] bg-white/50 px-4 py-3 outline-none transition focus:border-[color:var(--field-focus)] focus:ring-4 focus:ring-blue-500/10 dark:bg-white/5">
                                <option value="">Seleccione</option>
                                <option value="MASCULINO">MASCULINO</option>
                                <option value="FEMENINO">FEMENINO</option>
                            </select>
                            @error('sex')<p class="mt-2 text-sm text-[color:var(--error-text)]">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-[color:var(--text-secondary)]">
                                Correo electrónico
                            </label>
                            <input type="email" wire:model="email" placeholder="correo@ejemplo.com"
                                class="w-full rounded-2xl border border-[color:var(--field-border)] bg-white/50 px-4 py-3 outline-none transition focus:border-[color:var(--field-focus)] focus:ring-4 focus:ring-blue-500/10 dark:bg-white/5">
                            @error('email')<p class="mt-2 text-sm text-[color:var(--error-text)]">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-[color:var(--text-secondary)]">
                                Teléfono <span class="text-[color:var(--accent)]">*</span>
                            </label>
                            <input type="tel" wire:model.live.debounce.300ms="phone" inputmode="tel" autocomplete="tel"
                                placeholder="+584127018390"
                                class="w-full rounded-2xl border border-[color:var(--field-border)] bg-white/50 px-4 py-3 outline-none transition focus:border-[color:var(--field-focus)] focus:ring-4 focus:ring-blue-500/10 dark:bg-white/5">
                            <p class="mt-2 text-xs text-[color:var(--text-muted)]">
                                Incluya el prefijo del país. Ej: +584127018390
                            </p>
                            @error('phone')<p class="mt-2 text-sm text-[color:var(--error-text)]">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-[color:var(--text-secondary)]">
                                Fecha de vuelo <span class="text-[color:var(--accent)]">*</span>
                            </label>
                            <input type="date" wire:model="flightDate"
                                class="w-full rounded-2xl border border-[color:var(--field-border)] bg-white/50 px-4 py-3 outline-none transition focus:border-[color:var(--field-focus)] focus:ring-4 focus:ring-blue-500/10 dark:bg-white/5">
                            @error('flightDate')<p class="mt-2 text-sm text-[color:var(--error-text)]">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-[color:var(--text-secondary)]">
                                Hora de vuelo <span class="text-[color:var(--accent)]">*</span>
                            </label>
                            <input type="time" wire:model="flightTime"
                                class="w-full rounded-2xl border border-[color:var(--field-border)] bg-white/50 px-4 py-3 outline-none transition focus:border-[color:var(--field-focus)] focus:ring-4 focus:ring-blue-500/10 dark:bg-white/5">
                            @error('flightTime')<p class="mt-2 text-sm text-[color:var(--error-text)]">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-[color:var(--text-secondary)]">
                                Estado <span class="text-[color:var(--accent)]">*</span>
                            </label>
                            <select wire:model.live="stateId"
                                class="w-full rounded-2xl border border-[color:var(--field-border)] bg-white/50 px-4 py-3 outline-none transition focus:border-[color:var(--field-focus)] focus:ring-4 focus:ring-blue-500/10 dark:bg-white/5">
                                <option value="">Seleccione</option>
                                @foreach ($this->states as $id => $definition)
                                    <option value="{{ $id }}">{{ $definition }}</option>
                                @endforeach
                            </select>
                            @error('stateId')<p class="mt-2 text-sm text-[color:var(--error-text)]">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-[color:var(--text-secondary)]">
                                Ciudad <span class="text-[color:var(--accent)]">*</span>
                            </label>
                            <select wire:model="cityId" @disabled(blank($stateId))
                                class="w-full rounded-2xl border border-[color:var(--field-border)] bg-white/50 px-4 py-3 outline-none transition focus:border-[color:var(--field-focus)] focus:ring-4 focus:ring-blue-500/10 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-white/5">
                                <option value="">Seleccione</option>
                                @foreach ($this->cities as $id => $definition)
                                    <option value="{{ $id }}">{{ $definition }}</option>
                                @endforeach
                            </select>
                            @if (blank($stateId))
                                <p class="mt-2 text-xs text-[color:var(--text-muted)]">Seleccione primero un estado.</p>
                            @endif
                            @error('cityId')<p class="mt-2 text-sm text-[color:var(--error-text)]">{{ $message }}</p>@enderror
                        </div>

                        <div class="md:col-span-2 xl:col-span-4">
                            <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-[color:var(--text-secondary)]">
                                Observaciones
                            </label>
                            <textarea wire:model="observations" rows="3" placeholder="Información adicional relevante para el registro"
                                class="w-full rounded-2xl border border-[color:var(--field-border)] bg-white/50 px-4 py-3 outline-none transition focus:border-[color:var(--field-focus)] focus:ring-4 focus:ring-blue-500/10 dark:bg-white/5"></textarea>
                            @error('observations')<p class="mt-2 text-sm text-[color:var(--error-text)]">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </section>

                <section
                    class="rounded-[2rem] border border-[color:var(--glass-border)] bg-[color:var(--glass-bg)] p-6 shadow-[var(--glass-shadow)] backdrop-blur-[40px] sm:p-8">
                    <div class="mb-6 flex items-center gap-4">
                        <div class="h-px flex-1 bg-[color:var(--field-border)]"></div>
                        <h2 class="text-[11px] font-bold uppercase tracking-[0.22em] text-[color:var(--text-muted)]">
                            Contacto de emergencia
                        </h2>
                        <div class="h-px flex-1 bg-[color:var(--field-border)]"></div>
                    </div>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                        <div class="md:col-span-3 lg:col-span-1">
                            <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-[color:var(--text-secondary)]">
                                Nombre y Apellido <span class="text-[color:var(--accent)]">*</span>
                            </label>
                            <input type="text" wire:model="contactFullName" placeholder="Contacto principal"
                                class="w-full rounded-2xl border border-[color:var(--field-border)] bg-white/50 px-4 py-3 outline-none transition focus:border-[color:var(--field-focus)] focus:ring-4 focus:ring-blue-500/10 dark:bg-white/5">
                            @error('contactFullName')<p class="mt-2 text-sm text-[color:var(--error-text)]">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-[color:var(--text-secondary)]">
                                Teléfono <span class="text-[color:var(--accent)]">*</span>
                            </label>
                            <input type="tel" wire:model="contactPhone" placeholder="Teléfono de contacto"
                                class="w-full rounded-2xl border border-[color:var(--field-border)] bg-white/50 px-4 py-3 outline-none transition focus:border-[color:var(--field-focus)] focus:ring-4 focus:ring-blue-500/10 dark:bg-white/5">
                            @error('contactPhone')<p class="mt-2 text-sm text-[color:var(--error-text)]">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-[color:var(--text-secondary)]">
                                Correo electrónico <span class="text-[color:var(--accent)]">*</span>
                            </label>
                            <input type="email" wire:model="contactEmail" placeholder="correo@contacto.com"
                                class="w-full rounded-2xl border border-[color:var(--field-border)] bg-white/50 px-4 py-3 outline-none transition focus:border-[color:var(--field-focus)] focus:ring-4 focus:ring-blue-500/10 dark:bg-white/5">
                            @error('contactEmail')<p class="mt-2 text-sm text-[color:var(--error-text)]">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </section>

                <section
                    class="rounded-[2rem] border border-[color:var(--glass-border)] bg-[color:var(--glass-bg)] p-6 shadow-[var(--glass-shadow)] backdrop-blur-[40px] sm:p-8">
                    <div class="mb-6 flex items-center gap-4">
                        <div class="h-px flex-1 bg-[color:var(--field-border)]"></div>
                        <h2 class="text-[11px] font-bold uppercase tracking-[0.22em] text-[color:var(--text-muted)]">
                            Documento de identidad <span class="text-[color:var(--accent)]">*</span>
                        </h2>
                        <div class="h-px flex-1 bg-[color:var(--field-border)]"></div>
                    </div>

                    <div
                        class="rounded-[1.5rem] border border-dashed border-[color:var(--field-border)] bg-white/30 p-6 dark:bg-white/5">
                        <input type="file" wire:model="identityDocuments" accept="image/*" multiple
                            class="block w-full cursor-pointer text-sm text-[color:var(--text-secondary)] file:mr-4 file:rounded-full file:border-0 file:bg-[color:var(--accent)] file:px-5 file:py-2.5 file:text-sm file:font-semibold file:text-white hover:file:opacity-90">
                        <p class="mt-3 text-xs text-[color:var(--text-muted)]">Formatos de imagen · Máximo 5 MB por archivo · Puede cargar uno o más documentos</p>
                        @error('identityDocuments')<p class="mt-2 text-sm text-[color:var(--error-text)]">{{ $message }}</p>@enderror
                        @error('identityDocuments.*')<p class="mt-2 text-sm text-[color:var(--error-text)]">{{ $message }}</p>@enderror

                        @if (count($identityDocuments) > 0)
                            <p class="mt-3 text-sm text-[color:var(--text-secondary)]">
                                {{ count($identityDocuments) }} {{ count($identityDocuments) === 1 ? 'documento seleccionado' : 'documentos seleccionados' }}
                            </p>
                        @endif

                        <div wire:loading wire:target="identityDocuments" class="mt-3 text-sm text-[color:var(--text-secondary)]">
                            Cargando documentos...
                        </div>
                    </div>
                </section>

                @error('submit')
                    <div class="rounded-2xl border border-red-200 bg-[color:var(--error-bg)] px-4 py-3 text-sm text-[color:var(--error-text)]">
                        {{ $message }}
                    </div>
                @enderror

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-[color:var(--text-muted)]">
                        Al enviar, se registrará la fecha y hora exactas de su solicitud.
                    </p>
                    <button type="submit"
                        class="inline-flex items-center justify-center rounded-full bg-[color:var(--accent)] px-8 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-500/25 transition hover:scale-[0.98] active:scale-[0.96] disabled:opacity-60"
                        wire:loading.attr="disabled" wire:target="submit,identityDocuments"
                        @disabled($responsibleDaysExhausted)>
                        <span wire:loading.remove wire:target="submit">Registrar afiliado</span>
                        <span wire:loading wire:target="submit">Procesando registro...</span>
                    </button>
                </div>
                @endunless
            </form>
        @endif
    </div>
</div>
