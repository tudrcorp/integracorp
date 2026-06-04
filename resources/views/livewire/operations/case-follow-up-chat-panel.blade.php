@php
    use App\Support\Operations\CaseFollowUpChatManager;
    use Illuminate\Support\Str;

    $currentUserId = auth()->id();

    $initialsFromName = static function (?string $name): string {
        if (blank($name)) {
            return '?';
        }

        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($parts === []) {
            return '?';
        }

        if (count($parts) === 1) {
            return mb_strtoupper(mb_substr($parts[0], 0, 2));
        }

        return mb_strtoupper(mb_substr($parts[0], 0, 1).mb_substr($parts[array_key_last($parts)], 0, 1));
    };
@endphp

<div
    class="fi-operations-case-chat-root"
    x-data="operationsCaseChatPanel()"
    wire:poll.3s="pollHeartbeat"
>
    @if ($isOpen)
        <div
            class="fi-operations-case-chat-window fi-operations-case-chat--ios"
            :class="{ 'is-minimized': minimized, 'is-restoring': restoring, 'is-dragging': dragging }"
            :style="{ left: posX + 'px', top: posY + 'px', right: 'auto', bottom: 'auto' }"
            role="dialog"
            aria-modal="true"
            aria-labelledby="ops-case-chat-title"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-[0.97] translate-y-2"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        >
            <div
                class="fi-operations-case-chat-header"
                x-on:mousedown.prevent="startDrag($event)"
                x-on:dblclick.prevent="if (minimized) { toggleMinimize(); }"
            >
                <div class="fi-operations-case-chat-drag-grip" aria-hidden="true">
                    <span></span>
                </div>

                <div class="fi-operations-case-chat-header-toolbar">
                    <button
                        type="button"
                        x-on:click.stop="toggleMinimize()"
                        class="fi-operations-case-chat-ios-btn"
                        x-bind:aria-label="minimized ? 'Restaurar ventana' : 'Minimizar ventana'"
                    >
                        <span x-show="! minimized" x-cloak>
                            <x-filament::icon icon="heroicon-o-minus" class="size-5" />
                        </span>
                        <span x-show="minimized" x-cloak>
                            <x-filament::icon icon="heroicon-o-arrows-pointing-out" class="size-5" />
                        </span>
                    </button>

                    <div class="fi-operations-case-chat-header-center">
                        <h2 id="ops-case-chat-title" class="fi-operations-case-chat-title">
                            Chat de seguimiento
                        </h2>
                        <p class="fi-operations-case-chat-subtitle">
                            <span x-show="! minimized">En seguimiento</span>
                            <span x-show="minimized" x-cloak>Minimizado · doble clic para expandir</span>
                            @if ($cases->isNotEmpty())
                                · {{ $cases->count() }} activo{{ $cases->count() === 1 ? '' : 's' }}
                            @endif
                            @if ($totalUnread > 0)
                                · {{ $totalUnread }} sin leer
                            @endif
                        </p>
                    </div>

                    <button
                        type="button"
                        wire:click="closePanel"
                        class="fi-operations-case-chat-ios-btn fi-operations-case-chat-ios-btn-close"
                        aria-label="Cerrar chat"
                    >
                        <x-filament::icon icon="heroicon-o-x-mark" class="size-5" />
                    </button>
                </div>
            </div>

            <div class="fi-operations-case-chat-body-shell">
                <div class="fi-operations-case-chat-body">
                    <aside class="fi-operations-case-chat-sidebar">
                        <div class="fi-operations-case-chat-sidebar-head">
                            <p class="fi-operations-case-chat-sidebar-label">Casos activos</p>
                            <span class="fi-operations-case-chat-sidebar-count">{{ $cases->count() }}</span>
                        </div>

                        <div class="fi-operations-case-chat-sidebar-search">
                            <label class="fi-operations-case-chat-ios-search">
                                <x-filament::icon icon="heroicon-m-magnifying-glass" class="size-4 shrink-0 opacity-45" />
                                <input
                                    type="search"
                                    wire:model.live.debounce.300ms="caseSearch"
                                    placeholder="Buscar"
                                    class="fi-operations-case-chat-ios-search-input"
                                    autocomplete="off"
                                />
                            </label>
                        </div>

                        <div class="fi-operations-case-chat-case-list" role="listbox" aria-label="Casos en seguimiento">
                            @forelse ($cases as $case)
                                @php
                                    $unread = $unreadByCase[$case->id] ?? 0;
                                    $isSelected = $selectedCaseId === $case->id;
                                    $patientName = $case->patient_name ?? $case->telemedicinePatient?->full_name ?? 'Paciente';
                                @endphp
                                <button
                                    type="button"
                                    wire:click="selectCase({{ $case->id }})"
                                    role="option"
                                    aria-selected="{{ $isSelected ? 'true' : 'false' }}"
                                    class="fi-operations-case-chat-case-item {{ $isSelected ? 'is-selected' : '' }} {{ $unread > 0 ? 'has-unread' : '' }}"
                                    wire:key="ops-case-chat-item-{{ $case->id }}"
                                >
                                    <span class="fi-operations-case-chat-case-avatar" aria-hidden="true">
                                        {{ $initialsFromName($patientName) }}
                                    </span>

                                    <span class="fi-operations-case-chat-case-content">
                                        <span class="fi-operations-case-chat-case-item-top">
                                            <span class="fi-operations-case-chat-case-code">{{ $case->code }}</span>
                                        </span>
                                        <span class="fi-operations-case-chat-case-patient">
                                            {{ Str::limit($patientName, 34) }}
                                        </span>
                                    </span>

                                    @if ($unread > 0)
                                        <span class="fi-operations-case-chat-case-unread">{{ $unread > 9 ? '9+' : $unread }}</span>
                                    @endif
                                </button>
                            @empty
                                <div class="fi-operations-case-chat-empty-sidebar">
                                    <span class="fi-operations-case-chat-empty-icon" aria-hidden="true">
                                        <x-filament::icon icon="heroicon-o-inbox" class="size-7" />
                                    </span>
                                    <p class="fi-operations-case-chat-empty-title">Sin casos en seguimiento</p>
                                    <p class="fi-operations-case-chat-empty-text">
                                        Aparecerán aquí cuando un caso pase a EN SEGUIMIENTO.
                                    </p>
                                </div>
                            @endforelse
                        </div>
                    </aside>

                    <section class="fi-operations-case-chat-thread">
                        @if ($selectedCase)
                            @php
                                $selectedPatient = $selectedCase->patient_name ?? $selectedCase->telemedicinePatient?->full_name ?? 'Paciente';
                                $doctorName = $selectedCase->telemedicineDoctor?->full_name ?? '—';
                            @endphp

                            <div class="fi-operations-case-chat-thread-header">
                                <div class="fi-operations-case-chat-thread-header-main">
                                    <div class="fi-operations-case-chat-thread-header-top">
                                        <p class="fi-operations-case-chat-thread-code">{{ $selectedCase->code }}</p>
                                        <span @class([
                                            'fi-operations-case-chat-managed-badge is-compact',
                                            'is-atenmedi' => mb_strtoupper((string) $selectedCase->managed_by) === 'ATENMEDI',
                                            'is-tdg' => mb_strtoupper((string) $selectedCase->managed_by) === 'TDG',
                                        ])>
                                            {{ $selectedCase->managed_by ?? '—' }}
                                        </span>
                                    </div>
                                    <p class="fi-operations-case-chat-thread-patient">{{ $selectedPatient }}</p>
                                    <p class="fi-operations-case-chat-thread-meta">
                                        Dr(a). {{ $doctorName }}
                                    </p>
                                </div>
                            </div>

                            <div class="fi-operations-case-chat-thread-pane">
                                <div
                                    class="fi-operations-case-chat-messages"
                                    x-ref="messages"
                                    wire:key="ops-case-chat-messages-{{ $selectedCaseId }}"
                                    x-init="resizeComposerInput(); bindMessagesScrollListener(); $nextTick(() => scrollMessagesToBottom({ force: true }))"
                                    @scroll="onMessagesScroll()"
                                    aria-live="polite"
                                    aria-relevant="additions"
                                    wire:loading.class="is-syncing"
                                    wire:target="pollHeartbeat, sendMessage, selectCase"
                                >
                                <div class="fi-operations-case-chat-messages-inner">
                                @php
                                    $previousDate = null;
                                @endphp

                                @forelse ($messages as $message)
                                    @php
                                        $isMine = (int) $message->user_id === (int) $currentUserId;
                                        $authorName = $message->user?->name ?? $message->user?->email ?? 'Analista';
                                        $messageDate = optional($message->created_at)->timezone(config('app.timezone'));
                                        $dateLabel = $messageDate?->isToday()
                                            ? 'Hoy'
                                            : ($messageDate?->isYesterday() ? 'Ayer' : $messageDate?->translatedFormat('d M Y'));
                                        $showDateDivider = $messageDate && $dateLabel !== $previousDate;
                                        $previousDate = $dateLabel;
                                    @endphp

                                    @if ($showDateDivider)
                                        <div class="fi-operations-case-chat-date-divider" wire:key="ops-case-date-{{ $message->id }}">
                                            <span>{{ $dateLabel }}</span>
                                        </div>
                                    @endif

                                    <div
                                        wire:key="ops-case-msg-{{ $message->id }}"
                                        class="fi-operations-case-chat-message {{ $isMine ? 'is-mine' : 'is-theirs' }}"
                                    >
                                        <div class="fi-operations-case-chat-bubble-wrap">
                                            @unless ($isMine)
                                                <p class="fi-operations-case-chat-author">{{ $authorName }}</p>
                                            @endunless
                                            <div class="fi-operations-case-chat-bubble">
                                                <p class="fi-operations-case-chat-text">{{ trim($message->body) }}</p>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="fi-operations-case-chat-thread-empty">
                                        <span class="fi-operations-case-chat-empty-icon is-large" aria-hidden="true">
                                            <x-filament::icon icon="heroicon-o-chat-bubble-bottom-center-text" class="size-8" />
                                        </span>
                                        <p class="fi-operations-case-chat-empty-title">Inicie la conversación</p>
                                        <p class="fi-operations-case-chat-empty-text">
                                            Los analistas de ambos lados pueden escribir aquí mientras el caso esté en seguimiento.
                                        </p>
                                    </div>
                                @endforelse

                                <div
                                    x-ref="messagesEnd"
                                    class="fi-operations-case-chat-messages-end"
                                    aria-hidden="true"
                                ></div>
                                </div>
                            </div>

                            <form
                                class="fi-operations-case-chat-composer"
                                x-ref="composer"
                                x-on:submit.prevent="submitMessage()"
                            >
                                <label for="ops-case-chat-input" class="sr-only">Mensaje</label>
                                <div class="fi-operations-case-chat-composer-box">
                                    <textarea
                                        id="ops-case-chat-input"
                                        wire:model.live="messageBody"
                                        rows="1"
                                        maxlength="5000"
                                        placeholder="Mensaje"
                                        class="fi-operations-case-chat-input"
                                        x-ref="composerInput"
                                    x-init="resizeComposerInput()"
                                    x-on:input="resizeComposerInput()"
                                    x-on:keydown.enter.prevent="if (! $event.shiftKey) { submitMessage(); }"
                                    ></textarea>
                                    <button
                                        type="submit"
                                        wire:loading.attr="disabled"
                                        wire:target="sendMessage"
                                        class="fi-operations-case-chat-send-btn"
                                        aria-label="Enviar mensaje"
                                        title="Enviar"
                                    >
                                        <span wire:loading.remove wire:target="sendMessage">
                                            <x-filament::icon icon="heroicon-m-arrow-up" class="size-[1.125rem] stroke-[2.5]" />
                                        </span>
                                        <span wire:loading wire:target="sendMessage">
                                            <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </span>
                                    </button>
                                </div>
                                @error('messageBody')
                                    <p class="fi-operations-case-chat-error">{{ $message }}</p>
                                @enderror
                            </form>
                            </div>
                        @else
                            <div class="fi-operations-case-chat-thread-placeholder">
                                <span class="fi-operations-case-chat-empty-icon is-large" aria-hidden="true">
                                    <x-filament::icon icon="heroicon-o-cursor-arrow-rays" class="size-8" />
                                </span>
                                <p class="fi-operations-case-chat-empty-title">Seleccione un caso</p>
                                <p class="fi-operations-case-chat-empty-text">
                                    Elija un caso de la lista para ver y enviar mensajes.
                                </p>
                            </div>
                        @endif
                    </section>
                </div>
            </div>
        </div>
    @endif
