@php
    $stats = $stats ?? ['total' => 0, 'latest_at' => '—', 'latest_author' => '—'];
    $notes = $notes ?? [];
    $activityTitle = (string) ($activity_title ?? 'Actividad');
    $activityColor = (string) ($activity_color ?? '#6366f1');
@endphp

<div
    class="fi-scoped space-y-4"
    x-data="{
        search: '',
        matches(note) {
            const q = this.search.trim().toLowerCase();
            if (q === '') {
                return true;
            }
            return (note.search_blob ?? '').includes(q);
        },
        filteredNotes() {
            return @js($notes).filter((note) => this.matches(note));
        },
    }"
>
    <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-amber-200/70 bg-amber-50/80 px-4 py-3 dark:border-amber-500/30 dark:bg-amber-950/40">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-800/80 dark:text-amber-200/80">Total de notas</p>
            <p class="mt-1 text-2xl font-bold text-amber-950 dark:text-amber-100">{{ $stats['total'] }}</p>
        </div>
        <div class="rounded-2xl border border-sky-200/70 bg-sky-50/80 px-4 py-3 dark:border-sky-500/30 dark:bg-sky-950/40">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-sky-800/80 dark:text-sky-200/80">Última nota</p>
            <p class="mt-1 text-sm font-bold text-sky-950 dark:text-sky-100">{{ $stats['latest_at'] }}</p>
        </div>
        <div class="rounded-2xl border border-violet-200/70 bg-violet-50/80 px-4 py-3 dark:border-violet-500/30 dark:bg-violet-950/40">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-violet-800/80 dark:text-violet-200/80">Registrada por</p>
            <p class="mt-1 truncate text-sm font-bold text-violet-950 dark:text-violet-100">{{ $stats['latest_author'] }}</p>
        </div>
    </div>

    <div
        class="overflow-hidden rounded-2xl border border-gray-200/90 bg-gradient-to-br from-slate-50 via-white to-slate-100 p-4 shadow-sm dark:border-white/10 dark:from-slate-900/90 dark:via-slate-950 dark:to-slate-900/80"
    >
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Bitácora</p>
                <p class="mt-0.5 truncate text-base font-semibold text-gray-950 dark:text-white">{{ $activityTitle }}</p>
            </div>
            <span
                class="inline-flex shrink-0 items-center rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-wide"
                style="border-color: color-mix(in srgb, {{ $activityColor }} 35%, transparent); background: color-mix(in srgb, {{ $activityColor }} 12%, transparent); color: {{ $activityColor }};"
            >
                Seguimiento interno
            </span>
        </div>
    </div>

    @if (count($notes) > 0)
        <div class="rounded-2xl border border-gray-200/80 bg-white/90 p-4 shadow-sm dark:border-white/10 dark:bg-gray-900/70">
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Buscar en la bitácora
            </label>
            <input
                type="search"
                x-model.debounce.200ms="search"
                placeholder="Contenido, autor o fecha…"
                class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 shadow-sm outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-400/25 dark:border-white/15 dark:bg-gray-950 dark:text-gray-100"
            />
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                <span class="font-semibold text-gray-800 dark:text-gray-200" x-text="filteredNotes().length"></span>
                de {{ count($notes) }} nota(s) visibles
            </p>
        </div>
    @endif

    @if (count($notes) === 0)
        <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50/90 p-10 text-center dark:border-white/15 dark:bg-white/[0.03]">
            <x-filament::icon icon="heroicon-o-chat-bubble-left-right" class="mx-auto size-10 text-gray-400 dark:text-gray-500" />
            <p class="mt-3 text-sm font-semibold text-gray-700 dark:text-gray-200">Sin notas en la bitácora</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Las notas agregadas desde el Kanban o el seguimiento operativo aparecerán aquí en orden cronológico.
            </p>
        </div>
    @else
        <div class="relative space-y-4 pl-2">
            <div class="absolute bottom-2 left-[1.15rem] top-2 w-px bg-gradient-to-b from-amber-300/80 via-slate-200 to-transparent dark:from-amber-500/40 dark:via-white/10"></div>

            <template x-for="note in filteredNotes()" :key="note.id">
                <article class="relative pl-10">
                    <div
                        class="absolute left-0 top-3 flex size-9 items-center justify-center rounded-2xl border text-xs font-bold shadow-sm"
                        style="border-color: color-mix(in srgb, {{ $activityColor }} 35%, transparent); background: color-mix(in srgb, {{ $activityColor }} 14%, white); color: {{ $activityColor }};"
                        x-text="note.author_initials"
                    ></div>

                    <div class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm transition hover:border-amber-300/50 hover:shadow-md dark:border-white/10 dark:bg-gray-900/80 dark:hover:border-amber-500/35">
                        <div class="flex flex-wrap items-start justify-between gap-2 border-b border-gray-100 px-4 py-3 dark:border-white/10">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white" x-text="note.author_name"></p>
                                <p class="text-xs text-gray-500 dark:text-gray-400" x-show="note.author_email" x-text="note.author_email"></p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs font-semibold text-gray-700 dark:text-gray-200">
                                    <span x-text="note.created_at"></span>
                                    <span class="text-gray-400">·</span>
                                    <span x-text="note.created_time"></span>
                                </p>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400" x-text="note.created_at_human"></p>
                            </div>
                        </div>
                        <div class="px-4 py-4">
                            <p class="whitespace-pre-wrap text-sm leading-6 text-gray-700 dark:text-gray-200" x-text="note.content"></p>
                        </div>
                        <div class="flex items-center justify-between gap-2 border-t border-gray-100 bg-gray-50/80 px-4 py-2 text-[11px] text-gray-500 dark:border-white/10 dark:bg-white/[0.03] dark:text-gray-400">
                            <span class="inline-flex items-center gap-1">
                                <x-filament::icon icon="heroicon-m-hashtag" class="size-3.5" />
                                Nota #<span x-text="note.id"></span>
                            </span>
                            <span class="font-medium uppercase tracking-wide text-amber-700 dark:text-amber-300">Bitácora de actividad</span>
                        </div>
                    </div>
                </article>
            </template>

            <div
                x-show="filteredNotes().length === 0"
                x-cloak
                class="rounded-2xl border border-dashed border-gray-300 bg-white px-4 py-8 text-center dark:border-white/15 dark:bg-gray-900/50"
            >
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Sin coincidencias</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Ajusta el criterio de búsqueda para ver entradas de la bitácora.</p>
            </div>
        </div>
    @endif
</div>
