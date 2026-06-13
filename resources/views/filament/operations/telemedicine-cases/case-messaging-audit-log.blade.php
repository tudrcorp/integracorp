@php
    $caseStatus = (string) ($caseStatus ?? '—');
    $managedBy = (string) ($managedBy ?? '—');
    $stats = $stats ?? [];
    $participants = $participants ?? [];
    $messages = $messages ?? [];
    $total = (int) ($stats['total'] ?? count($messages));
@endphp

<div
    class="fi-scoped fi-case-messaging-audit space-y-4"
    x-data="{
        search: '',
        authorId: 'all',
        sort: 'asc',
        messages: @js($messages),
        init() {
            this.$nextTick(() => this.scrollToLatest());
        },
        filteredMessages() {
            const q = this.search.trim().toLowerCase();
            let rows = this.messages.filter((message) => {
                const authorOk = this.authorId === 'all' || String(message.author_id) === String(this.authorId);
                const searchOk = q === '' || (message.search_blob ?? '').includes(q);
                return authorOk && searchOk;
            });
            if (this.sort === 'desc') {
                rows = [...rows].reverse();
            }
            return rows;
        },
        visibleCount() {
            return this.filteredMessages().length;
        },
        setAuthor(id) {
            this.authorId = id;
            this.$nextTick(() => this.scrollToLatest());
        },
        toggleSort() {
            this.sort = this.sort === 'asc' ? 'desc' : 'asc';
            this.$nextTick(() => this.scrollToLatest());
        },
        scrollToLatest() {
            const container = this.$refs.threadScroll;
            if (! container) {
                return;
            }
            container.scrollTop = this.sort === 'asc' ? container.scrollHeight : 0;
        },
        scrollToOldest() {
            const container = this.$refs.threadScroll;
            if (! container) {
                return;
            }
            container.scrollTop = this.sort === 'asc' ? 0 : container.scrollHeight;
        },
        laneClass(message) {
            return message.align === 'right' ? 'is-right' : 'is-left';
        },
        threadClass(message) {
            return 'is-' + (message.thread_position ?? 'single');
        },
        toneClass(message) {
            return 'tone-' + (message.tone ?? 'sky');
        },
        viewerClass(message) {
            return message.is_viewer ? 'is-viewer' : '';
        },
    }"