</div>

@script
<script>
    Alpine.data('operationsCaseChatPanel', () => ({
        posX: 96,
        posY: 72,
        dragging: false,
        dragOffsetX: 0,
        dragOffsetY: 0,
        pendingScroll: false,
        pendingScrollForce: false,
        scrollDebounceTimer: null,
        scrollLayoutTimer: null,
        messagesObserver: null,
        composerResizeObserver: null,
        pinTimelineToBottom: true,
        messagesScrollListenerBound: false,
        livewireComponentId: null,
        minimized: false,
        restoring: false,
        minimizeSyncTimer: null,

        init() {
            this.minimized = this.$wire.isMinimized ?? false;

            const saved = localStorage.getItem('fi-operations-case-chat-position');
            const margin = 12;
            const panelWidth = Math.min(window.innerWidth * 0.92, 44 * 16);
            const panelHeight = Math.min(window.innerHeight * 0.86, 45 * 16);

            if (saved) {
                try {
                    const parsed = JSON.parse(saved);
                    this.posX = Number(parsed.x ?? this.posX);
                    this.posY = Number(parsed.y ?? this.posY);
                } catch (_) {}
            } else {
                this.posX = Math.max(margin, (window.innerWidth - panelWidth) / 2);
                this.posY = Math.max(margin, window.innerHeight - panelHeight - margin - 16);
            }

            this.clampToViewport();
            window.addEventListener('resize', () => this.clampToViewport());
            document.addEventListener('mousemove', (event) => this.onDrag(event));
            document.addEventListener('mouseup', () => this.endDrag());

            this.livewireComponentId = this.$wire.__instance?.id ?? null;

            this.$wire.on('operations-case-chat-scroll-bottom', (payload = {}) => {
                const detail = typeof payload === 'object' && payload !== null ? payload : { force: true };
                this.stickTimelineToLatest(detail);
            });

            Livewire.hook('commit', ({ component, succeed }) => {
                if (this.livewireComponentId === null || component.id !== this.livewireComponentId) {
                    return;
                }

                const container = this.$refs.messages;
                const preserveScrollTop = container && ! this.pinTimelineToBottom && ! this.pendingScrollForce
                    ? container.scrollTop
                    : null;

                succeed(() => {
                    this.minimized = this.$wire.isMinimized ?? this.minimized;
                    this.bindMessagesObserver();
                    this.bindMessagesScrollListener();

                    if (this.pendingScroll) {
                        const force = this.pendingScrollForce;
                        this.pendingScroll = false;
                        this.pendingScrollForce = false;
                        this.scheduleScrollAfterLayout({ force });
                    } else {
                        this.$nextTick(() => {
                            this.resizeComposerInput();
                            this.restoreMessagesScrollTop(preserveScrollTop);
                        });
                    }
                });
            });

            this.$watch('$wire.selectedCaseId', (caseId, previousCaseId) => {
                if (caseId === null || caseId === previousCaseId) {
                    return;
                }

                this.messagesScrollListenerBound = false;
                this.pinTimelineToBottom = true;
                this.stickTimelineToLatest({ force: true });
            });

            this.$watch('$wire.isOpen', (isOpen) => {
                if (! isOpen || this.$wire.selectedCaseId === null) {
                    return;
                }

                this.$nextTick(() => {
                    this.clampToViewport();
                    this.stickTimelineToLatest({ force: true });
                });
            });

            this.$watch('$wire.messageBody', () => {
                this.$nextTick(() => this.resizeComposerInput());
            });

            this.bindComposerResizeObserver();
        },

        async submitMessage() {
            const body = (this.$wire.messageBody ?? '').trim();

            if (body === '') {
                return;
            }

            this.pinTimelineToBottom = true;

            try {
                await this.$wire.sendMessage();
            } finally {
                this.afterOutboundMessage();
            }
        },

        afterOutboundMessage() {
            this.resizeComposerInput();
            this.stickTimelineToLatest({ force: true });

            [0, 60, 150, 300].forEach((delay) => {
                window.setTimeout(() => {
                    this.resizeComposerInput();
                    this.scrollMessagesToBottom({ force: true, attempt: 0 });
                }, delay);
            });
        },

        bindComposerResizeObserver() {
            const composer = this.$refs.composer;

            if (! composer || typeof ResizeObserver === 'undefined') {
                return;
            }

            if (this.composerResizeObserver) {
                this.composerResizeObserver.disconnect();
            }

            this.composerResizeObserver = new ResizeObserver(() => {
                if (! this.pinTimelineToBottom) {
                    return;
                }

                this.scrollMessagesToBottom({ force: true, attempt: 0 });
            });

            this.composerResizeObserver.observe(composer);
        },

        stickTimelineToLatest(detail = {}) {
            const force = detail.force ?? true;

            if (force) {
                this.pinTimelineToBottom = true;
            }

            if (! force && ! this.pinTimelineToBottom) {
                return;
            }

            this.queueScrollToBottom({ force });
            this.scheduleScrollAfterLayout({ force });
        },

        scheduleScrollAfterLayout({ force = true } = {}) {
            window.clearTimeout(this.scrollLayoutTimer);

            this.scrollLayoutTimer = window.setTimeout(() => {
                this.$nextTick(() => {
                    requestAnimationFrame(() => {
                        this.resizeComposerInput();

                        requestAnimationFrame(() => {
                            this.scrollMessagesToBottom({ force, attempt: 0 });
                        });
                    });
                });
            }, 16);
        },

        resizeComposerInput() {
            const input = this.$refs.composerInput;

            if (! input) {
                return;
            }

            input.style.height = 'auto';

            const maxHeight = Number.parseFloat(window.getComputedStyle(input).maxHeight);
            const scrollHeight = input.scrollHeight;

            if (Number.isFinite(maxHeight) && scrollHeight > maxHeight) {
                input.style.height = `${maxHeight}px`;
                input.style.overflowY = 'auto';
            } else {
                input.style.height = `${scrollHeight}px`;
                input.style.overflowY = 'hidden';
            }
        },

        onMessagesScroll() {
            const container = this.$refs.messages;

            if (! container || this.minimized) {
                return;
            }

            const distanceFromBottom = container.scrollHeight - container.scrollTop - container.clientHeight;
            this.pinTimelineToBottom = distanceFromBottom <= 80;
        },

        bindMessagesScrollListener() {
            const container = this.$refs.messages;

            if (! container || this.messagesScrollListenerBound) {
                return;
            }

            this.messagesScrollListenerBound = true;
            this.onMessagesScroll();
        },

        restoreMessagesScrollTop(scrollTop) {
            if (scrollTop === null) {
                return;
            }

            this.$nextTick(() => {
                requestAnimationFrame(() => {
                    const container = this.$refs.messages;

                    if (! container || this.pinTimelineToBottom) {
                        return;
                    }

                    container.scrollTop = scrollTop;
                });
            });
        },

        bindMessagesObserver() {
            if (this.messagesObserver) {
                this.messagesObserver.disconnect();
                this.messagesObserver = null;
            }
        },

        toggleMinimize() {
            const willRestore = this.minimized;
            this.restoring = willRestore;
            this.minimized = ! this.minimized;

            window.clearTimeout(this.minimizeSyncTimer);
            this.minimizeSyncTimer = window.setTimeout(() => {
                this.$wire.set('isMinimized', this.minimized);
            }, 220);

            this.$nextTick(() => {
                this.clampToViewport();

                window.setTimeout(() => {
                    this.restoring = false;

                    if (! this.minimized) {
                        this.stickTimelineToLatest({ force: true });
                    }
                }, 240);
            });
        },

        startDrag(event) {
            if (event.target.closest('button, a, input, textarea, [contenteditable]')) {
                return;
            }

            this.dragging = true;
            this.dragOffsetX = event.clientX - this.posX;
            this.dragOffsetY = event.clientY - this.posY;
        },

        onDrag(event) {
            if (! this.dragging) {
                return;
            }

            this.posX = event.clientX - this.dragOffsetX;
            this.posY = event.clientY - this.dragOffsetY;
            this.clampToViewport();
        },

        endDrag() {
            if (! this.dragging) {
                return;
            }

            this.dragging = false;
            localStorage.setItem('fi-operations-case-chat-position', JSON.stringify({
                x: this.posX,
                y: this.posY,
            }));
        },

        clampToViewport() {
            const margin = 12;
            const panel = this.$el.querySelector('.fi-operations-case-chat-window');
            const width = panel?.offsetWidth ?? 720;
            const height = panel?.offsetHeight ?? 520;

            this.posX = Math.min(Math.max(margin, this.posX), window.innerWidth - width - margin);
            this.posY = Math.min(Math.max(margin, this.posY), window.innerHeight - height - margin);
        },

        queueScrollToBottom(detail = {}) {
            this.pendingScroll = true;
            this.pendingScrollForce = detail.force ?? false;
        },

        scrollMessagesToBottom({ force = false, attempt = 0 } = {}) {
            const maxAttempts = 20;

            const run = () => {
                const container = this.$refs.messages;

                if (! container) {
                    return;
                }

                if (! force && ! this.pinTimelineToBottom) {
                    return;
                }

                const previousScrollHeight = container.scrollHeight;
                const maxScroll = Math.max(0, container.scrollHeight - container.clientHeight);

                container.scrollTo({
                    top: maxScroll,
                    left: 0,
                    behavior: force ? 'auto' : 'smooth',
                });

                container.scrollTop = maxScroll;

                const needsAnotherPass = attempt < maxAttempts && (
                    container.scrollHeight > previousScrollHeight + 1
                    || Math.abs(container.scrollTop - maxScroll) > 2
                );

                if (needsAnotherPass) {
                    requestAnimationFrame(() => {
                        this.scrollMessagesToBottom({ force, attempt: attempt + 1 });
                    });
                }
            };

            this.$nextTick(() => {
                requestAnimationFrame(() => requestAnimationFrame(run));
            });
        },
    }));
</script>
@endscript
