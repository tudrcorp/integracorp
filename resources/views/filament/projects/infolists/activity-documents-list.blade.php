@php
    $documents = $documents ?? [];
@endphp

<div
    class="fi-scoped space-y-4"
    x-data="{
        search: '',
        page: 1,
        perPage: 8,
        documents: @js($documents),
        init() {
            this.$watch('search', () => { this.page = 1 });
        },
        matches(doc) {
            const q = this.search.trim().toLowerCase();
            return q === '' || (doc.search_blob ?? '').includes(q);
        },
        filteredDocuments() {
            return this.documents.filter((doc) => this.matches(doc));
        },
        totalPages() {
            return Math.max(1, Math.ceil(this.filteredDocuments().length / this.perPage));
        },
        paginatedDocuments() {
            const start = (this.page - 1) * this.perPage;
            return this.filteredDocuments().slice(start, start + this.perPage);
        },
        goToPage(target) {
            this.page = Math.min(Math.max(1, target), this.totalPages());
        },
        toneClasses(tone) {
            const map = {
                primary: 'border-primary-400/35 bg-primary-500/10 text-primary-700 dark:text-primary-300',
                info: 'border-info-400/35 bg-info-500/10 text-info-700 dark:text-info-300',
                success: 'border-success-400/35 bg-success-500/10 text-success-700 dark:text-success-300',
                warning: 'border-warning-400/35 bg-warning-500/10 text-warning-700 dark:text-warning-300',
                danger: 'border-danger-400/35 bg-danger-500/10 text-danger-700 dark:text-danger-300',
                gray: 'border-gray-400/35 bg-gray-500/10 text-gray-700 dark:text-gray-300',
            };
            return map[tone] ?? map.primary;
        },
    }"
>
    @if (count($documents) === 0)
        <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50/90 p-10 text-center dark:border-white/15 dark:bg-white/[0.03]">
            <x-filament::icon icon="heroicon-o-folder-open" class="mx-auto size-10 text-gray-400 dark:text-gray-500" />
            <p class="mt-3 text-sm font-semibold text-gray-700 dark:text-gray-200">Sin documentos cargados</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Los archivos subidos desde el Kanban o el seguimiento de la actividad se listarán aquí con descarga directa.
            </p>
        </div>
    @else
        <div class="rounded-2xl border border-gray-200/80 bg-white/90 p-4 shadow-sm dark:border-white/10 dark:bg-gray-900/70">
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Buscar documento
            </label>
            <input
                type="search"
                x-model.debounce.200ms="search"
                placeholder="Nombre, extensión, tipo o responsable…"
                class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 shadow-sm outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-400/25 dark:border-white/15 dark:bg-gray-950 dark:text-gray-100"
            />
            <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500 dark:text-gray-400">
                <p>
                    <span class="font-semibold text-gray-800 dark:text-gray-200" x-text="filteredDocuments().length"></span>
                    resultado(s) · {{ count($documents) }} en total
                </p>
                <p>
                    Página <span class="font-semibold text-gray-800 dark:text-gray-200" x-text="page"></span>
                    de <span class="font-semibold text-gray-800 dark:text-gray-200" x-text="totalPages()"></span>
                </p>
            </div>
        </div>

        <div class="space-y-3">
            <template x-for="doc in paginatedDocuments()" :key="doc.id">
                <article class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm transition hover:border-sky-300/60 hover:shadow-md dark:border-white/10 dark:bg-gray-900/80 dark:hover:border-sky-500/40">
                    <div class="flex flex-wrap items-center gap-3 p-4">
                        <div
                            class="flex size-12 shrink-0 items-center justify-center rounded-2xl border text-xs font-bold uppercase"
                            :class="toneClasses(doc.tone)"
                            x-text="doc.extension"
                        ></div>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-gray-900 dark:text-white" x-text="doc.name"></p>
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                        <span x-text="doc.file_size_label"></span>
                                        <span class="text-gray-300 dark:text-gray-600"> · </span>
                                        <span x-text="doc.file_type ?? 'Tipo no especificado'"></span>
                                    </p>
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                        <span x-text="doc.uploader_name"></span>
                                        <span class="text-gray-300 dark:text-gray-600"> · </span>
                                        <span x-text="doc.uploaded_at"></span>
                                        <span class="text-gray-300 dark:text-gray-600"> · </span>
                                        <span x-text="doc.uploaded_at_human"></span>
                                    </p>
                                </div>
                                <span
                                    x-show="! doc.exists"
                                    class="shrink-0 rounded-full border border-amber-300/50 bg-amber-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-800 dark:border-amber-500/30 dark:bg-amber-950/40 dark:text-amber-200"
                                >
                                    Archivo no disponible
                                </span>
                            </div>
                        </div>

                        <div class="flex w-full shrink-0 flex-col justify-center gap-2 sm:w-auto">
                            <a
                                x-show="doc.download_url"
                                :href="doc.download_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center justify-center gap-2 rounded-xl border border-sky-200 bg-sky-50 px-4 py-2 text-xs font-semibold text-sky-800 transition hover:bg-sky-100 dark:border-sky-500/35 dark:bg-sky-500/15 dark:text-sky-100 dark:hover:bg-sky-500/25"
                            >
                                <x-filament::icon icon="heroicon-m-arrow-down-tray" class="size-4" />
                                Descargar
                            </a>
                            <p
                                x-show="! doc.download_url"
                                class="text-center text-[11px] font-medium text-gray-500 dark:text-gray-400"
                            >
                                Ruta no encontrada en almacenamiento
                            </p>
                        </div>
                    </div>
                </article>
            </template>

            <div
                x-show="filteredDocuments().length === 0"
                x-cloak
                class="rounded-2xl border border-dashed border-gray-300 bg-white px-4 py-8 text-center dark:border-white/15 dark:bg-gray-900/50"
            >
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Sin coincidencias</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Prueba con otro nombre, extensión o responsable.</p>
            </div>
        </div>

        <div
            x-show="totalPages() > 1"
            x-cloak
            class="flex flex-wrap items-center justify-center gap-2"
        >
            <button
                type="button"
                @click="goToPage(page - 1)"
                :disabled="page <= 1"
                class="rounded-xl border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 transition enabled:hover:bg-gray-50 disabled:opacity-40 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:enabled:hover:bg-white/10"
            >
                Anterior
            </button>
            <button
                type="button"
                @click="goToPage(page + 1)"
                :disabled="page >= totalPages()"
                class="rounded-xl border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 transition enabled:hover:bg-gray-50 disabled:opacity-40 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:enabled:hover:bg-white/10"
            >
                Siguiente
            </button>
        </div>
    @endif
</div>
