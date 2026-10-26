@php
    /** @var \App\Filament\Forms\Components\ModuleSelector $field */
    $statePath = $getStatePath();
    $modules = $field->getModules();
    $groups = [
        'panel' => ['title' => 'Paneles de trabajo', 'hint' => 'Cada uno abre un panel de INTEGRACORP.'],
        'special' => ['title' => 'Accesos especiales', 'hint' => 'No son paneles: cambian lo que la persona puede hacer. Asígnalos con cuidado.'],
    ];
    $labels = array_column($modules, 'label', 'key');
    $kinds = array_column($modules, 'kind', 'key');
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        wire:ignore
        x-data="{
            selected: $wire.$entangle(@js($statePath)),
            permissions: $wire.$entangle(@js($field->getPermissionsStatePath())),
            email: $wire.$entangle(@js($field->getEmailStatePath())),
            permissionModules: @js((object) $field->getPermissionModules()),
            labels: @js($labels),
            kinds: @js($kinds),
            initial: [],
            confirming: null,
            list() {
                const values = Array.isArray(this.selected) ? this.selected : Object.values(this.selected ?? {});
                return values.filter((value) => typeof value === 'string' && value !== '');
            },
            init() {
                this.initial = [...this.list()];
            },
            has(key) {
                return this.list().includes(key);
            },
            set(key, value) {
                const next = this.list().filter((item) => item !== key);
                if (value) { next.push(key); }
                this.selected = next;
            },
            toggle(key, confirmText) {
                if (this.has(key)) { this.set(key, false); this.confirming = null; return; }
                if (confirmText) { this.confirming = key; return; }
                this.set(key, true);
            },
            confirm(key) {
                this.set(key, true);
                this.confirming = null;
            },
            permissionCount(key) {
                const values = Array.isArray(this.permissions) ? this.permissions : Object.values(this.permissions ?? {});
                return values.reduce((total, id) => total + (this.permissionModules[Number(id)] === key ? 1 : 0), 0);
            },
            get internalEmail() {
                return String(this.email ?? '').trim().toLowerCase().endsWith('@tudrencasa.com');
            },
            countKind(kind) {
                return this.list().filter((key) => this.kinds[key] === kind).length;
            },
            get added() {
                return this.list().filter((key) => ! this.initial.includes(key));
            },
            get removed() {
                const current = this.list();
                return this.initial.filter((key) => ! current.includes(key));
            },
        }"
        class="space-y-5"
    >
        {{-- Resumen: qué tiene la persona y qué cambiará al guardar. --}}
        <div class="flex flex-col gap-3 rounded-2xl bg-gray-50 p-4 ring-1 ring-gray-950/5 sm:flex-row sm:items-center sm:justify-between dark:bg-white/5 dark:ring-white/10">
            <div class="min-w-0">
                <p class="text-sm font-semibold text-gray-950 dark:text-white">
                    <span x-show="list().length === 0">{{ $field->isOptionalForCommercialNetwork() ? 'Sin módulos internos: usará solo su panel comercial.' : 'Sin módulos: no podrá entrar a ningún panel interno.' }}</span>
                    <span x-show="list().length > 0">
                        <span x-text="countKind('panel') + (countKind('panel') === 1 ? ' panel' : ' paneles')"></span>
                        <span x-show="countKind('special') > 0" x-text="' · ' + countKind('special') + (countKind('special') === 1 ? ' acceso especial' : ' accesos especiales')"></span>
                    </span>
                </p>
                <div class="mt-2 flex flex-wrap gap-1.5" x-show="list().length > 0">
                    <template x-for="key in list()" :key="'chip-' + key">
                        <span class="inline-flex items-center gap-1 rounded-full bg-white px-2.5 py-0.5 text-xs font-medium text-gray-700 ring-1 ring-gray-950/10 dark:bg-white/10 dark:text-gray-200 dark:ring-white/10">
                            <span x-text="labels[key] ?? key"></span>
                            <button type="button" x-on:click="set(key, false)" class="text-gray-400 hover:text-gray-700 dark:hover:text-white" x-bind:aria-label="'Quitar ' + (labels[key] ?? key)">×</button>
                        </span>
                    </template>
                </div>
            </div>
            <div class="flex shrink-0 flex-wrap items-center gap-2 text-xs">
                <span x-show="added.length > 0" class="font-semibold text-emerald-600 dark:text-emerald-400" x-text="'+' + added.length + ' al guardar'"></span>
                <span x-show="removed.length > 0" class="font-semibold text-rose-600 dark:text-rose-400" x-text="'−' + removed.length + ' al guardar'"></span>
                <button type="button" x-show="added.length + removed.length > 0" x-on:click="selected = [...initial]; confirming = null" class="rounded-lg px-2 py-1 font-semibold text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/10">Deshacer</button>
                <button type="button" x-show="list().length > 0" x-on:click="selected = []; confirming = null" class="rounded-lg px-2 py-1 font-semibold text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-white/10">Quitar todos</button>
            </div>
        </div>

        @foreach ($groups as $kind => $group)
            @php($items = array_values(array_filter($modules, fn (array $module): bool => $module['kind'] === $kind)))
            @continue($items === [])

            <section class="space-y-2">
                <div>
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $group['title'] }}</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $group['hint'] }}</p>
                </div>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 2xl:grid-cols-3">
                    @foreach ($items as $module)
                        @php($key = $module['key'])
                        <div
                            wire:key="module-card-{{ $key }}"
                            class="rounded-2xl bg-white ring-1 transition dark:bg-white/5"
                            x-bind:class="has(@js($key)) ? 'shadow-sm ring-2' : 'ring-gray-950/10 hover:ring-gray-950/20 dark:ring-white/10 dark:hover:ring-white/20'"
                            x-bind:style="has(@js($key)) ? '--tw-ring-color: {{ $module['accent'] }}' : ''"
                        >
                            <button
                                type="button"
                                role="checkbox"
                                x-bind:aria-checked="has(@js($key))"
                                x-on:click="toggle(@js($key), @js($module['confirm']))"
                                class="flex w-full items-start gap-3 rounded-2xl p-3.5 text-start focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                            >
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl" style="background: {{ $module['accent'] }}1a; color: {{ $module['accent'] }}">
                                    <x-filament::icon :icon="$module['icon']" class="size-5" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="flex items-center justify-between gap-2">
                                        <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $module['label'] }}</span>
                                        <span
                                            class="flex size-5 shrink-0 items-center justify-center rounded-md ring-1 transition"
                                            x-bind:class="has(@js($key)) ? 'text-white ring-transparent' : 'ring-gray-300 dark:ring-white/20'"
                                            x-bind:style="has(@js($key)) ? 'background: {{ $module['accent'] }}' : ''"
                                        >
                                            <span x-show="has(@js($key))"><x-filament::icon icon="heroicon-m-check" class="size-3.5" /></span>
                                        </span>
                                    </span>
                                    <span class="mt-0.5 block text-xs leading-relaxed text-gray-500 dark:text-gray-400">{{ $module['description'] }}</span>
                                    @if ($module['path'])
                                        <span class="mt-1 block font-mono text-[11px] text-gray-400 dark:text-gray-500">{{ $module['path'] }}</span>
                                    @endif
                                </span>
                            </button>

                            {{-- Confirmación de los accesos delicados. --}}
                            <div x-show="confirming === @js($key)" x-collapse class="border-t border-rose-200 bg-rose-50 px-3.5 py-3 text-xs text-rose-900 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200" style="display: none">
                                <p>{{ $module['confirm'] }}</p>
                                <div class="mt-2 flex gap-2">
                                    <button type="button" x-on:click="confirm(@js($key))" class="rounded-lg bg-rose-600 px-3 py-1 font-semibold text-white hover:bg-rose-700">Sí, dar acceso</button>
                                    <button type="button" x-on:click="confirming = null" class="rounded-lg px-3 py-1 font-semibold hover:bg-rose-100 dark:hover:bg-white/10">Cancelar</button>
                                </div>
                            </div>

                            {{-- Avisos: lo que impediría usar el módulo o lo que se perderá al guardar. --}}
                            <div class="space-y-1 text-xs [&>p]:px-3.5 [&>p:last-child]:pb-3">
                                @if ($module['internal_email'])
                                    <p x-show="has(@js($key)) && ! internalEmail" class="flex items-start gap-1.5 text-amber-700 dark:text-amber-300" style="display: none">
                                        <x-filament::icon icon="heroicon-m-exclamation-triangle" class="mt-px size-3.5 shrink-0" />
                                        Su correo no es @tudrencasa.com: no podrá entrar a este panel.
                                    </p>
                                @endif
                                @if ($module['requires'])
                                    <p x-show="has(@js($key)) && ! has(@js($module['requires']))" class="flex items-start gap-1.5 text-amber-700 dark:text-amber-300" style="display: none">
                                        <x-filament::icon icon="heroicon-m-exclamation-triangle" class="mt-px size-3.5 shrink-0" />
                                        Para entrar necesita también {{ $labels[$module['requires']] ?? $module['requires'] }}.
                                    </p>
                                @endif
                                @if (in_array($key, ['NEGOCIOS', 'OPERACIONES', 'ADMINISTRACION', 'MARKETING', 'PROYECTOS', 'METRICAS'], true))
                                    <p x-show="has(@js($key)) && ! has('SUPERADMIN')" class="text-gray-500 dark:text-gray-400" style="display: none">
                                        <span x-show="permissionCount(@js($key)) > 0" x-text="permissionCount(@js($key)) + (permissionCount(@js($key)) === 1 ? ' permiso de menú' : ' permisos de menú')"></span>
                                        <span x-show="permissionCount(@js($key)) === 0" class="text-amber-700 dark:text-amber-300">Sin permisos marcados: verá el panel sin menú. Márcalos en la pestaña <strong>Permisos</strong>.</span>
                                    </p>
                                    <p x-show="! has(@js($key)) && initial.includes(@js($key)) && permissionCount(@js($key)) > 0" class="text-rose-600 dark:text-rose-400" style="display: none" x-text="'Al guardar se quitarán sus ' + permissionCount(@js($key)) + ' permisos de este módulo.'"></p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</x-dynamic-component>
