<?php

declare(strict_types=1);

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\PublicAiAgent\AgentConversationStateMachine;
use App\Services\PublicAiAgent\AgentOrchestrator;
use App\Services\PublicAiAgent\ChatAgentRegistrationService;
use App\Services\PublicAiAgent\IntentSlotFiller;
use App\Support\GuiaChat\ActionMenuOption;
use App\Support\GuiaChat\GuiaChatFeedbackRecorder;
use App\Support\GuiaChat\IntegracorpLoginPanels;
use App\Support\GuiaChat\ServiceMenuOption;
use Flux\Flux;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.guia-chat')] class extends Component {
    public string $sessionToken = '';

    public string $draft = '';

    /** @var array<int, array{id: string, role: string, content: string, animate?: bool}> */
    public array $chatFeed = [];

    public string $conversationState = 'saludo';

    public ?string $detectedIntent = null;

    public bool $handoffRequested = false;

    public bool $isThinking = false;

    public string $selectedAction = '';

    public ?string $serviceFeedbackMode = null;

    public ?string $serviceFeedbackStep = null;

    public string $serviceFeedbackReporterFirstName = '';

    public string $serviceFeedbackReporterLastName = '';

    /** @var array<string, array{label: string, description: string, short: string}> */
    public array $actionOptions = [
        'nuestros_planes' => [
            'label' => 'Nuestros Planes',
            'short' => 'Planes',
            'description' => 'Rangos de edad, coberturas y beneficios.',
        ],
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
        $this->serviceFeedbackMode = null;
        $this->serviceFeedbackStep = null;
        $this->serviceFeedbackReporterFirstName = '';
        $this->serviceFeedbackReporterLastName = '';
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
        $feedbackMode = (string) ($metadata['service_feedback_mode'] ?? '');
        $this->serviceFeedbackMode = in_array($feedbackMode, [
            ServiceMenuOption::SERVICE_SUGGESTION,
            ServiceMenuOption::GUIA_CHAT_BUG,
            ServiceMenuOption::INTEGRACORP_BUG,
        ], true) ? $feedbackMode : null;
        $feedbackStep = (string) ($metadata['service_feedback_step'] ?? '');
        if (in_array($feedbackStep, ['reporter_first_name', 'reporter_last_name'], true)) {
            $feedbackStep = ServiceMenuOption::FEEDBACK_STEP_REPORTER_NAME;
        }
        $this->serviceFeedbackStep = $this->serviceFeedbackMode !== null
            ? ($feedbackStep !== '' ? $feedbackStep : ServiceMenuOption::initialFeedbackStep($this->serviceFeedbackMode))
            : null;
        $this->serviceFeedbackReporterFirstName = (string) ($metadata['service_feedback_reporter_first_name'] ?? '');
        $this->serviceFeedbackReporterLastName = (string) ($metadata['service_feedback_reporter_last_name'] ?? '');

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
            $this->dispatch('chat-send-finished');

            return;
        }

        if ($this->handoffRequested) {
            $this->dispatch('chat-send-finished');

            return;
        }

        $this->selectedAction = $actionKey;
        $this->draft = '';
        $this->sendMessage();
    }

    /**
     * @return list<array{key: string, label: string, description: string, short: string, accent: string, icon: string}>
     */
    public function actionMenuOptions(): array
    {
        return ActionMenuOption::enrich($this->actionOptions);
    }

    /**
     * @return list<array{key: string, action: string, label: string, description: string, accent: string, icon: string, highlight_brand?: bool}>
     */
    public function serviceMenuOptions(): array
    {
        return ServiceMenuOption::catalog();
    }

    /**
     * @return list<array{label: string, route: string, accent: string, icon: string, url: string}>
     */
    public function integracorpLoginPanels(): array
    {
        return IntegracorpLoginPanels::forMenu();
    }

    public function selectServiceMenuOption(string $optionKey): void
    {
        try {
            $option = ServiceMenuOption::find($optionKey);

            if ($option === null || $this->handoffRequested) {
                return;
            }

            match ($option['action']) {
                ServiceMenuOption::BUSINESS_ADVISOR => $this->requestBusinessAdvisor(),
                ServiceMenuOption::SERVICE_SUGGESTION => $this->beginServiceFeedback(ServiceMenuOption::SERVICE_SUGGESTION),
                ServiceMenuOption::GUIA_CHAT_BUG => $this->beginServiceFeedback(ServiceMenuOption::GUIA_CHAT_BUG),
                ServiceMenuOption::INTEGRACORP_BUG => $this->beginServiceFeedback(ServiceMenuOption::INTEGRACORP_BUG),
                default => null,
            };
        } finally {
            $this->dispatch('chat-send-finished');
        }
    }

    public function serviceMenuDraftPlaceholder(): string
    {
        return ServiceMenuOption::draftPlaceholder($this->serviceFeedbackMode, $this->serviceFeedbackStep);
    }

    private function persistServiceFeedbackState(?ChatSession $session): void
    {
        if ($session === null) {
            return;
        }

        $metadata = is_array($session->metadata) ? $session->metadata : [];

        if ($this->serviceFeedbackMode === null) {
            unset(
                $metadata['service_feedback_mode'],
                $metadata['service_feedback_step'],
                $metadata['service_feedback_reporter_first_name'],
                $metadata['service_feedback_reporter_last_name'],
            );
        } else {
            $metadata['service_feedback_mode'] = $this->serviceFeedbackMode;
            $metadata['service_feedback_step'] = $this->serviceFeedbackStep;
            $metadata['service_feedback_reporter_first_name'] = $this->serviceFeedbackReporterFirstName;
            $metadata['service_feedback_reporter_last_name'] = $this->serviceFeedbackReporterLastName;
        }

        $session->metadata = $metadata;
        $session->save();
    }

    private function requestBusinessAdvisor(): void
    {
        $registrationService = app(ChatAgentRegistrationService::class);

        $session = ChatSession::query()
            ->where('public_token', $this->sessionToken)
            ->first();

        if ($session !== null) {
            $session->handoff_requested = true;
            $session->handoff_reason = 'Solicitud desde menú: Asesor de Negocios.';
            $session->status = 'handoff';
            $session->current_state = AgentConversationStateMachine::STATE_HANDOFF_HUMANO;
            $session->save();
        }

        $this->handoffRequested = true;
        $this->conversationState = AgentConversationStateMachine::STATE_HANDOFF_HUMANO;

        $this->pushAssistantReply(
            app(IntentSlotFiller::class)->publicChatHelpMessage(
                $registrationService->whatsappBusinessUrl(),
                $registrationService->whatsappBusinessDisplayLabel(),
            ),
        );
    }

    private function beginServiceFeedback(string $mode): void
    {
        $this->serviceFeedbackMode = $mode;
        $this->serviceFeedbackStep = ServiceMenuOption::initialFeedbackStep($mode);
        $this->serviceFeedbackReporterFirstName = '';
        $this->serviceFeedbackReporterLastName = '';

        $session = ChatSession::query()
            ->where('public_token', $this->sessionToken)
            ->first();

        $this->persistServiceFeedbackState($session);

        $this->pushAssistantReply(ServiceMenuOption::feedbackPrompt($mode, (string) $this->serviceFeedbackStep));
        $this->dispatch('chat-focus-draft');
    }

    private function handleServiceFeedbackInput(string $message): void
    {
        $mode = $this->serviceFeedbackMode;
        $step = $this->serviceFeedbackStep;

        if ($mode === null || $step === null) {
            return;
        }

        $this->chatFeed[] = [
            'id' => 'user-'.uniqid(),
            'role' => 'user',
            'content' => $message,
        ];
        $this->draft = '';
        $this->dispatch('chat-scroll-bottom');

        if ($step === ServiceMenuOption::FEEDBACK_STEP_REPORTER_NAME) {
            $parsed = ServiceMenuOption::parseReporterFullName($message);

            if ($parsed['last_name'] === '') {
                $this->pushAssistantReply(ServiceMenuOption::reporterNameReprompt());
                $this->dispatch('chat-focus-draft');

                return;
            }

            $this->serviceFeedbackReporterFirstName = $parsed['first_name'];
            $this->serviceFeedbackReporterLastName = $parsed['last_name'];
            $this->serviceFeedbackStep = ServiceMenuOption::FEEDBACK_STEP_MESSAGE;

            $session = ChatSession::query()->where('public_token', $this->sessionToken)->first();
            $this->persistServiceFeedbackState($session);
            $this->pushAssistantReply(ServiceMenuOption::feedbackPrompt($mode, ServiceMenuOption::FEEDBACK_STEP_MESSAGE));
            $this->dispatch('chat-focus-draft');

            return;
        }

        $this->submitServiceFeedback($message);
    }

    private function submitServiceFeedback(string $message): void
    {
        $mode = $this->serviceFeedbackMode;

        if ($mode === null) {
            return;
        }

        $session = ChatSession::query()
            ->where('public_token', $this->sessionToken)
            ->first();

        if ($session !== null) {
            app(GuiaChatFeedbackRecorder::class)->record(
                type: $mode,
                message: $message,
                session: $session,
                reporterFirstName: $this->serviceFeedbackReporterFirstName,
                reporterLastName: $this->serviceFeedbackReporterLastName,
                ipAddress: request()->ip(),
                userAgent: request()->userAgent(),
            );

            $metadata = is_array($session->metadata) ? $session->metadata : [];
            unset(
                $metadata['service_feedback_mode'],
                $metadata['service_feedback_step'],
                $metadata['service_feedback_reporter_first_name'],
                $metadata['service_feedback_reporter_last_name'],
            );
            $metadata['service_feedback'] ??= [];
            $metadata['service_feedback'][] = [
                'type' => $mode,
                'message' => $message,
                'reporter_first_name' => $this->serviceFeedbackReporterFirstName,
                'reporter_last_name' => $this->serviceFeedbackReporterLastName,
                'submitted_at' => now()->toIso8601String(),
            ];
            $session->metadata = $metadata;
            $session->save();

            $session->messages()->create([
                'role' => 'user',
                'content' => $message,
                'metadata' => [
                    'service_feedback_type' => $mode,
                    'reporter_first_name' => $this->serviceFeedbackReporterFirstName,
                    'reporter_last_name' => $this->serviceFeedbackReporterLastName,
                ],
            ]);
        }

        $this->serviceFeedbackMode = null;
        $this->serviceFeedbackStep = null;
        $this->serviceFeedbackReporterFirstName = '';
        $this->serviceFeedbackReporterLastName = '';
        $this->pushAssistantReply(ServiceMenuOption::feedbackAcknowledgement($mode));
        $this->releaseComposerChrome();
    }

    private function releaseComposerChrome(): void
    {
        $this->dispatch('chat-composer-release');
    }

    public function sendMessage(): void
    {
        try {
            $this->processSendMessage();
        } finally {
            $this->dispatch('chat-send-finished');
        }
    }

    private function processSendMessage(): void
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

        if ($this->serviceFeedbackMode !== null) {
            if ($message === '') {
                Flux::toast(
                    heading: 'Escribe tu mensaje',
                    text: 'Completa la información solicitada antes de enviar.',
                    variant: 'warning',
                );

                return;
            }

            $this->isThinking = true;
            $this->handleServiceFeedbackInput($message);
            $this->isThinking = false;

            if ($this->serviceFeedbackMode === null) {
                $this->releaseComposerChrome();
            }

            return;
        }

        if ($message !== '' && app(IntentSlotFiller::class)->isHelpRequest($message)) {
            $this->chatFeed[] = [
                'id' => 'user-'.uniqid(),
                'role' => 'user',
                'content' => $message,
            ];
            $this->draft = '';
            $this->pushAssistantReply($this->guideHelpMessage());
            $this->releaseComposerChrome();

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

        $this->releaseComposerChrome();
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

            $html .= $this->formatChatTextChunk($chunk);
        }

        return $html;
    }

    private function formatChatTextChunk(string $chunk): string
    {
        $segments = preg_split('/(\*\*.+?\*\*)/u', $chunk, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        if ($segments === false) {
            $segments = [$chunk];
        }

        $html = '';

        foreach ($segments as $segment) {
            if (preg_match('/^\*\*(.+)\*\*$/us', $segment, $match) === 1) {
                $html .= sprintf(
                    '<span class="font-semibold text-[1.1em] leading-snug text-emerald-200">%s</span>',
                    e($match[1]),
                );

                continue;
            }

            $escaped = e($segment);
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

<div
    id="guia-chat-root"
    class="guia-chat-shell fixed inset-0 z-0 flex w-full flex-col overflow-hidden pt-[env(safe-area-inset-top,0px)] pb-[env(safe-area-inset-bottom,0px)] pl-[env(safe-area-inset-left,0px)] pr-[env(safe-area-inset-right,0px)]"
    x-on:chat-send-finished.window="guiaChatUiStore().endSend()"
    x-on:guia-chat-reply-visible.window="guiaChatUiStore().clearAwaitingReply()"
>
    {{-- Fondo gradiente tipo IA --}}
    <div class="guia-chat-bg pointer-events-none fixed inset-0 bg-gradient-to-br from-[#0b1f4a] via-[#0d4f6e] to-[#14b8a6]"></div>
    <div class="guia-chat-bg pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(56,189,248,0.25),transparent_45%),radial-gradient(circle_at_80%_80%,rgba(16,185,129,0.2),transparent_40%)]"></div>

    <div class="relative z-10 mx-auto flex min-h-0 w-full max-w-3xl flex-1 flex-col overflow-hidden px-4 sm:px-6"
        x-on:chat-open-external.window="if ($event.detail?.url) { window.open($event.detail.url, '_blank', 'noopener,noreferrer'); }"
    >
        {{-- Header --}}
        <header class="relative shrink-0 px-1 pt-2 pb-2 text-center sm:px-0 sm:pt-4 sm:pb-3 md:pt-5 md:pb-4">
            <div class="pointer-events-none absolute inset-x-0 top-0 flex justify-center pt-1 sm:pt-2" aria-hidden="true">
                <div class="h-20 w-20 rounded-full bg-cyan-400/10 blur-2xl sm:h-24 sm:w-24 md:h-28 md:w-28"></div>
            </div>

            <div class="relative mx-auto flex max-w-md flex-col items-center md:max-w-lg">
                <h1 class="max-w-[16rem] text-balance sm:max-w-none">
                    <span class="block text-base font-medium tracking-tight text-white/90 sm:text-lg md:text-xl">
                        ¡Hola! Soy tu
                    </span>
                    <span class="mt-0.5 block text-2xl font-bold leading-none tracking-tight text-white sm:mt-1 sm:text-3xl md:text-4xl">
                        GU<span class="bg-gradient-to-r from-emerald-300 via-cyan-300 to-teal-200 bg-clip-text font-extrabold text-transparent drop-shadow-[0_0_16px_rgba(45,212,191,0.3)]">IA</span>-CHAT
                    </span>
                </h1>

                <div class="mt-3 h-px w-12 bg-gradient-to-r from-transparent via-white/25 to-transparent sm:mt-3.5 md:mt-4 md:w-20" aria-hidden="true"></div>
            </div>
        </header>

        {{-- Zona de chat (solo esta área hace scroll) --}}
        <div
            class="min-h-0 flex-1 overflow-y-auto overscroll-contain"
            x-data
            x-init="$el.scrollTop = $el.scrollHeight"
            x-on:chat-scroll-bottom.window="$el.scrollTop = $el.scrollHeight"
        >
            <div class="space-y-4 pt-3 pb-6 sm:pt-4 sm:pb-8 md:pt-5">
                @if (count($chatFeed) === 0)
                    <div
                        wire:key="guide-welcome"
                        x-show="! $store.guiaChatUi.optimisticThinking"
                        x-cloak
                        class="flex items-start gap-2.5 justify-start"
                    >
                        <img
                            src="{{ asset('images/chat/assistant-avatar.png') }}"
                            alt="GUÍA-CHAT Integracorp"
                            class="hidden h-9 w-9 shrink-0 rounded-full border border-white/25 bg-white object-cover shadow-md sm:block sm:h-10 sm:w-10"
                        />
                        <p
                            wire:ignore
                            class="max-w-full pt-1 text-sm leading-relaxed whitespace-pre-line text-white/95 sm:max-w-[88%] sm:text-base"
                            x-data="chatTypewriter(@js($this->guideWelcomeMessage()), @js($this->formatChatMessage($this->guideWelcomeMessage())))"
                        >
                            <span x-show="!finished">
                                <span x-text="displayedPlain"></span>
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
                            <div
                                wire:key="msg-{{ $chatMessage['id'] ?? $index }}"
                                @if (($chatMessage['animate'] ?? false) === true)
                                    wire:ignore
                                    x-data="chatTypewriter(@js($chatMessage['content']), @js($this->formatChatMessage($chatMessage['content'])), 'reply')"
                                    x-show="! preparing"
                                    x-cloak
                                    x-transition:enter="transition ease-out duration-200"
                                    x-transition:enter-start="opacity-0 translate-y-1"
                                    x-transition:enter-end="opacity-100 translate-y-0"
                                @endif
                                class="flex items-start gap-2.5 justify-start"
                            >
                                <img
                                    src="{{ asset('images/chat/assistant-avatar.png') }}"
                                    alt="Asistente Integracorp"
                                    class="hidden h-9 w-9 shrink-0 rounded-full border border-white/25 bg-white object-cover shadow-md sm:block sm:h-10 sm:w-10"
                                />
                                @if (($chatMessage['animate'] ?? false) === true)
                                    <p
                                        class="max-w-full pt-1 text-sm leading-relaxed whitespace-pre-line text-white/95 sm:max-w-[88%] sm:text-base"
                                    >
                                        <span x-show="!finished">
                                            <span x-text="displayedPlain"></span>
                                            <span
                                                x-show="isTyping"
                                                class="inline-block h-[1em] w-[2px] translate-y-[1px] animate-pulse rounded-sm bg-white/80 align-middle ml-0.5"
                                                aria-hidden="true"
                                            ></span>
                                        </span>
                                        <span hidden :hidden="!finished" x-html="formattedHtml"></span>
                                    </p>
                                @else
                                    <p class="max-w-full pt-1 text-sm leading-relaxed whitespace-pre-line text-white/95 sm:max-w-[88%] sm:text-base">
                                        {!! $this->formatChatMessage($chatMessage['content']) !!}
                                    </p>
                                @endif
                            </div>
                        @endif
                    @endforeach

                    <div
                        x-show="$store.guiaChatUi.optimisticUserMessage"
                        x-cloak
                        wire:ignore
                        class="flex justify-end"
                    >
                        <div class="max-w-[88%] rounded-2xl rounded-br-md border border-white/20 bg-white/20 px-4 py-3 text-sm text-white backdrop-blur-md">
                            <span x-text="$store.guiaChatUi.optimisticUserMessage"></span>
                        </div>
                    </div>

                    @include('pwa.partials.guia-chat-typing-indicator')

            </div>
        </div>

        {{-- Input fijo al pie del layout (sin overlap) --}}
        <div class="shrink-0 space-y-2.5 pt-2 pb-3">
        {{-- Barra de escritura horizontal (diseño tipo chat IA) --}}
        <form
            class="flex w-full items-center sm:gap-3"
            x-data="{
                draftFocused: false,
                hasDraftText: false,
                syncComposerChrome() {
                    const value = String(this.$refs.draftInput?.value ?? '').trim();
                    this.hasDraftText = value !== '';
                },
                composerMenusVisible() {
                    return ! this.draftFocused || ! this.hasDraftText;
                },
                resizeDraft() {
                    const input = this.$refs.draftInput;
                    if (! input) {
                        return;
                    }

                    const minHeight = window.matchMedia('(min-width: 640px)').matches ? 26 : 32;
                    const maxHeight = 128;

                    input.style.height = 'auto';
                    const nextHeight = Math.max(minHeight, Math.min(input.scrollHeight, maxHeight));
                    input.style.height = `${nextHeight}px`;
                    input.style.overflowY = input.scrollHeight > maxHeight ? 'auto' : 'hidden';
                },
                resolveOptimisticPreview(message) {
                    const trimmed = String(message ?? '').trim();
                    const selectedAction = String(this.$wire.selectedAction ?? '').trim();
                    const serviceFeedback = this.$wire.serviceFeedbackMode;

                    if (trimmed !== '') {
                        return { shouldOptimistic: true, preview: trimmed };
                    }

                    if (selectedAction !== '') {
                        const label = window.guiaChatActionLabels?.[selectedAction] ?? selectedAction;

                        return { shouldOptimistic: true, preview: `Seleccioné: ${label}` };
                    }

                    if (serviceFeedback) {
                        return { shouldOptimistic: false, preview: null };
                    }

                    return { shouldOptimistic: false, preview: null };
                },
                async submitDraft() {
                    const message = String(this.$refs.draftInput?.value ?? '').trim();
                    const { shouldOptimistic, preview } = this.resolveOptimisticPreview(message);
                    const store = guiaChatUiStore();

                    if (shouldOptimistic) {
                        store.beginSend(preview);
                        this.draftFocused = false;
                        this.hasDraftText = false;
                    }

                    await this.$wire.set('draft', message);

                    if (shouldOptimistic) {
                        this.$refs.draftInput.value = '';
                        this.resizeDraft();
                    }

                    try {
                        await window.guiaChatCall('sendMessage');
                    } catch (error) {
                        guiaChatUiStore().endSend();
                    }
                },
            }"
            x-on:submit.prevent="submitDraft()"
            x-on:chat-composer-release.window="draftFocused = false; hasDraftText = false"
            x-on:chat-focus-draft.window="draftFocused = true; $nextTick(() => { $refs.draftInput?.focus(); syncComposerChrome(); })"
        >
            <div class="flex min-w-0 flex-1 items-center gap-1 overflow-visible rounded-3xl border border-white/20 bg-black/30 px-1.5 py-1.5 shadow-[0_8px_32px_-12px_rgba(0,0,0,0.45)] backdrop-blur-xl sm:gap-2 sm:pl-2 sm:pr-3 sm:py-2">
                {{-- Reiniciar chat --}}
                <button
                    type="button"
                    wire:click="restartChat"
                    wire:loading.attr="disabled"
                    wire:target="restartChat"
                    x-bind:disabled="$store.guiaChatUi.isSending"
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-white/70 transition hover:bg-white/15 hover:text-white disabled:opacity-40"
                    aria-label="Reiniciar chat"
                    title="Reiniciar chat"
                >
                    <svg wire:loading.remove wire:target="restartChat" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 0 0-3.7-3.7 48.678 48.678 0 0 0-7.324 0 4.006 4.006 0 0 0-3.7 3.7c-.017.22-.032.441-.046.662M19.5 12 22.5 9m-3 3-3-3M4.5 12c0 1.232.046 2.453.138 3.662a4.006 4.006 0 0 0 3.7 3.7 48.656 48.656 0 0 0 7.324 0 4.006 4.006 0 0 0 3.7-3.7c.017-.22.032-.441.046-.662M4.5 12 1.5 15m3-3 3 3" />
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
                    placeholder="{{ $this->serviceMenuDraftPlaceholder() }}"
                    @disabled($handoffRequested)
                    x-ref="draftInput"
                    x-init="resizeDraft(); $watch('$wire.draft', () => $nextTick(() => { resizeDraft(); syncComposerChrome(); }))"
                    x-on:input="resizeDraft(); syncComposerChrome()"
                    x-on:focus="draftFocused = true; syncComposerChrome(); $dispatch('chat-composer-focus')"
                    x-on:keydown.enter.prevent="if (! $event.shiftKey) { submitDraft() }"
                    class="guia-chat-composer-input block min-h-8 max-h-32 min-w-0 flex-1 appearance-none resize-none overflow-hidden bg-transparent px-0 py-0 text-sm leading-8 text-white shadow-none placeholder:text-white/45 outline-none ring-0 focus:ring-0 focus-visible:ring-0 disabled:opacity-50 sm:min-h-[1.625rem] sm:leading-7 sm:text-base"
                ></textarea>

                <div
                    x-show="composerMenusVisible()"
                    x-cloak
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="flex shrink-0 items-center gap-1 overflow-visible"
                >
                    {{-- Selector de acción «Quiero!» --}}
                    @include('pwa.guia-chat-action-menu')

                    @include('pwa.guia-chat-service-menu')
                </div>

                {{-- Enviar (dentro de la barra en mobile para alinear márgenes) --}}
                <button
                    type="submit"
                    x-bind:disabled="$store.guiaChatUi.isSending || @js($handoffRequested) || ($wire.selectedAction === '' && $wire.serviceFeedbackMode === null)"
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-white/15 bg-black/40 text-white shadow-md backdrop-blur-md transition hover:bg-black/50 disabled:cursor-not-allowed disabled:opacity-40 sm:hidden"
                    aria-label="Enviar"
                >
                    <svg x-show="! $store.guiaChatUi.isSending" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5m0 0l-6 6m6-6 6 6"/>
                    </svg>
                    <svg x-show="$store.guiaChatUi.isSending" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                </button>
            </div>

            {{-- Enviar (fuera de la barra en tablet/desktop) --}}
            <button
                type="submit"
                x-bind:disabled="$store.guiaChatUi.isSending || @js($handoffRequested) || ($wire.selectedAction === '' && $wire.serviceFeedbackMode === null)"
                class="hidden h-10 w-10 shrink-0 items-center justify-center rounded-full border border-white/15 bg-black/40 text-white shadow-lg backdrop-blur-md transition hover:bg-black/50 disabled:cursor-not-allowed disabled:opacity-40 sm:flex sm:h-11 sm:w-11"
                aria-label="Enviar"
            >
                <svg x-show="! $store.guiaChatUi.isSending" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5m0 0l-6 6m6-6 6 6"/>
                </svg>
                <svg x-show="$store.guiaChatUi.isSending" x-cloak class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
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

@script
<script>
    window.guiaChatActionLabels = @json(collect($actionOptions)->mapWithKeys(fn (array $option, string $key): array => [$key => $option['label']]));

    window.guiaChatUiStore = function () {
        if (! window.Alpine) {
            return {
                beginSend() {},
                beginAwaitingReply() {},
                endSend() {},
                clearAwaitingReply() {},
                isTypingVisible() {
                    return false;
                },
                isSending: false,
                optimisticThinking: false,
                awaitingReply: false,
                optimisticUserMessage: null,
            };
        }

        if (! window.Alpine.store('guiaChatUi')) {
            window.Alpine.store('guiaChatUi', {
                optimisticUserMessage: null,
                optimisticThinking: false,
                awaitingReply: false,
                isSending: false,
                isTypingVisible() {
                    return this.optimisticThinking || this.awaitingReply;
                },
                beginAwaitingReply() {
                    this.awaitingReply = true;
                    this.isSending = true;
                    window.clearTimeout(this._safetyTimer);
                    this._safetyTimer = window.setTimeout(() => this.clearAwaitingReply(), 120000);
                    window.dispatchEvent(new CustomEvent('chat-scroll-bottom'));
                },
                beginSend(message = null) {
                    this.optimisticUserMessage = message;
                    this.optimisticThinking = true;
                    this.awaitingReply = true;
                    this.isSending = true;
                    window.clearTimeout(this._safetyTimer);
                    this._safetyTimer = window.setTimeout(() => {
                        this.endSend();
                        this.clearAwaitingReply();
                    }, 120000);
                    window.dispatchEvent(new CustomEvent('chat-scroll-bottom'));
                },
                endSend() {
                    window.clearTimeout(this._safetyTimer);
                    this.optimisticUserMessage = null;
                    this.optimisticThinking = false;
                    this.isSending = false;
                },
                clearAwaitingReply() {
                    window.clearTimeout(this._safetyTimer);
                    this.awaitingReply = false;
                },
            });
        }

        return window.Alpine.store('guiaChatUi');
    };

    document.addEventListener('alpine:init', () => {
        window.guiaChatUiStore();
    });

    window.guiaChatBeginAction = function (label) {
        window.guiaChatUiStore().beginSend(`Seleccioné: ${label}`);
    };

    window.guiaChatSelectAction = function (actionKey, label) {
        window.guiaChatBeginAction(label);

        return $wire.selectAction(actionKey);
    };

    window.guiaChatSelectServiceOption = function (optionKey) {
        if (optionKey !== 'integracorp_login') {
            window.guiaChatUiStore().beginAwaitingReply();
        }

        return $wire.selectServiceMenuOption(optionKey);
    };

    window.guiaChatCall = function (method, ...params) {
        return $wire.call(method, ...params);
    };
</script>
@endscript
