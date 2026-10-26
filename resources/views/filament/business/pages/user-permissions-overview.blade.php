<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
        {{-- Catálogo: buscar un permiso y ver cuántas personas lo tienen. --}}
        <aside
            class="xl:col-span-4"
            wire:ignore
            x-data="{
                catalog: @js($catalog),
                holders: @js((object) $holders),
                selected: @js($selected['id'] ?? null),
                query: '',
                module: 'all',
                onlyEmpty: false,
                fold(text) {
                    return String(text ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
                },
                count(id) {
                    return Number(this.holders[id] ?? 0);
                },
                groups(module) {
                    const query = this.fold(this.query.trim());
                    return module.groups
                        .map((group) => ({ name: group.name, items: group.items.filter((item) => (query === '' || this.fold(item.name + ' ' + group.name).includes(query)) && (! this.onlyEmpty || this.count(item.id) === 0)) }))
                        .filter((group) => group.items.length > 0);
                },
                get modules() {
                    return this.catalog.filter((module) => (this.module === 'all' || module.key === this.module) && this.groups(module).length > 0);
                },
                pick(id) {
                    this.selected = id;
                    this.$wire.selectPermission(id);
                },
            }"
            x-on:permission-holders-updated.window="holders = $event.detail.holders"
        >
            <div class="sticky top-20 space-y-3 rounded-2xl bg-white p-3 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <label class="relative block">
                    <span class="sr-only">Buscar un permiso</span>
                    <x-filament::icon icon="heroicon-m-magnifying-glass" class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                    <input
                        x-model.debounce.150ms="query"
                        x-on:keydown.escape="query = ''"
                        type="search"
                        placeholder="Buscar pantalla o acción…"
                        class="w-full rounded-xl border-0 bg-gray-50 py-2 ps-9 pe-3 text-sm text-gray-950 ring-1 ring-gray-950/10 placeholder:text-gray-400 focus:ring-2 focus:ring-primary-500 dark:bg-white/5 dark:text-white dark:ring-white/20"
                    />
                </label>

                <div class="flex flex-wrap gap-1.5">
                    <button type="button" x-on:click="module = 'all'" x-bind:class="module === 'all' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200'" class="rounded-full px-2.5 py-1 text-xs font-semibold">Todos</button>
                    <template x-for="item in catalog" :key="'f-' + item.key">
                        <button type="button" x-on:click="module = item.key" x-bind:class="module === item.key ? 'text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200'" x-bind:style="module === item.key ? 'background: ' + item.accent : ''" class="rounded-full px-2.5 py-1 text-xs font-semibold" x-text="item.label"></button>
                    </template>
                </div>

                <label class="inline-flex cursor-pointer items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                    <input type="checkbox" x-model="onlyEmpty" class="fi-checkbox-input" />
                    Solo permisos que nadie tiene
                </label>

                <div class="max-h-[65vh] space-y-3 overflow-y-auto pe-1">
                    <template x-for="item in modules" :key="'m-' + item.key">
                        <div>
                            <p class="mb-1 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <span class="size-2 rounded-full" x-bind:style="'background: ' + item.accent"></span>
                                <span x-text="item.label"></span>
                            </p>
                            <template x-for="group in groups(item)" :key="item.key + '-' + group.name">
                                <div class="mb-2">
                                    <p class="px-2 pb-0.5 text-[11px] font-medium text-gray-400 dark:text-gray-500" x-text="group.name"></p>
                                    <template x-for="permission in group.items" :key="permission.id">
                                        <button
                                            type="button"
                                            x-on:click="pick(permission.id)"
                                            x-bind:class="selected === permission.id ? 'bg-primary-50 ring-1 ring-primary-500 dark:bg-primary-500/10' : 'hover:bg-gray-50 dark:hover:bg-white/5'"
                                            class="flex w-full items-center justify-between gap-2 rounded-lg px-2 py-1.5 text-start text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                                        >
                                            <span class="min-w-0 flex-1 truncate text-gray-700 dark:text-gray-200" x-bind:title="permission.name" x-text="permission.name"></span>
                                            <span x-show="permission.action" class="shrink-0 rounded-full bg-amber-100 px-1.5 text-[10px] font-semibold uppercase text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">Acción</span>
                                            <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold tabular-nums" x-bind:class="count(permission.id) === 0 ? 'bg-gray-100 text-gray-400 dark:bg-white/5' : 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200'" x-text="count(permission.id)" x-bind:title="count(permission.id) + ' personas activas lo tienen'"></span>
                                        </button>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                    <p x-show="modules.length === 0" class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">Ningún permiso coincide.</p>
                </div>
            </div>
        </aside>

        {{-- Quién tiene el permiso elegido. --}}
        <section class="space-y-3 xl:col-span-8">
            @if ($selected)
                <div class="flex items-start gap-3 rounded-2xl bg-white p-4 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <span class="mt-1 size-3 shrink-0 rounded-full" style="background: {{ $selected['accent'] }}"></span>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $selected['module_label'] }} · {{ $selected['group'] }}</p>
                        <h2 class="text-lg font-semibold text-gray-950 dark:text-white">{{ $selected['name'] }}</h2>
                    </div>
                </div>
            @endif

            <div wire:loading.class="opacity-60" wire:target="selectPermission" class="transition-opacity">
                {{ $this->table }}
            </div>
        </section>
    </div>
</x-filament-panels::page>
