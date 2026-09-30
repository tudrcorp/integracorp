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
>
    {{-- Ciclo ligero: 3 s con el chat abierto, 5 s cerrado (solo contadores y avisos). El servidor no reenvía HTML si nada cambió. --}}
    @if ($isOpen)
        <div wire:poll.3s="pollHeartbeat" wire:key="ops-case-chat-poll-open" hidden></div>
    @else
        <div wire:poll.5s="pollHeartbeat" wire:key="ops-case-chat-poll-closed" hidden></div>
    @endif

    @if ($isOpen)
        <div
            class="fi-operations-case-chat-window fi-operations-case-chat--ios fi-operations-case-chat--glass"
            :class="{
                'is-minimized': minimized,
                'is-restoring': restoring,
                'is-dragging': dragging,
                'has-unread-alert': ($wire.totalUnread ?? 0) > 0,
                'has-incoming-pulse': incomingPulse,
            }"
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
                        <div class="fi-operations-case-chat-header-title-row">
                            <h2 id="ops-case-chat-title" class="fi-operations-case-chat-title">
                                Chat de seguimiento
                            </h2>
                            @if ($totalUnread > 0)
                                <span
                                    class="fi-operations-case-chat-header-unread-badge"
                                    wire:key="ops-case-chat-unread-badge-{{ $totalUnread }}"
                                    aria-label="{{ $totalUnread }} mensaje{{ $totalUnread === 1 ? '' : 's' }} sin leer"
                                >
                                    {{ $totalUnread > 9 ? '9+' : $totalUnread }}
                                </span>
                            @endif
                        </div>
                        <p class="fi-operations-case-chat-subtitle">
                            <span x-show="! minimized">En seguimiento</span>
                            <span x-show="minimized" x-cloak>Minimizado · doble clic para expandir</span>
                            @if ($activeCasesCount > 0)
                                · {{ $activeCasesCount }} activo{{ $activeCasesCount === 1 ? '' : 's' }}
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
                            <span class="fi-operations-case-chat-sidebar-count">{{ $activeCasesCount }}</span>
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

                        <div class="fi-operations-case-chat-filter" role="group" aria-label="Filtrar casos">
                            <button
                                type="button"
                                wire:click="$set('onlyUnread', false)"
                                @class(['fi-operations-case-chat-filter-chip', 'is-active' => ! $onlyUnread])
                                aria-pressed="{{ $onlyUnread ? 'false' : 'true' }}"
                            >
                                Todos
                            </button>
                            <button
                                type="button"
                                wire:click="$set('onlyUnread', true)"
                                @class(['fi-operations-case-chat-filter-chip', 'is-active' => $onlyUnread, 'has-unread' => $unreadByCase !== []])
                                aria-pressed="{{ $onlyUnread ? 'true' : 'false' }}"
                            >
                                Solo no leídos
                                @if ($unreadByCase !== [])
                                    <span class="fi-operations-case-chat-filter-count">{{ count($unreadByCase) > 99 ? '99+' : count($unreadByCase) }}</span>
                                @endif
                            </button>
                        </div>

                        @php
                            /** Una sola secuencia (encabezados + casos) para no duplicar el marcado de cada caso. */
                            $listRows = [];

                            if ($unreadCases->isNotEmpty()) {
                                $listRows[] = ['type' => 'header', 'key' => 'unread', 'label' => 'Sin leer', 'count' => $unreadCases->count()];

                                foreach ($unreadCases as $unreadCase) {
                                    $listRows[] = ['type' => 'case', 'case' => $unreadCase];
                                }
                            }

                            if (! $onlyUnread && $cases->isNotEmpty()) {
                                if ($unreadCases->isNotEmpty()) {
                                    $listRows[] = ['type' => 'header', 'key' => 'all', 'label' => 'Todos los casos', 'count' => null];
                                }

                                foreach ($cases as $listedCase) {
                                    $listRows[] = ['type' => 'case', 'case' => $listedCase];
                                }
                            }
                        @endphp

                        <div
                            class="fi-operations-case-chat-case-list"
                            role="listbox"
                            aria-label="Casos en seguimiento"
                            wire:loading.class="opacity-60"
                            wire:target="caseSearch, loadMoreCases, onlyUnread"
                        >
                            @forelse ($listRows as $row)
                                @if ($row['type'] === 'header')
                                    <p class="fi-operations-case-chat-section-label {{ $row['key'] === 'unread' ? 'is-unread' : '' }}" wire:key="ops-case-chat-section-{{ $row['key'] }}">
                                        {{ $row['label'] }}
                                        @if ($row['count'] !== null)
                                            <span>· {{ $row['count'] }}</span>
                                        @endif
                                    </p>
                                    @continue
                                @endif

                                @php
                                    $case = $row['case'];
                                    $unread = $unreadByCase[$case->id] ?? 0;
                                    $isSelected = $selectedCaseId === $case->id;
                                    $patientName = $case->patient_name ?? $case->telemedicinePatient?->full_name ?? 'Paciente';
                                    $preview = $unread > 0 ? ($previews[$case->id] ?? null) : null;
                                @endphp
                                <button
                                    type="button"
                                    wire:click="selectCase({{ $case->id }})"
                                    role="option"
                                    aria-selected="{{ $isSelected ? 'true' : 'false' }}"
                                    aria-label="{{ $case->code }} · {{ $patientName }}{{ $unread > 0 ? ' · '.$unread.' sin leer' : '' }}"
                                    class="fi-operations-case-chat-case-item {{ $isSelected ? 'is-selected' : '' }} {{ $unread > 0 ? 'has-unread' : '' }}"
                                    wire:key="ops-case-chat-item-{{ $case->id }}"
                                >
                                    <span class="fi-operations-case-chat-case-avatar" aria-hidden="true">
                                        {{ $initialsFromName($patientName) }}
                                        @if ($unread > 0)
                                            <span class="fi-operations-case-chat-case-unread-dot"></span>
                                        @endif
                                    </span>

                                    <span class="fi-operations-case-chat-case-content">
                                        <span class="fi-operations-case-chat-case-item-top">
                                            <span class="fi-operations-case-chat-case-code">{{ $case->code }}</span>
                                            @if ($preview !== null && $preview['created_at_human'] !== '')
                                                <span class="fi-operations-case-chat-case-time">{{ $preview['created_at_human'] }}</span>
                                            @endif
                                        </span>
                                        <span class="fi-operations-case-chat-case-patient">
                                            {{ Str::limit($patientName, 34) }}
                                        </span>
                                        @if ($preview !== null)
                                            <span class="fi-operations-case-chat-case-preview">
                                                @if (filled($preview['user_name']))
                                                    <strong>{{ Str::limit($preview['user_name'], 18) }}:</strong>
                                                @endif
                                                {{ Str::limit(trim($preview['body']), 60) }}
                                            </span>
                                        @endif
                                    </span>

                                    @if ($unread > 0)
                                        <span class="fi-operations-case-chat-case-unread">{{ $unread > 9 ? '9+' : $unread }}</span>
                                    @endif
                                </button>
                            @empty
                                <div class="fi-operations-case-chat-empty-sidebar">
                                    <span class="fi-operations-case-chat-empty-icon" aria-hidden="true">
                                        <x-filament::icon :icon="$onlyUnread && trim($caseSearch) === '' ? 'heroicon-o-check-circle' : 'heroicon-o-inbox'" class="size-7" />
                                    </span>
                                    @if (trim($caseSearch) !== '')
                                        <p class="fi-operations-case-chat-empty-title">Sin resultados</p>
                                        <p class="fi-operations-case-chat-empty-text">
                                            Ningún caso {{ $onlyUnread ? 'sin leer ' : '' }}coincide con «{{ Str::limit(trim($caseSearch), 40) }}». Busque por código, paciente o doctor.
                                        </p>
                                    @elseif ($onlyUnread)
                                        <p class="fi-operations-case-chat-empty-title">Todo al día</p>
                                        <p class="fi-operations-case-chat-empty-text">
                                            No tiene conversaciones sin leer. Pulse «Todos» para ver el resto de los casos.
                                        </p>
                                    @else
                                        <p class="fi-operations-case-chat-empty-title">Sin casos en seguimiento</p>
                                        <p class="fi-operations-case-chat-empty-text">
                                            Aparecerán aquí cuando un caso pase a EN SEGUIMIENTO.
                                        </p>
                                    @endif
                                </div>
                            @endforelse

                            @if ($hasMoreCases)
                                <button
                                    type="button"
                                    wire:click="loadMoreCases"
                                    wire:loading.attr="disabled"
                                    wire:target="loadMoreCases"
                                    class="fi-operations-case-chat-case-item justify-center text-sm font-medium opacity-80"
                                    wire:key="ops-case-chat-load-more"
                                >
                                    <span wire:loading.remove wire:target="loadMoreCases">Cargar más casos</span>
                                    <span wire:loading wire:target="loadMoreCases">Cargando…</span>
                                </button>
                            @endif
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
                                    wire:target="sendMessage, selectCase"
                                >
                                <div class="fi-operations-case-chat-messages-inner">
                                @if ($hasOlderMessages)
                                    <div class="flex justify-center py-2" wire:key="ops-case-chat-older-{{ $selectedCaseId }}">
                                        <button
                                            type="button"
                                            wire:click="loadOlderMessages"
                                            wire:loading.attr="disabled"
                                            wire:target="loadOlderMessages"
                                            class="rounded-full px-3 py-1 text-xs font-medium text-primary-600 ring-1 ring-primary-600/30 hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-white/5"
                                        >
                                            <span wire:loading.remove wire:target="loadOlderMessages">Ver mensajes anteriores</span>
                                            <span wire:loading wire:target="loadOlderMessages">Cargando…</span>
                                        </button>
                                    </div>
                                @endif
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

                                    @if ($unreadDividerMessageId !== null && (int) $message->id === $unreadDividerMessageId && ! $isMine)
                                        <div class="fi-operations-case-chat-new-divider" wire:key="ops-case-new-divider-{{ $message->id }}" role="separator">
                                            <span>Mensajes nuevos</span>
                                        </div>
                                    @endif

                                    @if ($message->isConsultationSummary())
                                        @php
                                            $summaryMeta = is_array($message->meta) ? $message->meta : [];
                                            $summarySections = array_values(array_filter(
                                                (array) ($summaryMeta['sections'] ?? []),
                                                static fn (mixed $section): bool => is_array($section) && filled($section['label'] ?? null),
                                            ));
                                            $visibleSections = array_slice($summarySections, 0, 2);
                                            $hiddenSections = array_slice($summarySections, 2);
                                        @endphp
                                        <article
                                            wire:key="ops-case-msg-{{ $message->id }}"
                                            class="fi-operations-case-chat-summary"
                                            x-data="{ expanded: false }"
                                            aria-label="{{ $summaryMeta['title'] ?? 'Resumen de consulta' }}"
                                        >
                                            <header class="fi-operations-case-chat-summary-head">
                                                <span class="fi-operations-case-chat-summary-badge">
                                                    <x-filament::icon icon="heroicon-m-sparkles" class="size-3.5" />
                                                    {{ \App\Support\Telemedicine\ConsultationChatSummary::AUTHOR_LABEL }}
                                                </span>
                                                <p class="fi-operations-case-chat-summary-title">{{ $summaryMeta['title'] ?? 'Resumen de consulta' }}</p>
                                                @if (filled($summaryMeta['subtitle'] ?? null))
                                                    <p class="fi-operations-case-chat-summary-subtitle">{{ $summaryMeta['subtitle'] }}</p>
                                                @endif
                                            </header>

                                            @if ($summarySections === [])
                                                <p class="fi-operations-case-chat-summary-empty">La consulta se registró sin datos clínicos adicionales.</p>
                                            @else
                                                <dl class="fi-operations-case-chat-summary-body">
                                                    @foreach ($visibleSections as $section)
                                                        @include('livewire.operations.partials.case-chat-summary-section', ['section' => $section])
                                                    @endforeach

                                                    @if ($hiddenSections !== [])
                                                        <div x-show="expanded" x-cloak x-collapse>
                                                            @foreach ($hiddenSections as $section)
                                                                @include('livewire.operations.partials.case-chat-summary-section', ['section' => $section])
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </dl>

                                                @if ($hiddenSections !== [])
                                                    <button
                                                        type="button"
                                                        class="fi-operations-case-chat-summary-toggle"
                                                        x-on:click="expanded = ! expanded"
                                                        x-bind:aria-expanded="expanded ? 'true' : 'false'"
                                                    >
                                                        <span x-show="! expanded">Ver resumen completo ({{ count($hiddenSections) }} apartado{{ count($hiddenSections) === 1 ? '' : 's' }} más)</span>
                                                        <span x-show="expanded" x-cloak>Ocultar detalle</span>
                                                    </button>
                                                @endif
                                            @endif
                                        </article>
                                        @continue
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
                                        wire:model="messageBody"
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
        incomingPulse: false,
        incomingPulseTimer: null,
        audioContext: null,
        baseDocumentTitle: document.title,
        unreadTitlePrefix: '',

        isOperationsModule() {
            return window.location.pathname.startsWith('/operations');
        },

        isOperationsAuthPage() {
            const path = window.location.pathname.replace(/\/$/, '');

            return /^\/operations\/(login|password-reset|register)(\/|$)/.test(path);
        },

        isAuthenticatedOperationsArea() {
            return this.isOperationsModule() && ! this.isOperationsAuthPage();
        },

        handleOperationsModuleNavigation() {
            if (! this.isAuthenticatedOperationsArea() && this.$wire.isOpen) {
                this.$wire.closePanel();
            }
        },

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

            this.$wire.on('operations-case-chat-incoming-message', (payload = {}) => {
                const detail = Array.isArray(payload) ? (payload[0] ?? {}) : (payload ?? {});
                this.handleIncomingMessage(detail);
            });

            this.$wire.on('operations-case-chat-unread-updated', (payload = {}) => {
                const detail = Array.isArray(payload) ? (payload[0] ?? {}) : (payload ?? {});
                const totalUnread = Number(detail.totalUnread ?? 0);
                this.syncUnreadDocumentTitle(totalUnread);
                window.dispatchEvent(new CustomEvent('operations-case-chat-unread-updated', {
                    detail: { totalUnread },
                }));
            });

            document.addEventListener('click', () => this.ensureAudioContext(), { once: true });
            document.addEventListener('keydown', () => this.ensureAudioContext(), { once: true });

            document.addEventListener('livewire:navigated', () => this.handleOperationsModuleNavigation());

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

        handleIncomingMessage(detail = {}) {
            this.playIncomingSound();
            this.triggerIncomingPulse();
            this.showIncomingToast(detail);
        },

        showIncomingToast(detail = {}) {
            if (typeof FilamentNotification === 'undefined') {
                return;
            }

            const caseCode = detail.caseCode ?? 'Caso';
            const authorName = detail.authorName ?? 'Analista';
            const bodyPreview = detail.bodyPreview ?? '';
            const newCount = Number(detail.newCount ?? 1);
            const suffix = newCount > 1 ? ` (+${newCount - 1} más)` : '';
            const isSummary = detail.isSummary === true;
            const caseId = Number(detail.caseId ?? 0);

            const notification = new FilamentNotification()
                .title(isSummary ? `Nuevo resumen de consulta · ${caseCode}` : `Nuevo mensaje · ${caseCode}`)
                .body(`${authorName}: ${bodyPreview}${suffix}`)
                .icon('heroicon-o-chat-bubble-left-right')
                .info()
                .duration(isSummary ? 9000 : 6500);

            if (caseId > 0 && typeof FilamentNotificationAction !== 'undefined') {
                notification.actions([
                    new FilamentNotificationAction('openCaseChat')
                        .label('Abrir chat')
                        .button()
                        .dispatch('operations-case-chat-open', { caseId })
                        .close(),
                ]);
            }

            notification.send();
        },

        triggerIncomingPulse() {
            this.incomingPulse = true;
            window.clearTimeout(this.incomingPulseTimer);
            this.incomingPulseTimer = window.setTimeout(() => {
                this.incomingPulse = false;
            }, 1400);
        },

        ensureAudioContext() {
            if (this.audioContext || typeof window.AudioContext === 'undefined') {
                return;
            }

            try {
                this.audioContext = new window.AudioContext();
            } catch (_) {}
        },

        playIncomingSound() {
            this.ensureAudioContext();

            try {
                const AudioCtx = window.AudioContext || window.webkitAudioContext;

                if (! AudioCtx) {
                    return;
                }

                const ctx = this.audioContext ?? new AudioCtx();
                this.audioContext = ctx;

                if (ctx.state === 'suspended') {
                    ctx.resume();
                }

                const now = ctx.currentTime;
                const playTone = (frequency, startAt, duration, volume = 0.1) => {
                    const oscillator = ctx.createOscillator();
                    const gain = ctx.createGain();

                    oscillator.type = 'sine';
                    oscillator.frequency.setValueAtTime(frequency, startAt);
                    gain.gain.setValueAtTime(0.0001, startAt);
                    gain.gain.exponentialRampToValueAtTime(volume, startAt + 0.015);
                    gain.gain.exponentialRampToValueAtTime(0.0001, startAt + duration);
                    oscillator.connect(gain);
                    gain.connect(ctx.destination);
                    oscillator.start(startAt);
                    oscillator.stop(startAt + duration + 0.02);
                };

                playTone(880, now, 0.12, 0.11);
                playTone(1174.66, now + 0.13, 0.16, 0.09);
            } catch (_) {}
        },

        syncUnreadDocumentTitle(totalUnread = 0) {
            const prefix = totalUnread > 0 ? `(${totalUnread > 9 ? '9+' : totalUnread}) ` : '';

            if (this.unreadTitlePrefix === prefix) {
                return;
            }

            this.unreadTitlePrefix = prefix;
            document.title = prefix === '' ? this.baseDocumentTitle : `${prefix}${this.baseDocumentTitle}`;
        },
    }));
</script>
@endscript
