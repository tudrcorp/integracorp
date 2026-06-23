<?php

declare(strict_types=1);

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\PublicAiAgent\AgentOrchestrator;
use App\Services\PublicAiAgent\ChatAgentRegistrationService;
use App\Services\PublicAiAgent\IntentSlotFiller;
use Flux\Flux;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.interactive')] class extends Component {
    public string $sessionToken = '';

    public string $draft = '';

    /** @var array<int, array{id: string, role: string, content: string, animate?: bool}> */
    public array $chatFeed = [];

    public string $conversationState = 'saludo';

    public ?string $detectedIntent = null;

    public bool $handoffRequested = false;

    public bool $isThinking = false;

    public string $selectedAction = '';

    /** @var array<string, array{label: string, description: string, short: string}> */
    public array $actionOptions = [
        // Acciones de cotización deshabilitadas temporalmente (reactivar cuando se requiera).
        // 'cotizacion_individual' => [
        //     'label' => 'Cotización plan individual',
        //     'short' => 'Individual',
        //     'description' => 'Persona o familia por edades.',
        // ],
        // 'cotizacion_corporativa' => [
        //     'label' => 'Cotización plan corporativo',
        //     'short' => 'Corporativo',
        //     'description' => 'Equipos y empresas por grupos.',
        // ],
        'registro_agencia_master' => [
            'label' => 'Registro Agencia Master',
            'short' => 'Agencia Master',
            'description' => 'Alta guiada de agencia master.',
        ],
        'registro_agencia_general' => [
            'label' => 'Registro Agencia General',
            'short' => 'Agencia General',
            'description' => 'Alta guiada de agencia general.',
        ],
        'registro_agente' => [
            'label' => 'Registro de Agente',
            'short' => 'Agente',
            'description' => 'Preregistro de agente.',
        ],
        'registro_subagente' => [
            'label' => 'Registro de Subagente',
            'short' => 'Subagente',
            'description' => 'Preregistro de subagente.',
        ],
    ];

    public function mount(): void
    {
        if (app()->bound('debugbar')) {
            app('debugbar')->disable();
        }

        $cookieToken = (string) request()->cookie(ChatSession::PUBLIC_CHAT_COOKIE, '');
        $session = ChatSession::findActiveByPublicToken($cookieToken);

        if ($session === null) {
            $session = ChatSession::startPublic(
                ipAddress: request()->ip(),
                userAgent: request()->userAgent(),
            );

            cookie()->queue($this->makePublicChatCookie((string) $session->public_token));
        }

        $this->hydrateFromSession($session);
    }

    private function makePublicChatCookie(string $token): \Symfony\Component\HttpFoundation\Cookie
    {
        return cookie(
            name: ChatSession::PUBLIC_CHAT_COOKIE,
            value: $token,
            minutes: 60 * 24 * 7,
            path: '/',
            secure: request()->isSecure(),
            httpOnly: true,
            sameSite: 'lax',
        );
    }

    public function restartChat(): void
    {
        if ($this->isThinking) {
            return;
        }

        $session = ChatSession::query()
            ->where('public_token', $this->sessionToken)
            ->first();

        if ($session !== null) {
            $session->update(['status' => 'closed']);
        }

        $newSession = ChatSession::startPublic(
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
        );

        cookie()->queue($this->makePublicChatCookie((string) $newSession->public_token));

        $this->sessionToken = (string) $newSession->public_token;
        $this->chatFeed = [];
        $this->draft = '';
        $this->conversationState = 'saludo';
        $this->detectedIntent = null;
        $this->handoffRequested = false;
        $this->selectedAction = '';

        Flux::toast(
            heading: 'Chat reiniciado',
            text: 'Puedes comenzar una nueva conversación.',
            variant: 'success',
        );
    }

    private function hydrateFromSession(ChatSession $session): void
    {
        $this->sessionToken = (string) $session->public_token;
        $this->conversationState = (string) $session->current_state;
        $this->detectedIntent = $session->detected_intent;
        $this->handoffRequested = (bool) $session->handoff_requested;

        $metadata = is_array($session->metadata) ? $session->metadata : [];
        $selectedAction = (string) ($metadata['selected_action'] ?? '');
        $this->selectedAction = isset($this->actionOptions[$selectedAction]) ? $selectedAction : '';

        $this->chatFeed = $session->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('id')
            ->get(['id', 'role', 'content'])
            ->map(fn (ChatMessage $message): array => [
                'id' => 'hist-'.$message->id,
                'role' => (string) $message->role,
                'content' => (string) ($message->content ?? ''),
                'animate' => false,
            ])
            ->all();
    }

    public function selectAction(string $actionKey): void
    {
        if (! isset($this->actionOptions[$actionKey])) {
            return;
        }

        if ($this->handoffRequested || $this->isThinking) {
            return;
        }

        $this->selectedAction = $actionKey;
        $this->draft = '';
        $this->sendMessage();
    }

    public function sendMessage(): void
    {
        try {
            $validated = $this->validate([
                'draft' => ['nullable', 'string', 'max:3000'],
            ]);
        } catch (ValidationException $exception) {
            $this->pushAssistantReply(
                collect($exception->errors())->flatten()->filter()->implode(' ') ?: 'Revisa el texto e intenta de nuevo.'
            );

            return;
        }

        $message = trim((string) ($validated['draft'] ?? ''));

        if ($message !== '' && app(IntentSlotFiller::class)->isHelpRequest($message)) {
            $this->chatFeed[] = [
                'id' => 'user-'.uniqid(),
                'role' => 'user',
                'content' => $message,
            ];
            $this->draft = '';
            $this->pushAssistantReply($this->guideHelpMessage());

            return;
        }

        if ($this->selectedAction === '' || ! isset($this->actionOptions[$this->selectedAction])) {
            Flux::toast(
                heading: 'Selecciona una acción',
                text: 'Elige una opción en «¿Qué quieres hacer?» antes de enviar.',
                variant: 'warning'
            );

            return;
        }

        if ($message === '') {
            $message = 'Seleccioné: '.$this->actionOptions[$this->selectedAction]['label'];
        }

        $this->chatFeed[] = [
            'id' => 'user-'.uniqid(),
            'role' => 'user',
            'content' => $message,
        ];

        $this->draft = '';
        $this->isThinking = true;
        $this->dispatch('chat-scroll-bottom');

        $session = ChatSession::query()
            ->where('public_token', $this->sessionToken)
            ->first();

        if ($session === null) {
            $session = ChatSession::startPublic(
                ipAddress: request()->ip(),
                userAgent: request()->userAgent(),
            );
            $this->sessionToken = (string) $session->public_token;
        }

        try {
            $result = app(AgentOrchestrator::class)->processUserMessage($session, $message, $this->selectedAction);
        } catch (ValidationException $exception) {
            $this->isThinking = false;
            $this->pushAssistantReply(
                collect($exception->errors())->flatten()->filter()->implode(' ') ?: 'Completa los datos solicitados.'
            );

            return;
        } catch (\Throwable $exception) {
            $this->isThinking = false;
            $this->pushAssistantReply($this->chatErrorMessageFromException($exception));
            report($exception);

            return;
        }

        $this->conversationState = (string) $result['state'];
        $this->detectedIntent = $result['intent'];
        $this->handoffRequested = (bool) $result['handoff_requested'];

        if (isset($result['new_session_token']) && is_string($result['new_session_token']) && $result['new_session_token'] !== '') {
            cookie()->queue($this->makePublicChatCookie($result['new_session_token']));
            $this->sessionToken = $result['new_session_token'];
            $this->chatFeed = [];
            $this->selectedAction = '';
            $this->detectedIntent = null;
            $this->handoffRequested = false;
        }

        $this->pushAssistantReply((string) $result['reply']);
        $this->isThinking = false;

        if (($result['open_action_menu'] ?? false) === true) {
            $this->dispatch('chat-open-action-menu');
        }

        if (isset($result['external_redirect_url']) && is_string($result['external_redirect_url']) && $result['external_redirect_url'] !== '') {
            $this->dispatch('chat-open-external', url: $result['external_redirect_url']);
        }
    }

    public function formatChatMessage(string $content): string
    {
        $chunks = preg_split(
            '/(\[[^\]]+\]\(https?:\/\/[^)]+\))/i',
            $content,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY,
        );

        if ($chunks === false) {
            $chunks = [$content];
        }

        $html = '';

        foreach ($chunks as $chunk) {
            if (preg_match('/^\[([^\]]+)\]\((https?:\/\/[^)]+)\)$/i', $chunk, $match) === 1) {
                $html .= sprintf(
                    '<a href="%s" target="_blank" rel="noopener noreferrer" class="underline text-emerald-200 hover:text-white">%s</a>',
                    e($match[2]),
                    e($match[1]),
                );

                continue;
            }

            $escaped = e($chunk);
            $html .= preg_replace(
                '/(https?:\/\/[^\s<]+)/i',
                '<a href="$1" target="_blank" rel="noopener noreferrer" class="underline text-emerald-200 hover:text-white break-all">$1</a>',
                $escaped,
            ) ?? $escaped;
        }

        return $html;
    }

    private function pushAssistantReply(string $content): void
    {
        $this->chatFeed[] = [
            'id' => 'assistant-'.uniqid(),
            'role' => 'assistant',
            'content' => $content,
            'animate' => true,
        ];
        $this->dispatch('chat-scroll-bottom');
    }

    private function chatErrorMessageFromException(\Throwable $exception): string
    {
        if ($exception instanceof ValidationException) {
            return collect($exception->errors())->flatten()->filter()->implode(' ') ?: 'Completa los datos solicitados.';
        }

        return 'No pudimos procesar tu solicitud en este momento. Revisa los datos enviados e intenta de nuevo. Si el problema continúa, contacta a un asesor.';
    }

    public function guideWelcomeMessage(): string
    {
        return app(IntentSlotFiller::class)->publicChatGuideWelcomeMessage();
    }

    public function guideHelpMessage(): string
    {
        $registrationService = app(ChatAgentRegistrationService::class);

        return app(IntentSlotFiller::class)->publicChatHelpMessage(
            $registrationService->whatsappBusinessUrl(),
            $registrationService->whatsappBusinessDisplayLabel(),
        );
    }
}; ?>

