<div class="relative min-h-screen px-4 py-8 sm:px-6 lg:px-10">
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
                            Agentes de la agencia de viajes
                        </p>
                        <h1 class="mt-1 text-2xl font-semibold tracking-tight text-[color:var(--text-primary)] sm:text-3xl">
                            Registro de agente
                        </h1>
                        <p class="mt-1 text-sm text-[color:var(--text-secondary)]">
                            Agencia <span class="font-semibold text-[color:var(--text-primary)]">{{ $agency->name }}</span>
                        </p>
                    </div>
                </div>
            </div>
        </header>

        @if ($agency->registrationHierarchyLines() !== [])
            <section class="glass-panel mb-6 px-5 py-4 sm:px-7">
                <div class="relative z-10">
                    <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-[color:var(--accent)]">
                        Jerarquía de esta agencia
                    </p>
                    <ul class="mt-3 space-y-1 text-sm text-[color:var(--text-secondary)]">
                        @foreach ($agency->registrationHierarchyLines() as $line)
                            <li wire:key="hierarchy-{{ $loop->index }}">{{ $line }}</li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif

        @if ($submitted)
            <div class="glass-panel p-8 text-center sm:p-12">
                <div class="relative z-10">
                    <div
                        class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-[color:var(--success-bg)] text-2xl text-[color:var(--success-text)] shadow-inner">
                        ✓
                    </div>
                    <h2 class="text-2xl font-semibold text-[color:var(--text-primary)]">Registro completado</h2>
                    <p class="mt-3 text-sm text-[color:var(--text-secondary)]">
                        Tu información quedó asociada a <span class="font-semibold">{{ $agency->name }}</span>,
                        dentro de su jerarquía comercial.
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
                                <input type="text" wire:model="fullName" class="field-input" placeholder="Ej: María Pérez" autocomplete="name">
                                @error('fullName')
                                    <p class="mt-2 text-sm text-[color:var(--error-text)]">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-[color:var(--text-secondary)]">Cargo</label>
                                <input type="text" wire:model="position" class="field-input" placeholder="Ej: Asesor comercial" autocomplete="organization-title">
                                @error('position')
                                    <p class="mt-2 text-sm text-[color:var(--error-text)]">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-[color:var(--text-secondary)]">Correo</label>
                                <input type="email" wire:model="email" class="field-input" placeholder="nombre@correo.com" autocomplete="email">
                                @error('email')
                                    <p class="mt-2 text-sm text-[color:var(--error-text)]">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-[color:var(--text-secondary)]">Teléfono</label>
                                <input type="tel" wire:model="phone" class="field-input" placeholder="+58 412 0000000" autocomplete="tel">
                                @error('phone')
                                    <p class="mt-2 text-sm text-[color:var(--error-text)]">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-[color:var(--text-secondary)]">Fecha de nacimiento</label>
                                <input type="date" wire:model="birthDate" class="field-input">
                                @error('birthDate')
                                    <p class="mt-2 text-sm text-[color:var(--error-text)]">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-8 flex flex-col items-center gap-3 sm:flex-row sm:justify-between">
                            <p class="text-xs text-[color:var(--text-muted)]">Los campos marcados con * son obligatorios.</p>
                            <button type="submit" class="btn-accent w-full sm:w-auto" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="submit">Enviar registro</span>
                                <span wire:loading wire:target="submit">Guardando…</span>
                            </button>
                        </div>
                    </div>
                </section>
            </form>
        @endif
    </div>
</div>
