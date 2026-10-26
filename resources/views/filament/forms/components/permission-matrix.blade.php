@php
    /** @var \App\Filament\Forms\Components\PermissionMatrix $field */
    $statePath = $getStatePath();
    $departmentsPath = $field->getDepartmentsStatePath();
    $catalog = $field->getCatalog();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        wire:ignore
        class="pm-root"
        x-data="{
            catalog: @js($catalog),
            selected: $wire.$entangle(@js($statePath)),
            departments: $wire.$entangle(@js($departmentsPath)),
            initial: [],
            marks: {},
            index: {},
            active: null,
            query: '',
            onlyMarked: false,
            showChanges: false,
            fold(text) {
                return String(text ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
            },
            normalize(list) {
                const values = Array.isArray(list) ? list : Object.values(list ?? {});
                return [...new Set(values.map(Number).filter((id) => Number.isInteger(id) && id > 0))];
            },
            init() {
                for (const module of this.catalog) {
                    for (const group of module.groups) {
                        for (const item of group.items) {
                            item.search = this.fold(item.name + ' ' + group.name + ' ' + module.label);
                            this.index[item.id] = { name: item.name, group: group.name, module: module.key, moduleLabel: module.label, accent: module.accent };
                        }
                    }
                }
                this.initial = this.normalize(this.selected);
                this.rebuild();
                this.ensureActive();
                this.$watch('selected', () => this.rebuild());
                this.$watch('departments', () => this.ensureActive());
            },
            rebuild() {
                const marks = {};
                for (const id of this.normalize(this.selected)) { marks[id] = true; }
                this.marks = marks;
            },
            commit() {
                this.selected = Object.keys(this.marks).filter((id) => this.marks[id]).map(Number);
            },
            get assignedKeys() {
                const departments = Array.isArray(this.departments) ? this.departments : Object.values(this.departments ?? {});
                return departments.map(String);
            },
            get assigned() {
                return this.catalog.filter((module) => this.assignedKeys.includes(module.key));
            },
            ensureActive() {
                if (! this.assigned.some((module) => module.key === this.active)) {
                    this.active = this.assigned.length > 0 ? this.assigned[0].key : null;
                }
            },
            get searching() {
                return this.query.trim() !== '';
            },
            visibleGroups(module) {
                const query = this.fold(this.query.trim());
                return module.groups
                    .map((group) => ({ name: group.name, items: group.items.filter((item) => (query === '' || item.search.includes(query)) && (! this.onlyMarked || this.marks[item.id])) }))
                    .filter((group) => group.items.length > 0);
            },
            get modulesInView() {
                const modules = this.searching || this.onlyMarked ? this.assigned : this.assigned.filter((module) => module.key === this.active);
                return modules.filter((module) => this.visibleGroups(module).length > 0);
            },
            countIn(items) {
                return items.reduce((total, item) => total + (this.marks[item.id] ? 1 : 0), 0);
            },
            markedInModule(module) {
                return module.groups.reduce((total, group) => total + this.countIn(group.items), 0);
            },
            visibleItems(module) {
                return this.visibleGroups(module).flatMap((group) => group.items);
            },
            toggle(id) {
                this.marks[id] = ! this.marks[id];
                this.commit();
            },
            setAll(items, value) {
                for (const item of items) { this.marks[item.id] = value; }
                this.commit();
            },
            allOn(items) {
                return items.length > 0 && items.every((item) => this.marks[item.id]);
            },
            get effective() {
                return Object.keys(this.marks).filter((id) => this.marks[id] && this.index[id] && this.assignedKeys.includes(this.index[id].module)).map(Number);
            },
            get totalAvailable() {
                return this.assigned.reduce((total, module) => total + module.total, 0);
            },
            get added() {
                return this.effective.filter((id) => ! this.initial.includes(id));
            },
            get removed() {
                const effective = this.effective;
                return this.initial.filter((id) => ! effective.includes(id));
            },
            get outsideModules() {
                const modules = new Set();
                for (const id of Object.keys(this.marks)) {
                    if (this.marks[id] && this.index[id] && ! this.assignedKeys.includes(this.index[id].module)) { modules.add(this.index[id].moduleLabel); }
                }
                return [...modules];
            },
            undo(id) {
                this.marks[id] = ! this.marks[id];
                this.commit();
            },
            reset() {
                this.selected = [...this.initial];
                this.showChanges = false;
            },
            describe(id) {
                const item = this.index[id];
                return item ? item.moduleLabel + ' · ' + item.group + ' › ' + item.name : 'Permiso #' + id;
            },
            focusSearch(event) {
                const tag = (document.activeElement?.tagName ?? '').toUpperCase();
                if (['INPUT', 'TEXTAREA', 'SELECT'].includes(tag) || document.activeElement?.isContentEditable) { return; }
                event.preventDefault();
                this.$refs.search.focus();
            },
        }"
        x-on:keydown.window.slash="focusSearch($event)"
    >
        {{-- Sin módulos: no hay nada que configurar todavía. --}}
        <template x-if="assigned.length === 0">
            <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-5 py-10 text-center dark:border-white/15 dark:bg-white/5">
                <x-filament::icon icon="heroicon-o-squares-plus" class="mx-auto size-8 text-gray-400" />
                <p class="mt-2 text-sm font-semibold text-gray-950 dark:text-white">Sin módulos asignados</p>
                <p class="mx-auto mt-1 max-w-md text-sm text-gray-500 dark:text-gray-400">Ve a la pestaña <strong>Módulos</strong>, asigna al menos un panel y vuelve aquí: los permisos aparecen al instante.</p>
            </div>
        </template>

        <template x-if="assigned.length > 0">
            <div class="space-y-4">
                {{-- Barra de herramientas: buscar en todo, ver solo lo marcado y el total. --}}
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <label class="relative block w-full sm:max-w-md">
                        <span class="sr-only">Buscar un permiso en todos los módulos</span>
                        <x-filament::icon icon="heroicon-m-magnifying-glass" class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                        <input
                            x-ref="search"
                            x-model.debounce.150ms="query"
                            x-on:keydown.escape="query = ''"
                            type="search"
                            placeholder="Buscar en todos los módulos… (tecla /)"
                            class="w-full rounded-xl border-0 bg-white py-2 ps-9 pe-3 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 placeholder:text-gray-400 focus:ring-2 focus:ring-primary-500 dark:bg-white/5 dark:text-white dark:ring-white/20"
                        />
                    </label>

                    <div class="flex flex-wrap items-center gap-3">
                        <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                            <input type="checkbox" x-model="onlyMarked" class="fi-checkbox-input" />
                            Solo marcados
                        </label>
                        <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold tabular-nums text-gray-700 dark:bg-white/10 dark:text-gray-200">
                            <span x-text="effective.length"></span> de <span x-text="totalAvailable"></span> permisos
                        </span>
                    </div>
                </div>

                {{-- Pestañas de módulo: cambian al instante, con su avance. --}}
                <div x-show="! searching && ! onlyMarked" class="-mx-1 flex gap-2 overflow-x-auto px-1 pt-1 pb-2" role="tablist" aria-label="Módulos del usuario">
                    <template x-for="module in assigned" :key="module.key">
                        <button
                            type="button"
                            role="tab"
                            x-on:click="active = module.key"
                            x-bind:aria-selected="active === module.key"
                            x-bind:class="active === module.key ? 'bg-white shadow-sm ring-2 ring-inset dark:bg-white/10' : 'bg-gray-50 ring-1 ring-gray-950/5 hover:bg-white dark:bg-white/5 dark:ring-white/10 dark:hover:bg-white/10'"
                            x-bind:style="active === module.key ? '--tw-ring-color: ' + module.accent : ''"
                            class="flex min-w-[9.5rem] shrink-0 flex-col gap-1.5 rounded-xl px-3 py-2 text-start transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                        >
                            <span class="flex items-center justify-between gap-2">
                                <span class="flex items-center gap-1.5 text-sm font-semibold text-gray-950 dark:text-white">
                                    <span class="size-2 rounded-full" x-bind:style="'background: ' + module.accent"></span>
                                    <span x-text="module.label"></span>
                                </span>
                                <span class="text-xs tabular-nums text-gray-500 dark:text-gray-400" x-text="markedInModule(module) + '/' + module.total"></span>
                            </span>
                            <span class="h-1 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                                <span class="block h-full rounded-full transition-all" x-bind:style="'background: ' + module.accent + '; width: ' + (module.total > 0 ? markedInModule(module) * 100 / module.total : 0) + '%'"></span>
                            </span>
                        </button>
                    </template>
                </div>

                {{-- Contenido: el módulo activo, o los resultados de la búsqueda en todos. --}}
                <template x-for="module in modulesInView" :key="'m-' + module.key">
                    <section class="space-y-3">
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-950/5 pb-2 dark:border-white/10">
                            <div class="min-w-0">
                                <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-950 dark:text-white">
                                    <span class="size-2.5 rounded-full" x-bind:style="'background: ' + module.accent"></span>
                                    <span x-text="module.label"></span>
                                    <span class="text-xs font-normal text-gray-500 dark:text-gray-400" x-text="searching ? visibleItems(module).length + ' coincidencias' : module.subtitle"></span>
                                </h3>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <button type="button" x-on:click="setAll(visibleItems(module), true)" class="rounded-lg px-2.5 py-1 text-xs font-semibold text-primary-600 hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-white/5" x-text="searching ? 'Marcar resultados' : 'Marcar todo el módulo'"></button>
                                <button type="button" x-on:click="setAll(visibleItems(module), false)" class="rounded-lg px-2.5 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5" x-text="searching ? 'Quitar resultados' : 'Quitar todo'"></button>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-3 lg:grid-cols-2 2xl:grid-cols-3">
                            <template x-for="group in visibleGroups(module)" :key="module.key + '-' + group.name">
                                <div class="overflow-hidden rounded-xl bg-white ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                                    <div class="flex items-center justify-between gap-2 border-s-4 bg-gray-50 px-3 py-2 dark:bg-white/5" x-bind:style="'border-color: ' + module.accent">
                                        <span class="min-w-0 truncate text-xs font-semibold uppercase tracking-wide text-gray-700 dark:text-gray-200" x-text="group.name"></span>
                                        <span class="flex shrink-0 items-center gap-2">
                                            <span class="text-xs tabular-nums text-gray-500 dark:text-gray-400" x-text="countIn(group.items) + '/' + group.items.length"></span>
                                            <button
                                                type="button"
                                                x-on:click="setAll(group.items, ! allOn(group.items))"
                                                class="rounded-md px-2 py-0.5 text-xs font-semibold text-primary-600 hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-white/10"
                                                x-text="allOn(group.items) ? 'Quitar todos' : 'Marcar todos'"
                                            ></button>
                                        </span>
                                    </div>
                                    <ul class="divide-y divide-gray-950/5 dark:divide-white/5">
                                        <template x-for="item in group.items" :key="item.id">
                                            <li>
                                                <label class="flex cursor-pointer items-start gap-2.5 px-3 py-2 transition hover:bg-gray-50 dark:hover:bg-white/5">
                                                    <input type="checkbox" class="fi-checkbox-input mt-0.5" x-bind:checked="!! marks[item.id]" x-on:change="toggle(item.id)" />
                                                    <span class="min-w-0 flex-1 text-sm leading-snug text-gray-700 dark:text-gray-200" x-text="item.name"></span>
                                                    <span x-show="item.action" class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-800 dark:bg-amber-500/15 dark:text-amber-300" title="Habilita una acción dentro de una pantalla, no un ítem de menú">Acción</span>
                                                    <span x-show="! initial.includes(item.id) && marks[item.id]" class="size-1.5 shrink-0 self-center rounded-full bg-emerald-500" title="Se agregará al guardar"></span>
                                                    <span x-show="initial.includes(item.id) && ! marks[item.id]" class="size-1.5 shrink-0 self-center rounded-full bg-rose-500" title="Se quitará al guardar"></span>
                                                </label>
                                            </li>
                                        </template>
                                    </ul>
                                </div>
                            </template>
                        </div>
                    </section>
                </template>

                <template x-if="modulesInView.length === 0">
                    <div class="rounded-xl bg-gray-50 px-5 py-8 text-center text-sm text-gray-500 dark:bg-white/5 dark:text-gray-400">
                        <span x-show="searching">Ningún permiso coincide con «<span x-text="query"></span>» en los módulos del usuario.</span>
                        <span x-show="! searching && onlyMarked">Este usuario todavía no tiene permisos marcados en sus módulos.</span>
                    </div>
                </template>

                {{-- Permisos de módulos que el usuario ya no tiene: se quitan al guardar. --}}
                <template x-if="outsideModules.length > 0">
                    <div class="flex items-start gap-2 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-500/30">
                        <x-filament::icon icon="heroicon-m-exclamation-triangle" class="mt-0.5 size-4 shrink-0" />
                        <span>Tiene permisos en <strong x-text="outsideModules.join(', ')"></strong>, pero ese módulo no está asignado. Se quitarán al guardar, salvo que lo asignes en la pestaña <strong>Módulos</strong>.</span>
                    </div>
                </template>

                {{-- Resumen de cambios: qué pasará al guardar, con deshacer. --}}
                <div
                    x-show="added.length + removed.length > 0"
                    x-transition.opacity
                    class="sticky bottom-3 z-10 rounded-2xl bg-white/95 p-3 shadow-lg ring-1 ring-gray-950/10 backdrop-blur dark:bg-gray-900/95 dark:ring-white/15"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm text-gray-700 dark:text-gray-200">
                            <span class="font-semibold">Cambios sin guardar:</span>
                            <span class="ms-1 font-semibold text-emerald-600 dark:text-emerald-400" x-show="added.length > 0" x-text="'+' + added.length + (added.length === 1 ? ' agregado' : ' agregados')"></span>
                            <span class="ms-1 font-semibold text-rose-600 dark:text-rose-400" x-show="removed.length > 0" x-text="'−' + removed.length + (removed.length === 1 ? ' quitado' : ' quitados')"></span>
                        </p>
                        <div class="flex items-center gap-1.5">
                            <button type="button" x-on:click="showChanges = ! showChanges" class="rounded-lg px-2.5 py-1 text-xs font-semibold text-primary-600 hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-white/5" x-text="showChanges ? 'Ocultar detalle' : 'Ver detalle'"></button>
                            <button type="button" x-on:click="reset()" class="rounded-lg px-2.5 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5">Deshacer todo</button>
                        </div>
                    </div>
                    <div x-show="showChanges" x-collapse class="mt-2 max-h-60 space-y-1 overflow-y-auto">
                        <template x-for="id in added" :key="'a-' + id">
                            <div class="flex items-center justify-between gap-2 rounded-lg bg-emerald-50 px-2.5 py-1.5 text-xs text-emerald-900 dark:bg-emerald-500/10 dark:text-emerald-200">
                                <span class="min-w-0 truncate"><strong>+</strong> <span x-text="describe(id)"></span></span>
                                <button type="button" x-on:click="undo(id)" class="shrink-0 font-semibold underline-offset-2 hover:underline">Deshacer</button>
                            </div>
                        </template>
                        <template x-for="id in removed" :key="'r-' + id">
                            <div class="flex items-center justify-between gap-2 rounded-lg bg-rose-50 px-2.5 py-1.5 text-xs text-rose-900 dark:bg-rose-500/10 dark:text-rose-200">
                                <span class="min-w-0 truncate"><strong>−</strong> <span x-text="describe(id)"></span></span>
                                <button type="button" x-show="index[id] && assignedKeys.includes(index[id].module)" x-on:click="undo(id)" class="shrink-0 font-semibold underline-offset-2 hover:underline">Deshacer</button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </template>
    </div>
</x-dynamic-component>