>
    <div class="fi-case-messaging-audit-shell overflow-hidden rounded-[1.75rem] border border-slate-200/85 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.18)] ring-1 ring-slate-200/55 dark:border-white/10 dark:ring-white/10">
        <div class="fi-case-messaging-audit-toolbar border-b border-slate-200/80 bg-white/95 px-4 py-3 dark:border-white/10 dark:bg-slate-950/90">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div class="flex-1">
                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Buscar en el hilo
                    </label>
                    <input
                        type="search"
                        x-model.debounce.200ms="search"
                        placeholder="Texto, analista, correo, ID o fecha…"
                        class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm outline-none transition focus:border-violet-400 focus:ring-2 focus:ring-violet-400/25 dark:border-white/15 dark:bg-gray-950 dark:text-gray-100"
                    />
                </div>
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="rounded-full border border-gray-200 bg-gray-50 px-3 py-1 font-semibold text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
                        {{ $caseStatus }} · {{ $managedBy }}
                    </span>
                    <button
                        type="button"
                        @click="toggleSort()"
                        class="inline-flex items-center gap-1 rounded-full border border-gray-200 bg-white px-3 py-1.5 font-semibold text-gray-700 transition hover:border-violet-300 hover:text-violet-700 dark:border-white/15 dark:bg-gray-950 dark:text-gray-200"
                    >
                        <span x-text="sort === 'asc' ? 'Cronológico ↑' : 'Recientes primero ↓'"></span>
                    </button>
                    <button
                        type="button"
                        @click="scrollToOldest()"
                        class="inline-flex items-center rounded-full border border-gray-200 bg-white px-3 py-1.5 font-semibold text-gray-700 transition hover:border-sky-300 hover:text-sky-700 dark:border-white/15 dark:bg-gray-950 dark:text-gray-200"
                    >
                        Inicio
                    </button>
                    <button
                        type="button"
                        @click="scrollToLatest()"
                        class="inline-flex items-center rounded-full border border-violet-300/60 bg-violet-500/10 px-3 py-1.5 font-semibold text-violet-800 transition hover:bg-violet-500/15 dark:text-violet-200"
                    >
                        Final del hilo
                    </button>
                </div>
            </div>

            @if (count($participants) > 0)
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Participantes</span>
                    <button
                        type="button"
                        @click="setAuthor('all')"
                        :class="authorId === 'all'
                            ? 'border-violet-400 bg-violet-500/15 text-violet-800 dark:border-violet-400/50 dark:bg-violet-500/20 dark:text-violet-200'
                            : 'border-gray-200 bg-gray-50 text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300'"
                        class="rounded-full border px-3 py-1 text-xs font-semibold transition"
                    >
                        Todo el hilo
                    </button>
                    @foreach ($participants as $participant)
                        <button
                            type="button"
                            @click="setAuthor(@js((string) $participant['id']))"
                            :class="String(authorId) === @js((string) $participant['id'])
                                ? 'border-violet-400 bg-violet-500/15 text-violet-800 dark:border-violet-400/50 dark:bg-violet-500/20 dark:text-violet-200'
                                : 'border-gray-200 bg-gray-50 text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300'"
                            class="fi-case-messaging-audit-participant-chip tone-{{ $participant['tone'] }} inline-flex items-center gap-2 rounded-full border px-2.5 py-1 text-xs font-semibold transition"
                            title="{{ $participant['email'] }}"
                        >
                            <span class="fi-case-messaging-audit-participant-avatar" aria-hidden="true">{{ $participant['initials'] }}</span>
                            <span>{{ $participant['name'] }}</span>
                            <span class="opacity-70">({{ $participant['message_count'] }})</span>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        @if ($total === 0)
            <div class="fi-case-messaging-audit-empty p-12 text-center">
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Sin mensajes en este hilo</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    La conversación entre analistas aparecerá aquí en orden cronológico cuando se intercambien mensajes por el chat operativo.
                </p>
            </div>
        @else
            <div
                x-ref="threadScroll"
                class="fi-case-messaging-audit-thread max-h-[min(70vh,52rem)] overflow-y-auto px-3 py-4 sm:px-5"
                aria-label="Hilo de conversación del caso"
            >
                <div class="fi-case-messaging-audit-thread-inner mx-auto max-w-4xl space-y-1">
                    <div
                        x-show="visibleCount() > 0"
                        x-cloak
                        class="mb-3 flex items-center justify-between gap-2 rounded-xl border border-gray-200/70 bg-white/70 px-3 py-2 text-xs dark:border-white/10 dark:bg-white/5"
                    >
                        <p class="text-gray-600 dark:text-gray-300">
                            <span class="font-bold text-gray-900 dark:text-white" x-text="visibleCount()"></span>
                            mensaje(s) en el hilo visible
                        </p>
                        <p class="font-semibold text-gray-500 dark:text-gray-400">Lectura continua · sin paginación</p>
                    </div>

                    <template x-for="message in filteredMessages()" :key="message.id">
                        <div class="space-y-1">
                            <div
                                x-show="message.show_date_divider"
                                x-cloak
                                class="fi-case-messaging-audit-date-divider"
                            >
                                <span x-text="message.date_label"></span>
                            </div>

                            <div
                                class="fi-case-messaging-audit-message"
                                :class="[laneClass(message), threadClass(message), toneClass(message), viewerClass(message)]"
                            >
                                <div
                                    x-show="message.show_avatar"
                                    x-cloak
                                    class="fi-case-messaging-audit-avatar"
                                    :class="toneClass(message)"
                                    x-text="message.author_initials"
                                    aria-hidden="true"
                                ></div>
                                <div
                                    x-show="! message.show_avatar"
                                    x-cloak
                                    class="fi-case-messaging-audit-avatar-spacer"
                                    aria-hidden="true"
                                ></div>

                                <div class="fi-case-messaging-audit-bubble-wrap">
                                    <div
                                        x-show="message.show_author_header"
                                        x-cloak
                                        class="fi-case-messaging-audit-author"
                                    >
                                        <span class="font-semibold" x-text="message.author_name"></span>
                                        <span
                                            x-show="message.is_viewer"
                                            x-cloak
                                            class="fi-case-messaging-audit-you-badge"
                                        >Usted</span>
                                    </div>

                                    <div class="fi-case-messaging-audit-bubble">
                                        <p class="fi-case-messaging-audit-text" x-text="message.body"></p>
                                    </div>

                                    <div class="fi-case-messaging-audit-meta">
                                        <span x-text="message.created_at_time"></span>
                                        <span class="opacity-50">·</span>
                                        <span x-text="message.created_at_human"></span>
                                        <span class="opacity-50">·</span>
                                        <span class="font-mono text-[10px] opacity-60">#<span x-text="message.id"></span></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <div
                        x-show="visibleCount() === 0"
                        x-cloak
                        class="rounded-2xl border border-dashed border-gray-300 bg-white/60 p-8 text-center dark:border-white/15 dark:bg-gray-900/50"
                    >
                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Sin coincidencias en el hilo</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Ajuste la búsqueda o el participante seleccionado.</p>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
