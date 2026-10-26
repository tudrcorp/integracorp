@php
    /** @var list<array<string, mixed>> $rows */
    $columns = [
        ['key' => 'name', 'label' => 'Persona', 'align' => 'start'],
        ['key' => 'department', 'label' => 'Área', 'align' => 'start'],
        ['key' => 'days', 'label' => 'Días', 'align' => 'end', 'hint' => 'Días con al menos un minuto activo'],
        ['key' => 'active', 'label' => 'Horas activas', 'align' => 'end'],
        ['key' => 'avg_day', 'label' => 'Por día', 'align' => 'end', 'hint' => 'Horas activas por día conectado'],
        ['key' => 'vs_area', 'label' => 'Frente a su área', 'align' => 'end', 'hint' => 'Su promedio diario frente al de su área'],
        ['key' => 'work_usage', 'label' => 'Uso de jornada', 'align' => 'end', 'hint' => 'Activo y reuniones en horario ÷ jornada esperada'],
        ['key' => 'overtime', 'label' => 'Extra', 'align' => 'end', 'hint' => 'Activo fuera de horario, fin de semana o feriado'],
        ['key' => 'records', 'label' => 'Registros', 'align' => 'end', 'hint' => 'Registros creados en el período'],
        ['key' => 'records_per_hour', 'label' => 'Por hora', 'align' => 'end', 'hint' => 'Registros creados por hora activa'],
        ['key' => 'last_seen', 'label' => 'Última conexión', 'align' => 'end'],
    ];
@endphp

