@php
    $documents = $documents ?? [];
    $caseCode = (string) ($caseCode ?? '—');
    $defaultPhone = (string) ($defaultPhone ?? '');
    $defaultEmail = (string) ($defaultEmail ?? '');
    $patientName = (string) ($patientName ?? 'Paciente');
    $documentFilters = collect($documentFilters ?? [])
        ->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')
        ->map(fn (string $value): string => trim($value))
        ->unique()
        ->values()
        ->all();
    $categories = collect($documents)->pluck('category')->unique()->values()->all();
    $filters = collect($categories)
        ->merge($documentFilters)
        ->unique()
        ->sort()
        ->values()
        ->all();
    $total = count($documents);
    $available = collect($documents)->where('exists', true)->count();
@endphp

<div
    class="fi-scoped space-y-4"
    x-data="{
        search: '',
        category: 'all',
        page: 1,
        perPage: 5,
        perPageOptions: [5, 10, 20, 50],
        documents: @js($documents),
        init() {
            this.$watch('search', () => { this.page = 1 });
            this.$watch('category', () => { this.page = 1 });
            this.$watch('perPage', () => { this.page = 1 });
        },
        matches(doc) {
            const q = this.search.trim().toLowerCase();
            const categoryOk = this.category === 'all'
                || doc.category === this.category
                || (Array.isArray(doc.types) && doc.types.includes(this.category));
            const searchOk = q === '' || (doc.search_blob ?? '').includes(q);
            return categoryOk && searchOk;
        },
        filteredDocuments() {
            return this.documents.filter((doc) => this.matches(doc));
        },
        visibleCount() {
            return this.filteredDocuments().length;
        },
        totalPages() {
            const count = this.visibleCount();
            return Math.max(1, Math.ceil(count / this.perPage));
        },
        paginatedDocuments() {
            const filtered = this.filteredDocuments();
            const start = (this.page - 1) * this.perPage;
            return filtered.slice(start, start + this.perPage);
        },
        paginationFrom() {
            if (this.visibleCount() === 0) {
                return 0;
            }
            return (this.page - 1) * this.perPage + 1;
        },
        paginationTo() {
            return Math.min(this.page * this.perPage, this.visibleCount());
        },
        goToPage(target) {
            const next = Math.min(Math.max(1, target), this.totalPages());
            this.page = next;
            this.$nextTick(() => {
                this.$refs.documentsList?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        },
        setCategory(value) {
            this.category = value;
            this.page = 1;
        },
    }"
>
    <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-sky-200/70 bg-sky-50/80 px-4 py-3 dark:border-sky-500/30 dark:bg-sky-950/40">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-sky-700/80 dark:text-sky-300/80">Caso</p>
            <p class="mt-1 text-lg font-bold text-sky-900 dark:text-sky-100">{{ $caseCode }}</p>
        </div>
        <div class="rounded-2xl border border-emerald-200/70 bg-emerald-50/80 px-4 py-3 dark:border-emerald-500/30 dark:bg-emerald-950/40">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700/80 dark:text-emerald-300/80">Documentos</p>
            <p class="mt-1 text-lg font-bold text-emerald-900 dark:text-emerald-100">{{ $total }}</p>
        </div>
        <div class="rounded-2xl border border-cyan-200/70 bg-cyan-50/80 px-4 py-3 dark:border-cyan-500/30 dark:bg-cyan-950/40">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-cyan-700/80 dark:text-cyan-300/80">Disponibles</p>
            <p class="mt-1 text-lg font-bold text-cyan-900 dark:text-cyan-100">{{ $available }}</p>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200/80 bg-white/90 p-4 shadow-sm dark:border-white/10 dark:bg-gray-900/70">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div class="flex-1">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Buscar documento o referencia
                </label>
                <input
                    type="search"
                    x-model.debounce.200ms="search"
                    placeholder="Referencia, tipo, nombre de archivo, coordinación, orden…"
                    class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm outline-none ring-0 transition focus:border-sky-400 focus:ring-2 focus:ring-sky-400/25 dark:border-white/15 dark:bg-gray-950 dark:text-gray-100"
                />
            </div>
            <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                <p>
                    <span class="font-semibold text-gray-800 dark:text-gray-200" x-text="visibleCount()"></span>
                    resultado(s) · {{ $total }} en total
                </p>
                <label class="flex items-center gap-2">
                    <span class="font-semibold uppercase tracking-wide">Por página</span>
                    <select
                        x-model.number="perPage"
                        class="rounded-lg border border-gray-200 bg-white px-2 py-1 text-sm font-semibold text-gray-800 dark:border-white/15 dark:bg-gray-950 dark:text-gray-100"
                    >
                        <template x-for="size in perPageOptions" :key="size">
                            <option :value="size" x-text="size"></option>
                        </template>
                    </select>
                </label>
            </div>
        </div>

        <div class="mt-3 flex flex-wrap gap-2">
            <button
                type="button"
                @click="setCategory('all')"
                :class="category === 'all'
                    ? 'border-sky-400 bg-sky-500/15 text-sky-800 dark:border-sky-400/50 dark:bg-sky-500/20 dark:text-sky-200'
                    : 'border-gray-200 bg-gray-50 text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300'"
                class="rounded-full border px-3 py-1 text-xs font-semibold transition"
            >
                TODOS
            </button>
            @foreach ($filters as $filterCategory)
                <button
                    type="button"
                    @click="setCategory(@js($filterCategory))"
                    :class="category === @js($filterCategory)
                        ? 'border-sky-400 bg-sky-500/15 text-sky-800 dark:border-sky-400/50 dark:bg-sky-500/20 dark:text-sky-200'
                        : 'border-gray-200 bg-gray-50 text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300'"
                    class="rounded-full border px-3 py-1 text-xs font-semibold transition"
                >
                    {{ mb_strtoupper($filterCategory) }}
                </button>
            @endforeach
        </div>
    </div>

    @if ($total === 0)
        <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50/90 p-8 text-center dark:border-white/15 dark:bg-gray-900/50">
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Sin documentación asociada al caso</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Los archivos de coordinación, cotizaciones, órdenes y referencias médicas aparecerán aquí automáticamente.
            </p>
        </div>
    @else
        <div x-ref="documentsList" class="space-y-3">
            <div
                x-show="visibleCount() > 0"
                x-cloak
                class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-gray-200/70 bg-gray-50/80 px-3 py-2 text-xs dark:border-white/10 dark:bg-white/5"
            >
                <p class="text-gray-600 dark:text-gray-300">
                    Mostrando
                    <span class="font-bold text-gray-900 dark:text-white" x-text="paginationFrom()"></span>–<span class="font-bold text-gray-900 dark:text-white" x-text="paginationTo()"></span>
                    de <span class="font-bold text-gray-900 dark:text-white" x-text="visibleCount()"></span>
                </p>
                <p class="font-semibold text-gray-500 dark:text-gray-400">
                    Página <span x-text="page"></span> de <span x-text="totalPages()"></span>
                </p>
            </div>

            <template x-for="doc in paginatedDocuments()" :key="doc.uid">
                <article
                    class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm transition hover:border-sky-300/60 hover:shadow-md dark:border-white/10 dark:bg-gray-900/80 dark:hover:border-sky-500/40"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 px-4 py-3 dark:border-white/10">
                        <div class="min-w-0 flex-1 space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="inline-flex rounded-full border px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wide"
                                    :class="{
                                        'border-primary-400/40 bg-primary-500/10 text-primary-700 dark:text-primary-300': doc.category_tone === 'primary',
                                        'border-info-400/40 bg-info-500/10 text-info-700 dark:text-info-300': doc.category_tone === 'info',
                                        'border-warning-400/40 bg-warning-500/10 text-warning-700 dark:text-warning-300': doc.category_tone === 'warning',
                                        'border-success-400/40 bg-success-500/10 text-success-700 dark:text-success-300': doc.category_tone === 'success',
                                        'border-danger-400/40 bg-danger-500/10 text-danger-700 dark:text-danger-300': doc.category_tone === 'danger',
                                        'border-gray-400/40 bg-gray-500/10 text-gray-700 dark:text-gray-300': doc.category_tone === 'gray',
                                    }"
                                    x-text="doc.category"
                                ></span>
                                <span
                                    class="inline-flex rounded-full border border-gray-300/50 bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-600 dark:border-white/15 dark:bg-white/10 dark:text-gray-300"
                                    x-text="doc.extension"
                                ></span>
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                    :class="doc.exists
                                        ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300'
                                        : 'bg-rose-500/15 text-rose-700 dark:text-rose-300'"
                                    x-text="doc.exists ? 'Disponible' : 'No encontrado'"
                                ></span>
                            </div>
                            <p class="truncate text-sm font-bold text-gray-900 dark:text-white" x-text="doc.document_name"></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400" x-text="doc.reference_detail"></p>
                        </div>
                        <div class="text-right">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">Referencia</p>
                            <p class="font-mono text-sm font-bold text-sky-700 dark:text-sky-300" x-text="doc.reference"></p>
                        </div>
                    </div>

                    <div class="grid gap-3 px-4 py-3 sm:grid-cols-[1fr_auto] sm:items-center">
                        <div class="space-y-1 text-sm">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">Tipo(s) de documento</p>
                            <p class="text-gray-700 dark:text-gray-200" x-text="doc.types_label"></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                <span x-text="doc.uploaded_at_label"></span>
                                <span x-show="doc.uploaded_at_relative" x-text="' · ' + doc.uploaded_at_relative"></span>
                            </p>
                        </div>
                        <div class="flex shrink-0 flex-wrap items-center justify-end gap-2">
                            <button
                                type="button"
                                :disabled="!doc.exists"
                                @click="$wire.mountAction('sendCaseDocument', {
                                    file_path: doc.file_path,
                                    document_name: doc.document_name,
                                    focus: 'both',
                                    default_phone: @js($defaultPhone),
                                    default_email: @js($defaultEmail),
                                    patient_name: @js($patientName),
                                })"
                                :class="doc.exists
                                    ? 'border-violet-400/50 bg-violet-500/10 text-violet-800 hover:bg-violet-500/20 dark:text-violet-200'
                                    : 'cursor-not-allowed border-gray-200/60 bg-gray-100/80 text-gray-400 opacity-60 dark:border-white/10 dark:bg-white/5 dark:text-gray-500'"
                                class="inline-flex items-center justify-center gap-1.5 rounded-full border px-3 py-2 text-xs font-bold transition"
                                title="Enviar por WhatsApp o correo"
                            >
                                Enviar documentos
                            </button>
                            <a
                                :href="doc.download_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                :class="doc.exists
                                    ? 'border-cyan-400/50 bg-cyan-500/10 text-cyan-800 hover:bg-cyan-500/20 dark:text-cyan-200'
                                    : 'pointer-events-none border-gray-200/60 bg-gray-100/80 text-gray-400 opacity-60 dark:border-white/10 dark:bg-white/5 dark:text-gray-500'"
                                class="inline-flex items-center justify-center gap-1.5 rounded-full border px-4 py-2 text-xs font-bold transition"
                            >
                                Descargar
                            </a>
                        </div>
                    </div>
                </article>
            </template>

            <div
                x-show="visibleCount() === 0"
                x-cloak
                class="rounded-2xl border border-dashed border-amber-300/60 bg-amber-50/80 p-6 text-center dark:border-amber-500/30 dark:bg-amber-950/30"
            >
                <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">Sin resultados para el filtro actual</p>
                <p class="mt-1 text-xs text-amber-800/80 dark:text-amber-300/80">Pruebe otro término o seleccione «Todos».</p>
            </div>

            <nav
                x-show="visibleCount() > 0 && totalPages() > 1"
                x-cloak
                class="flex flex-wrap items-center justify-center gap-2 rounded-2xl border border-gray-200/80 bg-white/90 px-3 py-3 shadow-sm dark:border-white/10 dark:bg-gray-900/70"
                aria-label="Paginación de documentos"
            >
                <button
                    type="button"
                    @click="goToPage(1)"
                    :disabled="page <= 1"
                    class="rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5"
                >
                    Primera
                </button>
                <button
                    type="button"
                    @click="goToPage(page - 1)"
                    :disabled="page <= 1"
                    class="rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5"
                >
                    Anterior
                </button>

                <div class="flex items-center gap-1 px-1">
                    <template x-for="pageNumber in Array.from({ length: totalPages() }, (_, i) => i + 1)" :key="pageNumber">
                        <button
                            type="button"
                            x-show="totalPages() <= 7 || pageNumber === 1 || pageNumber === totalPages() || Math.abs(pageNumber - page) <= 1"
                            @click="goToPage(pageNumber)"
                            :class="page === pageNumber
                                ? 'border-sky-400 bg-sky-500/15 text-sky-800 dark:border-sky-400/50 dark:bg-sky-500/20 dark:text-sky-200'
                                : 'border-gray-200 bg-gray-50 text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300'"
                            class="min-w-[2rem] rounded-lg border px-2 py-1.5 text-xs font-bold transition"
                            x-text="pageNumber"
                        ></button>
                    </template>
                    <span
                        x-show="totalPages() > 7 && page > 3"
                        class="px-1 text-xs text-gray-400"
                    >…</span>
                </div>

                <button
                    type="button"
                    @click="goToPage(page + 1)"
                    :disabled="page >= totalPages()"
                    class="rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5"
                >
                    Siguiente
                </button>
                <button
                    type="button"
                    @click="goToPage(totalPages())"
                    :disabled="page >= totalPages()"
                    class="rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5"
                >
                    Última
                </button>
            </nav>
        </div>
    @endif
</div>