<div class="relative flex h-dvh w-full flex-col overflow-hidden">
    {{-- Fondo gradiente tipo IA --}}
    <div class="pointer-events-none fixed inset-0 bg-gradient-to-br from-[#0b1f4a] via-[#0d4f6e] to-[#14b8a6]"></div>
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(56,189,248,0.25),transparent_45%),radial-gradient(circle_at_80%_80%,rgba(16,185,129,0.2),transparent_40%)]"></div>

    <div class="relative mx-auto flex h-full w-full max-w-3xl flex-col overflow-hidden px-4 sm:px-6"
        x-on:chat-open-external.window="if ($event.detail?.url) { window.open($event.detail.url, '_blank', 'noopener,noreferrer'); }"
    >
        {{-- Header --}}
        <header class="shrink-0 pt-4 pb-3 text-center sm:pt-6 sm:pb-4">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center overflow-hidden rounded-2xl border border-white/25 bg-white shadow-lg sm:h-20 sm:w-20">
                <img
                    src="{{ asset('images/chat/assistant-avatar.png') }}"
                    alt="Integracorp"
                    class="h-full w-full object-cover"
                />
            </div>
            <h1 class="text-2xl font-semibold tracking-tight text-white sm:text-3xl">
                ¡Hola! Soy tu
                <span class="inline-flex items-baseline whitespace-nowrap">
                    GU<span class="bg-gradient-to-r from-emerald-300 via-cyan-200 to-teal-300 bg-clip-text font-extrabold text-transparent drop-shadow-sm">IA</span>-CHAT
                </span>
            </h1>
            <p class="mt-2 text-sm text-white/75 sm:text-base">Estoy aquí para guiarte en lo que necesites.</p>
        </header>

        {{-- Zona de chat (solo esta área hace scroll) --}}
        <div
            class="min-h-0 flex-1 overflow-y-auto overscroll-contain"
            x-data
            x-init="$el.scrollTop = $el.scrollHeight"
            x-on:chat-scroll-bottom.window="$el.scrollTop = $el.scrollHeight"
        >
            <div class="space-y-4 py-1 sm:py-2">
                @if (count($chatFeed) === 0 && ! $isThinking)
                    <div wire:key="guide-welcome" class="flex items-start gap-2.5 justify-start">
                        <img
                            src="{{ asset('images/chat/assistant-avatar.png') }}"
                            alt="GUÍA-CHAT Integracorp"
                            class="h-9 w-9 shrink-0 rounded-full border border-white/25 bg-white object-cover shadow-md sm:h-10 sm:w-10"
                        />
                        <p
                            wire:ignore
                            class="max-w-[85%] pt-1 text-sm leading-relaxed whitespace-pre-line text-white/95 sm:max-w-[88%] sm:text-base"
                            x-data="chatTypewriter(@js($this->guideWelcomeMessage()), @js($this->formatChatMessage($this->guideWelcomeMessage())))"
                        >
                            <span x-show="!finished">
                                <span x-text="displayed"></span>
                                <span
                                    x-show="isTyping"
                                    class="inline-block h-[1em] w-[2px] translate-y-[1px] animate-pulse rounded-sm bg-white/80 align-middle ml-0.5"
                                    aria-hidden="true"
                                ></span>
                            </span>
                            <span hidden :hidden="!finished" x-html="formattedHtml"></span>
                        </p>
                    </div>
                @endif

                @foreach ($chatFeed as $index => $chatMessage)
                        @if ($chatMessage['role'] === 'user')
                            <div wire:key="msg-{{ $chatMessage['id'] ?? $index }}" class="flex justify-end">
                                <div class="max-w-[88%] rounded-2xl rounded-br-md border border-white/20 bg-white/20 px-4 py-3 text-sm text-white backdrop-blur-md">
                                    {{ $chatMessage['content'] }}
                                </div>
                            </div>
                        @else
                            <div wire:key="msg-{{ $chatMessage['id'] ?? $index }}" class="flex items-start gap-2.5 justify-start">
                                <img
                                    src="{{ asset('images/chat/assistant-avatar.png') }}"
                                    alt="Asistente Integracorp"
                                    class="h-9 w-9 shrink-0 rounded-full border border-white/25 bg-white object-cover shadow-md sm:h-10 sm:w-10"
                                />
                                @if (($chatMessage['animate'] ?? false) === true)
                                    <p
                                        wire:ignore
                                        class="max-w-[85%] pt-1 text-sm leading-relaxed whitespace-pre-line text-white/95 sm:max-w-[88%] sm:text-base"
                                        x-data="chatTypewriter(@js($chatMessage['content']), @js($this->formatChatMessage($chatMessage['content'])))"
                                    >
                                        <span x-show="!finished">
                                            <span x-text="displayed"></span>
                                            <span
                                                x-show="isTyping"
                                                class="inline-block h-[1em] w-[2px] translate-y-[1px] animate-pulse rounded-sm bg-white/80 align-middle ml-0.5"
                                                aria-hidden="true"
                                            ></span>
                                        </span>
                                        <span hidden :hidden="!finished" x-html="formattedHtml"></span>
                                    </p>
                                @else
                                    <p class="max-w-[85%] pt-1 text-sm leading-relaxed whitespace-pre-line text-white/95 sm:max-w-[88%] sm:text-base">
                                        {!! $this->formatChatMessage($chatMessage['content']) !!}
                                    </p>
                                @endif
                            </div>
                        @endif
                    @endforeach

                    @if ($isThinking)
                        <div class="flex items-start gap-2.5 justify-start" wire:key="assistant-typing">
                            <div class="relative shrink-0">
                                <span class="absolute inset-0 animate-ping rounded-full bg-emerald-400/25"></span>
                                <img
                                    src="{{ asset('images/chat/assistant-avatar.png') }}"
                                    alt="Asistente Integracorp"
                                    class="relative h-9 w-9 rounded-full border border-white/25 bg-white object-cover shadow-md sm:h-10 sm:w-10"
                                />
                            </div>
                            <div class="flex items-center gap-1.5 px-1 pt-3" aria-label="El asistente está escribiendo">
                                <span class="h-2 w-2 rounded-full bg-white/90 animate-[chat-typing_1.2s_ease-in-out_infinite]"></span>
                                <span class="h-2 w-2 rounded-full bg-white/70 animate-[chat-typing_1.2s_ease-in-out_0.15s_infinite]"></span>
                                <span class="h-2 w-2 rounded-full bg-white/50 animate-[chat-typing_1.2s_ease-in-out_0.3s_infinite]"></span>
                            </div>
                        </div>
                    @endif
            </div>
        </div>

        {{-- Input fijo al pie del layout (sin overlap) --}}
        <div class="shrink-0 space-y-3 pt-3 pb-[max(1rem,env(safe-area-inset-bottom))]">
        {{-- Barra de escritura horizontal (diseño tipo chat IA) --}}
        <form wire:submit="sendMessage" class="flex w-full items-end gap-2 overflow-visible sm:gap-3">
            <div class="flex min-w-0 flex-1 items-end gap-1 overflow-visible rounded-3xl border border-white/20 bg-black/30 py-1.5 pl-1.5 pr-2 shadow-[0_8px_32px_-12px_rgba(0,0,0,0.45)] backdrop-blur-xl sm:gap-2 sm:pl-2 sm:pr-3 sm:py-2">
                {{-- Reiniciar chat --}}
                <button
                    type="button"
                    wire:click="restartChat"
                    wire:loading.attr="disabled"
                    wire:target="restartChat"
                    @disabled($isThinking)
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-white/70 transition hover:bg-white/15 hover:text-white disabled:opacity-40"
                    aria-label="Reiniciar chat"
                    title="Reiniciar chat"
                >
                    <svg wire:loading.remove wire:target="restartChat" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/>
                    </svg>
                    <svg wire:loading wire:target="restartChat" class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                </button>

                {{-- Campo de texto (crece con el contenido) --}}
                <textarea
                    wire:model="draft"
                    rows="1"
                    placeholder="Pregunta lo que necesites..."
                    @disabled($handoffRequested)
                    x-data="{
                        resize() {
                            this.$el.style.height = 'auto';
                            this.$el.style.height = `${Math.min(this.$el.scrollHeight, 128)}px`;
                        },
                    }"
                    x-init="resize(); $watch('$wire.draft', () => $nextTick(() => resize()))"
                    x-on:input="resize()"
                    x-on:keydown.enter.prevent="if (! $event.shiftKey) { $el.form.requestSubmit() }"
                    class="min-h-[1.5rem] max-h-32 min-w-0 flex-1 resize-none overflow-y-auto border-0 bg-transparent py-1 text-sm leading-relaxed text-white placeholder:text-white/45 outline-none focus:ring-0 disabled:opacity-50 sm:min-h-[1.625rem] sm:text-base"
                ></textarea>

                {{-- Selector de acción (custom glass dropdown) --}}
                <div
                    x-data="{
                        open: false,
                        menuStyle: '',
                        closeIfOutside(event) {
                            if (! this.open) {
                                return;
                            }

                            const target = event.target;
                            if (
                                this.$refs.triggerDesktop?.contains(target)
                                || this.$refs.triggerMobile?.contains(target)
                                || this.$refs.menu?.contains(target)
                            ) {
                                return;
                            }

                            this.open = false;
                        },
                        updateMenuPosition() {
                            const isDesktop = window.matchMedia('(min-width: 640px)').matches;
                            const trigger = isDesktop ? this.$refs.triggerDesktop : this.$refs.triggerMobile;

                            if (! trigger) {
                                return;
                            }

                            const rect = trigger.getBoundingClientRect();
                            const menuWidth = Math.min(288, window.innerWidth - 32);
                            const left = Math.min(
                                Math.max(16, rect.right - menuWidth),
                                window.innerWidth - menuWidth - 16
                            );
                            const spaceAbove = rect.top - 16;
                            const spaceBelow = window.innerHeight - rect.bottom - 16;
                            const openUp = spaceBelow < 320 && spaceAbove > spaceBelow;

                            if (openUp) {
                                this.menuStyle = `position: fixed; left: ${left}px; bottom: ${window.innerHeight - rect.top + 8}px; width: ${menuWidth}px; z-index: 200;`;
                            } else {
                                this.menuStyle = `position: fixed; left: ${left}px; top: ${rect.bottom + 8}px; width: ${menuWidth}px; z-index: 200;`;
                            }
                        },
                        toggle() {
                            if (! this.open) {
                                this.updateMenuPosition();
                            }

                            this.open = ! this.open;
                        },
                    }"
                    x-on:click.window="closeIfOutside($event)"
                    x-on:keydown.escape.window="open = false"
                    x-on:resize.window="if (open) updateMenuPosition()"
                    x-on:scroll.window="if (open) updateMenuPosition()"
                    x-on:chat-open-action-menu.window="open = true; $nextTick(() => updateMenuPosition())"
                    class="relative shrink-0 overflow-visible"
                >
                    <button
                        type="button"
                        x-ref="triggerDesktop"
                        x-on:click="toggle()"
                        @disabled($handoffRequested)
                        class="{{ $selectedAction !== ''
                            ? 'hidden max-w-[10rem] items-center gap-1.5 rounded-full border border-white/35 bg-white/20 py-1.5 pl-3 pr-2.5 text-xs font-medium text-white shadow-[inset_0_1px_0_0_rgba(255,255,255,0.2)] backdrop-blur-lg transition hover:bg-white/25 sm:inline-flex sm:max-w-[11rem] sm:py-2 sm:pl-3.5 sm:pr-3 sm:text-sm'
                            : 'hidden max-w-[10rem] items-center gap-1.5 rounded-full border border-white/30 bg-white/15 py-1.5 pl-3 pr-2.5 text-xs font-medium text-white/85 shadow-[inset_0_1px_0_0_rgba(255,255,255,0.15)] backdrop-blur-lg transition hover:border-white/40 hover:bg-white/20 sm:inline-flex sm:max-w-[11rem] sm:py-2 sm:pl-3.5 sm:pr-3 sm:text-sm' }} disabled:opacity-50"
                        aria-haspopup="listbox"
                        x-bind:aria-expanded="open"
                    >
                        <span class="truncate">
                            @if ($selectedAction !== '' && isset($actionOptions[$selectedAction]))
                                {{ $actionOptions[$selectedAction]['short'] }}
                            @else
                                ¿Qué quieres hacer?
                            @endif
                        </span>
                        <svg
                            class="h-3.5 w-3.5 shrink-0 text-white/70 transition-transform duration-200 sm:h-4 sm:w-4"
                            x-bind:class="open ? 'rotate-180' : ''"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                        >
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                        </svg>
                    </button>

                    {{-- Móvil: acciones desde icono ? --}}
                    <button
                        type="button"
                        x-ref="triggerMobile"
                        x-on:click="toggle()"
                        @disabled($handoffRequested)
                        class="{{ $selectedAction !== ''
                            ? 'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-white ring-1 ring-emerald-400/50 transition hover:bg-white/10 sm:hidden disabled:opacity-50'
                            : 'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-white/60 transition hover:bg-white/10 hover:text-white sm:hidden disabled:opacity-50' }}"
                        aria-haspopup="listbox"
                        x-bind:aria-expanded="open"
                        aria-label="¿Qué quieres hacer?"
                        title="¿Qué quieres hacer?"
                    >
                        <span class="text-[15px] font-semibold leading-none tracking-tight">?</span>
                    </button>

                    <template x-teleport="body">
                        <div
                            x-ref="menu"
                            x-show="open"
                            x-bind:style="menuStyle"
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 translate-y-1 scale-95"
                            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                            x-transition:leave-end="opacity-0 translate-y-1 scale-95"
                            x-cloak
                            class="overflow-hidden rounded-2xl border border-white/25 bg-black/55 shadow-[0_20px_50px_-12px_rgba(0,0,0,0.65)] backdrop-blur-2xl"
                            role="listbox"
                        >
                            <div class="border-b border-white/10 px-3.5 py-2.5">
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/50">Acciones</p>
                                <p class="text-xs text-white/70">¿Qué quieres hacer?</p>
                            </div>

                            <ul class="max-h-[min(16rem,calc(100dvh-8rem))] overflow-y-auto py-1.5">
                                @foreach ($actionOptions as $actionKey => $action)
                                    <li wire:key="action-{{ $actionKey }}">
                                        <button
                                            type="button"
                                            wire:click="selectAction(@js($actionKey))"
                                            x-on:click="open = false"
                                            class="{{ $selectedAction === $actionKey
                                                ? 'flex w-full items-start gap-3 px-3.5 py-2.5 text-left transition bg-emerald-500/20 hover:bg-emerald-500/25'
                                                : 'flex w-full items-start gap-3 px-3.5 py-2.5 text-left transition hover:bg-white/10' }}"
                                            role="option"
                                            aria-selected="{{ $selectedAction === $actionKey ? 'true' : 'false' }}"
                                        >
                                            <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border {{ $selectedAction === $actionKey ? 'border-emerald-400 bg-emerald-400 text-emerald-950' : 'border-white/35 bg-white/5' }}">
                                                @if ($selectedAction === $actionKey)
                                                    <svg class="h-2.5 w-2.5" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.36 7.36a1 1 0 01-1.415 0L3.296 9.44a1 1 0 111.414-1.415l3.926 3.926 6.653-6.66a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                    </svg>
                                                @endif
                                            </span>
                                            <span class="min-w-0">
                                                <span class="block text-sm font-medium text-white">{{ $action['label'] }}</span>
                                                <span class="block text-xs text-white/55">{{ $action['description'] }}</span>
                                            </span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </template>
                </div>

                {{-- Micrófono (solo escritorio) --}}
                <button
                    type="button"
                    class="hidden h-8 w-8 shrink-0 items-center justify-center rounded-full text-white/60 transition hover:bg-white/10 hover:text-white sm:flex"
                    aria-label="Entrada por voz"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3a3 3 0 00-3 3v6a3 3 0 006 0V6a3 3 0 00-3-3z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 10v1a7 7 0 01-14 0v-1M12 18v3"/>
                    </svg>
                </button>
            </div>

            {{-- Enviar (fuera de la barra) --}}
            <button
                type="submit"
                @disabled($isThinking || $handoffRequested || $selectedAction === '')
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-white/15 bg-black/40 text-white shadow-lg backdrop-blur-md transition hover:bg-black/50 disabled:cursor-not-allowed disabled:opacity-40 sm:h-11 sm:w-11"
                aria-label="Enviar"
            >
                @if ($isThinking)
                    <svg class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                @else
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5m0 0l-6 6m6-6 6 6"/>
                    </svg>
                @endif
            </button>
        </form>

        @if ($handoffRequested)
            <p class="text-center text-sm text-white/80">
                Un asesor humano continuará tu solicitud pronto.
            </p>
        @endif

        <p class="text-center text-xs text-white/40">
            Chat guiado · Integracorp
        </p>
        </div>
    </div>
</div>
