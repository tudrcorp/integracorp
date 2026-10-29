@php
    $rangeStatePath = $getStatePath();
    $rangeFieldId = 'drp-'.md5($rangeStatePath);
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <style>
        .ic-drp { --drp-accent: var(--primary-600, #2563eb); --drp-band: color-mix(in oklab, var(--primary-500, #3b82f6) 16%, transparent); font-size: 0.875rem; }
        .dark .ic-drp { --drp-accent: var(--primary-500, #3b82f6); --drp-band: color-mix(in oklab, var(--primary-400, #60a5fa) 20%, transparent); }
        .ic-drp-trigger { display: flex; width: 100%; align-items: center; gap: 0.5rem; border-radius: 0.5rem; padding: 0.5rem 0.75rem; text-align: start; background: #fff; box-shadow: 0 1px 2px rgb(0 0 0 / 0.05); outline: 1px solid rgb(3 7 18 / 0.1); transition: outline-color 150ms ease; }
        .ic-drp-trigger:hover { outline-color: rgb(3 7 18 / 0.2); }
        .ic-drp-trigger:focus-visible, .ic-drp-trigger[aria-expanded="true"] { outline: 2px solid var(--drp-accent); }
        .dark .ic-drp-trigger { background: rgb(255 255 255 / 0.05); outline-color: rgb(255 255 255 / 0.1); }
        .ic-drp-trigger .ic-drp-value { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: rgb(3 7 18); }
        .dark .ic-drp-trigger .ic-drp-value { color: #fff; }
        .ic-drp-trigger .ic-drp-placeholder { color: rgb(156 163 175); }
        .ic-drp-panel { margin-top: 0.5rem; border-radius: 0.75rem; padding: 0.75rem; background: #fff; outline: 1px solid rgb(3 7 18 / 0.08); box-shadow: 0 8px 24px rgb(0 0 0 / 0.06); }
        .dark .ic-drp-panel { background: rgb(24 24 27); outline-color: rgb(255 255 255 / 0.1); }
        .ic-drp-presets { display: flex; flex-wrap: wrap; gap: 0.375rem; margin-bottom: 0.75rem; }
        .ic-drp-chip { border-radius: 9999px; padding: 0.25rem 0.625rem; font-size: 0.75rem; font-weight: 500; color: rgb(55 65 81); background: rgb(243 244 246); transition: background-color 150ms ease, color 150ms ease; }
        .ic-drp-chip:hover, .ic-drp-chip:focus-visible { background: var(--drp-band); color: var(--drp-accent); outline: none; }
        .dark .ic-drp-chip { color: rgb(209 213 219); background: rgb(255 255 255 / 0.06); }
        .ic-drp-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem; }
        .ic-drp-title { font-weight: 600; color: rgb(3 7 18); text-transform: capitalize; }
        .dark .ic-drp-title { color: #fff; }
        .ic-drp-nav { display: inline-flex; width: 2rem; height: 2rem; align-items: center; justify-content: center; border-radius: 9999px; color: rgb(75 85 99); }
        .ic-drp-nav:hover, .ic-drp-nav:focus-visible { background: rgb(243 244 246); outline: none; }
        .dark .ic-drp-nav { color: rgb(209 213 219); }
        .dark .ic-drp-nav:hover, .dark .ic-drp-nav:focus-visible { background: rgb(255 255 255 / 0.08); }
        .ic-drp-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); row-gap: 0.25rem; }
        .ic-drp-weekday { padding-bottom: 0.25rem; text-align: center; font-size: 0.6875rem; font-weight: 600; color: rgb(107 114 128); }
        .ic-drp-cell { position: relative; display: flex; height: 2.25rem; align-items: center; justify-content: center; }
        .ic-drp-cell::before { content: ''; position: absolute; inset: 0.125rem 0; background: transparent; }
        .ic-drp-cell.is-between::before { background: var(--drp-band); }
        .ic-drp-cell.has-range.is-start::before { left: 50%; background: var(--drp-band); }
        .ic-drp-cell.has-range.is-end::before { right: 50%; background: var(--drp-band); }
        .ic-drp-cell.is-preview::before { opacity: 0.55; }
        .ic-drp-day { position: relative; z-index: 1; display: inline-flex; width: 2rem; height: 2rem; align-items: center; justify-content: center; border-radius: 9999px; color: rgb(31 41 55); font-variant-numeric: tabular-nums; transition: background-color 120ms ease, color 120ms ease; }
        .dark .ic-drp-day { color: rgb(229 231 235); }
        .ic-drp-day:hover, .ic-drp-day:focus-visible { background: rgb(229 231 235); outline: none; }
        .dark .ic-drp-day:hover, .dark .ic-drp-day:focus-visible { background: rgb(255 255 255 / 0.12); }
        .ic-drp-day.is-today { box-shadow: inset 0 0 0 1px var(--drp-accent); }
        .ic-drp-cell.is-start .ic-drp-day, .ic-drp-cell.is-end .ic-drp-day { background: var(--drp-accent); color: #fff; font-weight: 600; }
        .ic-drp-foot { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; margin-top: 0.75rem; padding-top: 0.625rem; border-top: 1px solid rgb(3 7 18 / 0.06); }
        .dark .ic-drp-foot { border-top-color: rgb(255 255 255 / 0.08); }
        .ic-drp-hint { font-size: 0.75rem; color: rgb(107 114 128); }
        .ic-drp-link { font-size: 0.75rem; font-weight: 600; color: var(--drp-accent); }
        .ic-drp-link.is-muted { color: rgb(107 114 128); }
        .ic-drp-link:hover { text-decoration: underline; }
    </style>

    <div
        id="{{ $rangeFieldId }}"
        wire:ignore
        x-data="{
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$rangeStatePath}')", isOptimisticallyLive: false) }},
            open: false,
            hover: null,
            viewYear: 2000,
            viewMonth: 0,
            months: ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'],
            weekdays: ['L', 'M', 'M', 'J', 'V', 'S', 'D'],
            init() {
                this.showMonthOf(this.from() ?? this.today());
            },
            iso(date) {
                return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
            },
            today() { return this.iso(new Date()); },
            valid(value) { return typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value) ? value : null; },
            from() { return this.valid(this.state?.from); },
            until() { return this.valid(this.state?.until); },
            human(value) { return value.split('-').reverse().join('/'); },
            showMonthOf(value) {
                const [year, month] = value.split('-').map(Number);
                this.viewYear = year;
                this.viewMonth = month - 1;
            },
            shift(step) {
                const date = new Date(this.viewYear, this.viewMonth + step, 1);
                this.viewYear = date.getFullYear();
                this.viewMonth = date.getMonth();
            },
            get title() { return this.months[this.viewMonth] + ' ' + this.viewYear; },
            get cells() {
                const lead = (new Date(this.viewYear, this.viewMonth, 1).getDay() + 6) % 7;
                const total = new Date(this.viewYear, this.viewMonth + 1, 0).getDate();
                const cells = [];
                for (let i = 0; i < lead; i++) { cells.push({ key: 'blank-' + i, value: null, day: null }); }
                for (let day = 1; day <= total; day++) {
                    const value = this.iso(new Date(this.viewYear, this.viewMonth, day));
                    cells.push({ key: value, value: value, day: day });
                }
                return cells;
            },
            pick(value) {
                const start = this.from();
                const end = this.until();
                this.hover = null;
                if (! start || end) {
                    this.state = { from: value, until: null };
                    return;
                }
                this.state = value < start ? { from: value, until: start } : { from: start, until: value };
            },
            bounds() {
                const start = this.from();
                if (! start) { return [null, null]; }
                const end = this.until();
                if (! end && this.hover) { return this.hover < start ? [this.hover, start] : [start, this.hover]; }
                return [start, end ?? start];
            },
            cellClass(value) {
                if (! value) { return ''; }
                const [start, end] = this.bounds();
                if (! start) { return ''; }
                const classes = [];
                if (value === start) { classes.push('is-start'); }
                if (value === end) { classes.push('is-end'); }
                if (value > start && value < end) { classes.push('is-between'); }
                if (start !== end && (value === start || value === end)) { classes.push('has-range'); }
                if (! this.until() && this.hover) { classes.push('is-preview'); }
                return classes.join(' ');
            },
            preset(key) {
                const now = new Date();
                const y = now.getFullYear();
                const m = now.getMonth();
                const ranges = {
                    today: [now, now],
                    last7: [new Date(y, m, now.getDate() - 6), now],
                    month: [new Date(y, m, 1), now],
                    previousMonth: [new Date(y, m - 1, 1), new Date(y, m, 0)],
                    last30: [new Date(y, m, now.getDate() - 29), now],
                    year: [new Date(y, 0, 1), now],
                };
                const [start, end] = ranges[key];
                this.state = { from: this.iso(start), until: this.iso(end) };
                this.hover = null;
                this.showMonthOf(this.iso(end));
            },
            clear() {
                this.state = { from: null, until: null };
                this.hover = null;
            },
            get summary() {
                const start = this.from();
                const end = this.until();
                if (! start) { return null; }
                if (! end) { return this.human(start); }
                return start === end ? this.human(start) : this.human(start) + ' – ' + this.human(end);
            },
            get hint() {
                if (! this.from()) { return 'Elige la fecha inicial.'; }
                if (! this.until()) { return 'Ahora elige la fecha final (o aplica para un solo día).'; }
                return 'Rango listo. Pulsa «Aplicar filtros».';
            },
        }"
        x-on:keydown.escape="if (open) { $event.stopPropagation(); open = false; }"
        class="ic-drp"
    >
        <button
            type="button"
            class="ic-drp-trigger"
            x-on:click="open = ! open; if (open) { showMonthOf(until() ?? from() ?? today()); }"
            x-bind:aria-expanded="open ? 'true' : 'false'"
            aria-controls="{{ $rangeFieldId }}-panel"
        >
            <x-filament::icon icon="heroicon-m-calendar-days" class="size-5 shrink-0 text-gray-400" />
            <span class="ic-drp-value" x-show="summary" x-text="summary"></span>
            <span class="ic-drp-value ic-drp-placeholder" x-show="! summary">Cualquier fecha</span>
            <x-filament::icon icon="heroicon-m-chevron-down" class="size-4 shrink-0 text-gray-400 transition" x-bind:class="open && 'rotate-180'" />
        </button>

        <div id="{{ $rangeFieldId }}-panel" class="ic-drp-panel" x-show="open" x-cloak x-transition.opacity.duration.150ms>
            <div class="ic-drp-presets" role="group" aria-label="Rangos rápidos">
                <button type="button" class="ic-drp-chip" x-on:click="preset('today')">Hoy</button>
                <button type="button" class="ic-drp-chip" x-on:click="preset('last7')">Últimos 7 días</button>
                <button type="button" class="ic-drp-chip" x-on:click="preset('month')">Este mes</button>
                <button type="button" class="ic-drp-chip" x-on:click="preset('previousMonth')">Mes anterior</button>
                <button type="button" class="ic-drp-chip" x-on:click="preset('last30')">Últimos 30 días</button>
                <button type="button" class="ic-drp-chip" x-on:click="preset('year')">Este año</button>
            </div>

            <div class="ic-drp-head">
                <button type="button" class="ic-drp-nav" x-on:click="shift(-1)" aria-label="Mes anterior">
                    <x-filament::icon icon="heroicon-m-chevron-left" class="size-5" />
                </button>
                <span class="ic-drp-title" x-text="title" aria-live="polite"></span>
                <button type="button" class="ic-drp-nav" x-on:click="shift(1)" aria-label="Mes siguiente">
                    <x-filament::icon icon="heroicon-m-chevron-right" class="size-5" />
                </button>
            </div>

            <div class="ic-drp-grid" x-on:mouseleave="hover = null">
                <template x-for="(weekday, index) in weekdays" x-bind:key="'weekday-' + index">
                    <span class="ic-drp-weekday" x-text="weekday"></span>
                </template>

                <template x-for="cell in cells" x-bind:key="cell.key">
                    <div class="ic-drp-cell" x-bind:class="cellClass(cell.value)">
                        <template x-if="cell.value">
                            <button
                                type="button"
                                class="ic-drp-day"
                                x-bind:class="cell.value === today() && 'is-today'"
                                x-text="cell.day"
                                x-on:click="pick(cell.value)"
                                x-on:mouseenter="if (from() && ! until()) { hover = cell.value; }"
                                x-bind:aria-label="human(cell.value)"
                                x-bind:aria-pressed="cellClass(cell.value).includes('is-start') || cellClass(cell.value).includes('is-end') ? 'true' : 'false'"
                            ></button>
                        </template>
                    </div>
                </template>
            </div>

            <div class="ic-drp-foot">
                <span class="ic-drp-hint" x-text="hint"></span>
                <span class="flex shrink-0 items-center gap-3">
                    <button type="button" class="ic-drp-link is-muted" x-show="from()" x-on:click="clear()">Limpiar</button>
                    <button type="button" class="ic-drp-link" x-on:click="open = false">Listo</button>
                </span>
            </div>
        </div>
    </div>
</x-dynamic-component>