<x-filament-widgets::widget class="corp-users-comparison">
    <x-filament::section
        heading="Comparativa por persona"
        description="Cada persona frente a su área y a la empresa (promedio de la empresa: {{ $companyAvgDay }} activas por día{{ $companyRecordsPerHour !== null ? ', '.number_format($companyRecordsPerHour, 1, ',', '.').' registros por hora activa' : '' }}). Clic en un nombre para ver sus días; clic en una columna para ordenar."
    >
        @if ($rows === [])
            <div class="flex h-40 flex-col items-center justify-center gap-2 text-center">
                <x-filament::icon icon="heroicon-o-users" class="size-8 text-gray-300 dark:text-gray-600" />
                <p class="text-sm text-gray-500 dark:text-gray-400">No hay personal con cuenta activa en este período.</p>
            </div>
        @else
            <div
                wire:key="corp-users-comparison-{{ $periodKey }}"
                x-data="{
                    rows: @js($rows),
                    sort: 'active',
                    dir: -1,
                    query: '',
                    get visible() {
                        const query = this.query.trim().toLowerCase();
                        const list = query === '' ? [...this.rows] : this.rows.filter((row) => (row.name + ' ' + row.department).toLowerCase().includes(query));
                        const key = this.sort;
                        return list.sort((a, b) => {
                            const left = a[key];
                            const right = b[key];
                            if (left === right) { return a.name.localeCompare(b.name); }
                            if (left === null) { return 1; }
                            if (right === null) { return -1; }
                            const order = typeof left === 'string' ? left.localeCompare(right) : left - right;
                            return order * this.dir;
                        });
                    },
                    order(key) {
                        if (this.sort === key) { this.dir = -this.dir; return; }
                        this.sort = key;
                        this.dir = ['name', 'department', 'last_seen'].includes(key) ? 1 : -1;
                    },
                    pct(value) { return (value > 0 ? '+' : '') + new Intl.NumberFormat('es-VE').format(value) + ' %'; },
                    num(value, digits = 0) { return new Intl.NumberFormat('es-VE', { minimumFractionDigits: digits, maximumFractionDigits: digits }).format(value); },
                }"
            >
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <label class="relative block w-full sm:w-72">
                        <span class="sr-only">Buscar persona o área</span>
                        <x-filament::icon icon="heroicon-m-magnifying-glass" class="pointer-events-none absolute start-2.5 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                        <input
                            type="search"
                            x-model.debounce.300ms="query"
                            placeholder="Buscar persona o área…"
                            class="w-full rounded-lg border-0 bg-white py-1.5 ps-8 pe-3 text-sm text-gray-950 ring-1 ring-gray-950/10 placeholder:text-gray-400 focus:ring-2 focus:ring-primary-500 dark:bg-white/5 dark:text-white dark:ring-white/20"
                        />
                    </label>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        <span x-text="visible.length"></span> de {{ count($rows) }} personas
                    </p>
                </div>

                <div class="max-h-[34rem] overflow-auto rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
                    <table class="w-full min-w-[1100px] text-sm">
                        <thead class="sticky top-0 z-10 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                            <tr>
                                @foreach ($columns as $column)
                                    <th class="px-3 py-2 font-medium {{ $column['align'] === 'end' ? 'text-end' : 'text-start' }}" @isset($column['hint']) title="{{ $column['hint'] }}" @endisset>
                                        <button
                                            type="button"
                                            x-on:click="order(@js($column['key']))"
                                            class="inline-flex items-center gap-1 uppercase hover:text-gray-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:hover:text-white"
                                        >
                                            {{ $column['label'] }}
                                            <span x-show="sort === @js($column['key'])" x-text="dir === 1 ? '↑' : '↓'" aria-hidden="true"></span>
                                        </button>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-950/5 dark:divide-white/5">
                            <template x-for="row in visible" :key="row.id">
                                <tr class="transition hover:bg-gray-50 dark:hover:bg-white/5">
                                    <td class="max-w-[16rem] px-3 py-2">
                                        <div class="flex items-center gap-1.5">
                                            <button
                                                type="button"
                                                x-on:click="$wire.dispatch('operations-dashboard-detail', { dimension: 'user_person', value: String(row.id) })"
                                                class="truncate text-start font-medium text-primary-600 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:text-primary-400"
                                                x-text="row.name"
                                                x-bind:title="'Ver los días de ' + row.name"
                                            ></button>
                                            <template x-if="row.monitor_url">
                                                <a x-bind:href="row.monitor_url" target="_blank" rel="noopener" class="shrink-0 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" title="Abrir en Actividad de usuarios (Negocios)">
                                                    <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" class="size-3.5" />
                                                </a>
                                            </template>
                                        </div>
                                    </td>
                                    <td class="max-w-[12rem] truncate px-3 py-2 text-gray-600 dark:text-gray-300" x-text="row.department"></td>
                                    <td class="px-3 py-2 text-end tabular-nums text-gray-700 dark:text-gray-200" x-text="num(row.days)"></td>
                                    <td class="whitespace-nowrap px-3 py-2 text-end tabular-nums font-semibold text-gray-950 dark:text-white">
                                        <span x-text="row.active_text"></span>
                                        <template x-if="row.change !== null">
                                            <span
                                                class="ms-1 text-xs font-medium"
                                                x-bind:style="'color: ' + (row.change >= 0 ? 'var(--ops-good-ink)' : 'var(--ops-bad-ink)')"
                                                x-text="pct(row.change)"
                                                title="Frente a {{ $previousLabel }}"
                                            ></span>
                                        </template>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-end tabular-nums text-gray-700 dark:text-gray-200" x-text="row.avg_day_text"></td>
                                    <td class="whitespace-nowrap px-3 py-2 text-end tabular-nums">
                                        <template x-if="row.vs_area !== null">
                                            <span
                                                class="font-medium"
                                                x-bind:style="'color: ' + (row.vs_area <= -40 ? 'var(--ops-bad-ink)' : (row.vs_area >= 20 ? 'var(--ops-good-ink)' : 'var(--ops-muted)'))"
                                                x-text="pct(row.vs_area)"
                                                x-bind:title="row.vs_company !== null ? 'Frente a la empresa: ' + pct(row.vs_company) : ''"
                                            ></span>
                                        </template>
                                        <template x-if="row.vs_area === null"><span class="text-gray-400">—</span></template>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-end tabular-nums">
                                        <template x-if="row.work_usage !== null">
                                            <span
                                                class="font-medium"
                                                x-bind:style="'color: ' + (row.work_usage < {{ \App\Support\Metrics\Corporate\CorporateUsersMetrics::LOW_WORK_USAGE }} ? 'var(--ops-bad-ink)' : (row.work_usage >= {{ \App\Support\Metrics\Corporate\CorporateUsersMetrics::GOOD_WORK_USAGE }} ? 'var(--ops-good-ink)' : 'var(--ops-muted)'))"
                                                x-text="row.work_usage + ' %'"
                                            ></span>
                                        </template>
                                        <template x-if="row.work_usage === null"><span class="text-gray-400" title="Sin jornada en el período">—</span></template>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-end tabular-nums text-gray-700 dark:text-gray-200" x-text="row.overtime_text"></td>
                                    <td class="px-3 py-2 text-end tabular-nums font-semibold text-gray-950 dark:text-white" x-text="num(row.records)"></td>
                                    <td class="px-3 py-2 text-end tabular-nums text-gray-700 dark:text-gray-200" x-text="row.records_per_hour === null ? '—' : num(row.records_per_hour, 1)"></td>
                                    <td class="whitespace-nowrap px-3 py-2 text-end tabular-nums" x-bind:class="row.last_seen === null || row.last_seen >= {{ \App\Support\Metrics\Corporate\CorporateUsersMetrics::IDLE_ACCOUNT_DAYS }} ? 'font-medium' : 'text-gray-600 dark:text-gray-300'" x-bind:style="row.last_seen === null || row.last_seen >= {{ \App\Support\Metrics\Corporate\CorporateUsersMetrics::IDLE_ACCOUNT_DAYS }} ? 'color: var(--ops-bad-ink)' : ''" x-text="row.last_seen_text"></td>
                                </tr>
                            </template>
                            <tr x-show="visible.length === 0" x-cloak>
                                <td colspan="{{ count($columns) }}" class="px-3 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Ninguna persona coincide con la búsqueda.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
