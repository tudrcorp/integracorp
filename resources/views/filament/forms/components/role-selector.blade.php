@php
    /** @var \App\Filament\Forms\Components\RoleSelector $field */
    $roles = $field->getRoles();
    $readOnly = $field->getReadOnlyRoles();
    $groups = collect($roles)->groupBy('group');
    $fields = array_column($roles, 'field');
    $labels = array_column($roles, 'label', 'field');
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        wire:ignore
        x-data="{
            @foreach ($roles as $role)
                {{ $role['field'] }}: $wire.$entangle(@js($role['path']), @js($role['live'])),
            @endforeach
            fields: @js($fields),
            labels: @js($labels),
            initial: {},
            init() {
                for (const field of this.fields) { this.initial[field] = !! this[field]; }
            },
            on(field) {
                return !! this[field];
            },
            toggle(field) {
                this[field] = ! this.on(field);
            },
            get active() {
                return this.fields.filter((field) => this.on(field));
            },
            get changed() {
                return this.fields.filter((field) => this.on(field) !== this.initial[field]);
            },
            undo() {
                for (const field of this.fields) {
                    if (this.on(field) !== this.initial[field]) { this[field] = this.initial[field]; }
                }
            },
        }"
        class="space-y-5"
    >
        {{-- Resumen: qué roles tiene y qué cambiará al guardar. --}}
        <div class="flex flex-col gap-3 rounded-2xl bg-gray-50 p-4 ring-1 ring-gray-950/5 sm:flex-row sm:items-center sm:justify-between dark:bg-white/5 dark:ring-white/10">
            <div class="min-w-0">
                <p class="text-sm font-semibold text-gray-950 dark:text-white">
                    <span x-show="active.length === 0">Sin roles: la persona usa solo los módulos y permisos asignados.</span>
                    <span x-show="active.length > 0" x-text="active.length + (active.length === 1 ? ' rol asignado' : ' roles asignados')"></span>
                </p>
                <div class="mt-2 flex flex-wrap gap-1.5" x-show="active.length > 0">
                    <template x-for="field in active" :key="'chip-' + field">
                        <span class="inline-flex items-center gap-1 rounded-full bg-white px-2.5 py-0.5 text-xs font-medium text-gray-700 ring-1 ring-gray-950/10 dark:bg-white/10 dark:text-gray-200 dark:ring-white/10">
                            <span x-text="labels[field]"></span>
                            <button type="button" x-on:click="toggle(field)" class="text-gray-400 hover:text-gray-700 dark:hover:text-white" x-bind:aria-label="'Quitar ' + labels[field]">×</button>
                        </span>
                    </template>
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-2 text-xs" x-show="changed.length > 0">
                <span class="font-semibold text-primary-600 dark:text-primary-400" x-text="changed.length + (changed.length === 1 ? ' cambio sin guardar' : ' cambios sin guardar')"></span>
                <button type="button" x-on:click="undo()" class="rounded-lg px-2 py-1 font-semibold text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/10">Deshacer</button>
            </div>
        </div>

        {{-- Agente y agencia a la vez casi siempre es un error de alta. --}}
        <div x-show="on('is_agent') && on('is_agency')" style="display: none" class="flex items-start gap-2 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-500/30">
            <x-filament::icon icon="heroicon-m-exclamation-triangle" class="mt-0.5 size-4 shrink-0" />
            <span>Esta cuenta quedó como <strong>agente</strong> y <strong>agencia</strong> a la vez. Normalmente es una u otra: revisa que sea intencional.</span>
        </div>

        @foreach ($groups as $group => $items)
            <section class="space-y-2">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $group }}</h3>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 2xl:grid-cols-3">
                    @foreach ($items as $role)
                        @php($key = $role['field'])
                        <button
                            type="button"
                            role="switch"
                            wire:key="role-card-{{ $key }}"
                            x-bind:aria-checked="on(@js($key))"
                            x-on:click="toggle(@js($key))"
                            class="flex w-full items-start gap-3 rounded-2xl bg-white p-3.5 text-start ring-1 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:bg-white/5"
                            x-bind:class="on(@js($key)) ? 'shadow-sm ring-2 ring-inset' : 'ring-gray-950/10 hover:ring-gray-950/20 dark:ring-white/10 dark:hover:ring-white/20'"
                            x-bind:style="on(@js($key)) ? '--tw-ring-color: {{ $role['accent'] }}' : ''"
                        >
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl" style="background: {{ $role['accent'] }}1a; color: {{ $role['accent'] }}">
                                <x-filament::icon :icon="$role['icon']" class="size-5" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center justify-between gap-2">
                                    <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $role['label'] }}</span>
                                    {{-- Interruptor visual: el estado real vive en el campo enlazado. --}}
                                    <span class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition" x-bind:style="'background: ' + (on(@js($key)) ? '{{ $role['accent'] }}' : 'rgb(156 163 175 / 0.45)')">
                                        <span class="absolute top-0.5 size-4 rounded-full bg-white shadow transition-all" x-bind:class="on(@js($key)) ? 'left-[1.125rem]' : 'left-0.5'"></span>
                                    </span>
                                </span>
                                <span class="mt-0.5 block text-xs leading-relaxed text-gray-500 dark:text-gray-400">{{ $role['description'] }}</span>
                                @if ($role['effect'] === 'restricts')
                                    <span class="mt-1.5 inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">
                                        <x-filament::icon icon="heroicon-m-funnel" class="size-3" />
                                        Restringe lo que ve
                                    </span>
                                @endif
                            </span>
                        </button>
                    @endforeach
                </div>
            </section>
        @endforeach

        {{-- Indicadores que no se editan aquí, para que se sepa si la persona los tiene. --}}
        <section class="space-y-2">
            <div>
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Otros indicadores</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">Solo lectura: se asignan fuera de este formulario.</p>
            </div>
            <div class="grid grid-cols-1 gap-2 md:grid-cols-3">
                @foreach ($readOnly as $role)
                    <div class="flex items-start gap-2.5 rounded-xl bg-gray-50 px-3 py-2.5 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                        <x-filament::icon :icon="$role['icon']" class="mt-0.5 size-4 shrink-0 text-gray-400" />
                        <div class="min-w-0">
                            <p class="flex items-center gap-2 text-sm font-medium text-gray-800 dark:text-gray-100">
                                {{ $role['label'] }}
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase',
                                    'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300' => $role['active'],
                                    'bg-gray-200 text-gray-600 dark:bg-white/10 dark:text-gray-400' => ! $role['active'],
                                ])>{{ $role['active'] ? 'Sí' : 'No' }}</span>
                            </p>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $role['description'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</x-dynamic-component>
