<div class="relative min-h-screen px-4 py-8 sm:px-6 lg:px-10">
    <div class="mx-auto w-full max-w-3xl animate-glass">
        <header class="mb-8">
            <div class="glass-panel px-5 py-5 sm:px-7 sm:py-6">
                <div class="relative z-10 flex flex-col items-center gap-5 sm:flex-row sm:justify-between">
                    <div class="flex flex-col items-center gap-4 sm:flex-row sm:gap-6">
                        @if ($parentAgency->logoUrl())
                            <img src="{{ $parentAgency->logoUrl() }}" alt="{{ $parentAgency->name }}"
                                class="h-28 w-auto max-w-[16rem] object-contain drop-shadow-lg sm:h-24 sm:max-w-[16rem]">
                            <div class="h-px w-12 bg-[color:var(--field-border)] sm:h-20 sm:w-px"></div>
                        @endif
                        <img src="{{ asset('image/logo-tdev.png') }}" alt="Tu Doctor En Viajes"
                            class="h-20 w-auto max-w-[14rem] object-contain drop-shadow-lg sm:h-24 sm:max-w-[16rem]">
                    </div>

                    <div class="text-center sm:text-right">
                        <p class="text-[11px] font-bold uppercase tracking-[0.28em] text-[color:var(--accent)]">
                            Agencias de viajes
                        </p>
                        <h1 class="mt-1 text-2xl font-semibold tracking-tight text-[color:var(--text-primary)] sm:text-3xl">
                            Registro de agencia
                        </h1>
                        <p class="mt-1 text-sm text-[color:var(--text-secondary)]">
                            Asociada a <span class="font-semibold text-[color:var(--text-primary)]">{{ $parentAgency->name }}</span>
                        </p>
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
                        La agencia <span class="font-semibold">{{ $createdAgencyName }}</span>
                        quedó asociada a <span class="font-semibold">{{ $parentAgency->name }}</span>
                        y conserva la jerarquía comercial.
                    </p>
                    @if ($registeredAtDisplay)
                        <p class="mt-2 text-xs font-medium uppercase tracking-[0.18em] text-[color:var(--text-muted)]">
                            Fecha y hora: {{ $registeredAtDisplay }}
                        </p>
                    @endif

                    @if ($askAssociateAgents && $createdAgencyAgentRegistrationUrl)
                        <div class="mx-auto mt-8 max-w-xl rounded-[1.75rem] border border-[color:var(--field-border)] bg-[color:var(--field-bg)] p-6 text-center shadow-inner">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-[color:var(--accent)]">Siguiente paso</p>
                            <p class="mt-3 text-sm text-[color:var(--text-secondary)]">
                                Puedes registrar ahora los agentes de esta agencia. Quedarán ligados a ella.
                            </p>
                            <div class="mt-6 flex flex-col items-center gap-3 sm:flex-row sm:justify-center">
                                <button type="button" wire:click="associateAgentsNow" class="btn-accent w-full sm:w-auto">
                                    Registrar agentes
                                </button>
                                <button type="button" wire:click="skipAssociateAgents" class="text-sm font-semibold text-[color:var(--text-secondary)] underline-offset-2 hover:underline">
                                    Ahora no
                                </button>
                            </div>
                        </div>
                    @else
                        <div class="mt-8">
                            <button type="button" wire:click="startNewRegistration" class="btn-accent">
                                Registrar otra agencia
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        @else
            <form wire:submit="submit" class="space-y-6">
                <section class="glass-panel mb-6 px-5 py-4 sm:px-7">
                    <div class="relative z-10">
                        <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-[color:var(--accent)]">
                            Jerarquía que va a quedar
                        </p>
                        <ul class="mt-3 space-y-1 text-sm text-[color:var(--text-secondary)]">
                            <li>Nivel {{ $hierarchyPreview['nivel'] }}</li>
                            <li>Agencia principal: {{ $hierarchyPreview['agenciaPpalNivel1'] }}</li>
                            @if (filled($hierarchyPreview['agenciaSuperiorNivel2']))
                                <li>Agencia superior: {{ $hierarchyPreview['agenciaSuperiorNivel2'] }}</li>
                            @endif
                            @if (filled($hierarchyPreview['agenteSuperiorNivel3']))
                                <li>Agente superior: {{ $hierarchyPreview['agenteSuperiorNivel3'] }}</li>
                            @endif
                            <li>Registrada bajo: {{ $parentAgency->name }}</li>
                        </ul>
                    </div>
                </section>

                <section class="glass-panel p-6 sm:p-8">
                    <div class="relative z-10">
                        <div class="mb-6 flex items-center gap-4">
                            <div class="h-px flex-1 bg-[color:var(--field-border)]"></div>
                            <h2 class="text-[11px] font-bold uppercase tracking-[0.22em] text-[color:var(--text-muted)]">
                                Datos de la agencia
                            </h2>
                            <div class="h-px flex-1 bg-[color:var(--field-border)]"></div>
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-[color:var(--text-secondary)]">
                                    Nombre de la agencia <span class="text-[color:var(--accent)]">*</span>
                                </label>
                                <input type="text" wire:model="name" class="field-input" placeholder="Ej: Viajes del Centro" autocomplete="organization">
                                @error('name')
                                    <p class="mt-2 text-sm text-[color:var(--error-text)]">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-[color:var(--text-secondary)]">RIF</label>
                                <input type="text" wire:model="identificationNumber" class="field-input" placeholder="J-00000000-0">
                                @error('identificationNumber')
                                    <p class="mt-2 text-sm text-[color:var(--error-text)]">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-[color:var(--text-secondary)]">Correo</label>
                                <input type="email" wire:model="email" class="field-input" placeholder="agencia@correo.com" autocomplete="email">
                                @error('email')
                                    <p class="mt-2 text-sm text-[color:var(--error-text)]">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-[color:var(--text-secondary)]">Representante</label>
                                <input type="text" wire:model="representativeName" class="field-input" autocomplete="name">
                                @error('representativeName')
                                    <p class="mt-2 text-sm text-[color:var(--error-text)]">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-[color:var(--text-secondary)]">Teléfono</label>
                                <input type="tel" wire:model="phone" class="field-input" autocomplete="tel">
                                @error('phone')
                                    <p class="mt-2 text-sm text-[color:var(--error-text)]">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-[color:var(--text-secondary)]">Teléfono adicional</label>
                                <input type="tel" wire:model="phoneAdditional" class="field-input">
                                @error('phoneAdditional')
                                    <p class="mt-2 text-sm text-[color:var(--error-text)]">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-[color:var(--text-secondary)]">Instagram</label>
                                <input type="text" wire:model="instagramUsername" class="field-input" placeholder="@agencia">
                                @error('instagramUsername')
                                    <p class="mt-2 text-sm text-[color:var(--error-text)]">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-[color:var(--text-secondary)]">Dirección</label>
                                <input type="text" wire:model="address" class="field-input" autocomplete="street-address">
                                @error('address')
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
