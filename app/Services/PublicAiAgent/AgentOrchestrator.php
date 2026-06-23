<?php

declare(strict_types=1);

namespace App\Services\PublicAiAgent;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use Illuminate\Validation\ValidationException;

class AgentOrchestrator
{
    public function __construct(
        private readonly AgentConversationStateMachine $stateMachine,
        private readonly IntentSlotFiller $intentSlotFiller,
        private readonly ProspectAgentRegistrationService $prospectAgentRegistrationService,
        private readonly PublicQuoteSimulationService $publicQuoteSimulationService,
        private readonly PublicAgentRegistrationValidationService $registrationValidationService,
        private readonly ChatAgentRegistrationService $chatAgentRegistrationService,
        private readonly ChatAgencyMasterRegistrationService $chatAgencyMasterRegistrationService,
        private readonly ChatAgencyGeneralRegistrationService $chatAgencyGeneralRegistrationService,
        private readonly PublicPlanCatalogService $publicPlanCatalogService,
        private readonly PublicPlanBenefitsService $publicPlanBenefitsService,
        private readonly ChatIndividualQuoteService $chatIndividualQuoteService,
    ) {}

    /**
     * @return array{
     *   reply: string,
     *   intent: string|null,
     *   state: string,
     *   handoff_requested: bool,
     *   tool_runs: array<int, array<string, mixed>>,
     *   external_redirect_url?: string|null,
     *   new_session_token?: string|null,
     *   open_action_menu?: bool
     * }
     */
    public function processUserMessage(ChatSession $session, string $message, ?string $selectedAction = null): array
    {
        $trimmedMessage = trim($message);

        if ($trimmedMessage === '') {
            throw ValidationException::withMessages([
                'message' => 'El mensaje no puede estar vacío.',
            ]);
        }

        $this->storeMessage($session, [
            'role' => 'user',
            'content' => $trimmedMessage,
        ]);

        $session->last_message_at = now();
        $session->save();

        $metadata = is_array($session->metadata) ? $session->metadata : [];
        $action = $metadata['selected_action'] ?? null;
        $previousAction = is_string($action) ? $action : null;

        if ($this->stateMachine->isValidAction($selectedAction)) {
            $action = $selectedAction;
            $metadata['selected_action'] = $action;

            if ($previousAction !== $action) {
                $newIntent = $this->stateMachine->intentFromAction($action);
                if ($newIntent !== null) {
                    $session->detected_intent = $newIntent;
                }
            }
        }

        if ($this->shouldBeginFreshWorkflowForActionSelection(
            $trimmedMessage,
            $metadata,
            is_string($action) ? $action : null,
            $previousAction,
        )) {
            $metadata = $this->freshWorkflowMetadataForAction((string) $action);
            $session->detected_intent = $this->stateMachine->intentFromAction((string) $action);
            $session->current_state = AgentConversationStateMachine::STATE_SALUDO;
            $session->metadata = $metadata;
            $session->save();
        }

        $detectedIntent = $session->detected_intent;
        if ($detectedIntent === null && is_string($action)) {
            $detectedIntent = $this->stateMachine->intentFromAction($action);
            if ($detectedIntent !== null) {
                $session->detected_intent = $detectedIntent;
            }
        }

        if ($detectedIntent === null) {
            $detectedIntent = $this->stateMachine->detectIntentFromText($trimmedMessage);
            if ($detectedIntent !== null) {
                $session->detected_intent = $detectedIntent;
            }
        }

        if ($this->shouldEscalateToHuman($trimmedMessage)) {
            $toolResult = $this->escalateToHuman($session, [
                'reason' => 'Solicitud directa de atención humana.',
            ]);

            $this->storeMessage($session, [
                'role' => 'assistant',
                'content' => 'Te conectaré con un asesor humano para continuar.',
                'metadata' => ['handoff' => true],
            ]);

            $session->context_summary = $this->summarizeLatestConversation($session);
            $session->current_state = AgentConversationStateMachine::STATE_HANDOFF_HUMANO;
            $session->last_message_at = now();
            $session->save();

            return [
                'reply' => 'Te conectaré con un asesor humano para continuar.',
                'intent' => $session->detected_intent,
                'state' => (string) $session->current_state,
                'handoff_requested' => true,
                'tool_runs' => [[
                    'tool' => 'escalate_to_human',
                    'arguments' => ['reason' => 'Solicitud directa de atención humana.'],
                    'result' => $toolResult,
                ]],
            ];
        }

        if ($detectedIntent !== null) {
            $intentResult = $this->handleStructuredIntentWorkflow($session, $detectedIntent, $trimmedMessage, is_string($action) ? $action : null, $metadata);
            if ($intentResult !== null) {
                return $intentResult;
            }
        }

        $assistantReply = $this->genericGuidedReply();

        $this->storeMessage($session, [
            'role' => 'assistant',
            'content' => $assistantReply,
            'metadata' => ['guided_fallback' => true],
        ]);

        $session->metadata = $metadata;
        $session->context_summary = $this->summarizeLatestConversation($session);
        $session->current_state = $this->stateMachine->resolveNextState(
            currentState: (string) $session->current_state,
            intent: $detectedIntent,
            toolWasExecuted: false,
            handoffRequested: (bool) $session->handoff_requested,
        );
        $session->last_message_at = now();
        $session->save();

        return [
            'reply' => $assistantReply,
            'intent' => $session->detected_intent,
            'state' => (string) $session->current_state,
            'handoff_requested' => (bool) $session->handoff_requested,
            'tool_runs' => [],
        ];
    }

    /**
     * @return array{
     *   reply: string,
     *   intent: string|null,
     *   state: string,
     *   handoff_requested: bool,
     *   tool_runs: array<int, array<string, mixed>>,
     *   external_redirect_url?: string|null
     * }|null
     */
    private function handleStructuredIntentWorkflow(
        ChatSession $session,
        string $intent,
        string $message,
        ?string $action,
        array $metadata,
    ): ?array {
        $intentPayload = is_array($metadata['intent_payload'] ?? null) ? $metadata['intent_payload'] : [];

        if ($metadata['awaiting_another_action_offer'] ?? false) {
            return $this->handleAnotherActionOfferConfirmation($session, $intent, $message, $metadata);
        }

        if (($metadata['awaiting_agency_master_not_received_offer'] ?? false)) {
            return $this->handleAgencyMasterNotReceivedOffer($session, $intent, $message, $metadata);
        }

        if (($metadata['awaiting_whatsapp_offer'] ?? false)) {
            return $this->handleWhatsAppOfferConfirmation($session, $intent, $message, $metadata);
        }

        if (
            $this->stateMachine->isRegistrationWithCredentialsFlow($action)
            && ($metadata['awaiting_show_credentials'] ?? false)
        ) {
            return $this->handleShowCredentialsConfirmation($session, $intent, $message, $metadata);
        }

        if ($metadata['awaiting_phone_credentials_offer'] ?? false) {
            return $this->handlePhoneCredentialsOfferConfirmation($session, $intent, $message, $metadata);
        }

        if (
            $this->stateMachine->isIndividualQuoteAction($action)
            && $intent === AgentConversationStateMachine::INTENT_COTIZACION
        ) {
            return $this->handleIndividualQuoteChatWorkflow($session, $intent, $message, $metadata);
        }

        if (
            $this->stateMachine->isSimplifiedPreregistrationAction($action)
            && ! ($metadata['agent_welcome_sent'] ?? false)
        ) {
            $metadata['agent_welcome_sent'] = true;
            $metadata['selected_action'] = $action;
            $metadata['intent_payload'] = $this->intentSlotFiller->applyActionPreset($action, $intentPayload);

            $assistantReply = $this->intentSlotFiller->registrationWelcomeMessage($action);
            $this->storeMessage($session, [
                'role' => 'assistant',
                'content' => $assistantReply,
                'metadata' => [
                    'intent' => $intent,
                    'agent_welcome' => true,
                ],
            ]);

            $session->metadata = $metadata;
            $session->context_summary = $this->summarizeLatestConversation($session);
            $session->current_state = AgentConversationStateMachine::STATE_RECOLECCION_DATOS;
            $session->last_message_at = now();
            $session->save();

            return [
                'reply' => $assistantReply,
                'intent' => $session->detected_intent,
                'state' => (string) $session->current_state,
                'handoff_requested' => (bool) $session->handoff_requested,
                'tool_runs' => [],
            ];
        }

        if (
            (
                $this->stateMachine->isAgentOrSubagentRegistrationAction($action)
                || $this->stateMachine->isAgencyRegistrationAction($action)
            )
            && ($metadata['awaiting_agency_selection'] ?? false)
        ) {
            $agencyFlowResult = $this->handleAgencySelection($session, $intent, $message, $action, $metadata);
            if ($agencyFlowResult !== null) {
                return $agencyFlowResult;
            }

            $intentPayload = is_array($metadata['intent_payload'] ?? null) ? $metadata['intent_payload'] : [];
        }

        $intentPayload = $this->intentSlotFiller->applyActionPreset($action, $intentPayload);
        $intentPayload = $this->intentSlotFiller->mergePayloadFromMessage($intent, $intentPayload, $message, $action);
        $metadata['intent_payload'] = $intentPayload;

        if ($this->stateMachine->isSimplifiedPreregistrationAction($action)) {
            $validationFlowResult = $this->handleSimplifiedPreregistrationValidation($session, $intent, $intentPayload, $action, $metadata);
            if ($validationFlowResult !== null) {
                return $validationFlowResult;
            }

            $intentPayload = is_array($metadata['intent_payload'] ?? null) ? $metadata['intent_payload'] : [];
        }

        $missingFields = $this->intentSlotFiller->missingRequiredFields($intent, $intentPayload, $action);
        if ($missingFields !== []) {
            $metadata['awaiting_confirmation'] = false;
            $metadata['awaiting_confirmation_intent'] = null;

            $assistantReply = $this->enrichCollectionQuestion(
                $intent,
                $missingFields[0],
                $this->intentSlotFiller->questionForField($intent, $missingFields[0], $action),
                $intentPayload,
            );
            $this->storeMessage($session, [
                'role' => 'assistant',
                'content' => $assistantReply,
                'metadata' => [
                    'intent' => $intent,
                    'missing_fields' => $missingFields,
                ],
            ]);

            $session->metadata = $metadata;
            $session->context_summary = $this->summarizeLatestConversation($session);
            $session->current_state = AgentConversationStateMachine::STATE_RECOLECCION_DATOS;
            $session->last_message_at = now();
            $session->save();

            return [
                'reply' => $assistantReply,
                'intent' => $session->detected_intent,
                'state' => (string) $session->current_state,
                'handoff_requested' => (bool) $session->handoff_requested,
                'tool_runs' => [],
            ];
        }

        $awaitingConfirmation = (bool) ($metadata['awaiting_confirmation'] ?? false)
            && ($metadata['awaiting_confirmation_intent'] ?? null) === $intent;

        if (! $awaitingConfirmation || ! $this->intentSlotFiller->isConfirmation($message)) {
            $metadata['awaiting_confirmation'] = true;
            $metadata['awaiting_confirmation_intent'] = $intent;

            $assistantReply = $this->intentSlotFiller->confirmationPrompt($intent, $intentPayload, $action);
            $metadata['intent_payload'] = $intentPayload;
            $this->storeMessage($session, [
                'role' => 'assistant',
                'content' => $assistantReply,
                'metadata' => [
                    'intent' => $intent,
                    'awaiting_confirmation' => true,
                ],
            ]);

            $session->metadata = $metadata;
            $session->context_summary = $this->summarizeLatestConversation($session);
            $session->current_state = AgentConversationStateMachine::STATE_CONFIRMACION;
            $session->last_message_at = now();
            $session->save();

            return [
                'reply' => $assistantReply,
                'intent' => $session->detected_intent,
                'state' => (string) $session->current_state,
                'handoff_requested' => (bool) $session->handoff_requested,
                'tool_runs' => [],
            ];
        }

        try {
            $toolRun = $this->executeIntentWorkflowTool($session, $intent, $intentPayload, $action);
        } catch (ValidationException $exception) {
            $assistantReply = collect($exception->errors())->flatten()->filter()->implode(' ');
            if ($assistantReply === '') {
                $assistantReply = 'Faltan datos para continuar. Por favor indícame la información pendiente.';
            }

            $this->storeMessage($session, [
                'role' => 'assistant',
                'content' => $assistantReply,
                'metadata' => ['intent' => $intent, 'validation_error' => true],
            ]);

            $session->metadata = $metadata;
            $session->context_summary = $this->summarizeLatestConversation($session);
            $session->current_state = AgentConversationStateMachine::STATE_RECOLECCION_DATOS;
            $session->last_message_at = now();
            $session->save();

            return [
                'reply' => $assistantReply,
                'intent' => $session->detected_intent,
                'state' => (string) $session->current_state,
                'handoff_requested' => (bool) $session->handoff_requested,
                'tool_runs' => [],
            ];
        }

        if ($toolRun['tool'] === 'register_chat_agent') {
            return $this->handleChatAgentRegistrationToolResult($session, $intent, $metadata, $toolRun);
        }

        if ($toolRun['tool'] === 'register_chat_agency_master') {
            return $this->handleChatAgentRegistrationToolResult($session, $intent, $metadata, $toolRun);
        }

        if ($toolRun['tool'] === 'register_chat_agency_general') {
            return $this->handleChatAgentRegistrationToolResult($session, $intent, $metadata, $toolRun);
        }

        if (
            $toolRun['tool'] === 'create_prospect_preregistration'
            && $this->stateMachine->isSimplifiedAgencyGeneralRegistrationAction($action)
        ) {
            return $this->handleAgencyProspectRegistrationToolResult($session, $intent, $metadata, $toolRun);
        }

        $this->storeMessage($session, [
            'role' => 'tool',
            'tool_name' => $toolRun['tool'],
            'tool_call_id' => 'manual-intent-flow',
            'tool_arguments' => $toolRun['arguments'],
            'tool_result' => $toolRun['result'],
            'content' => json_encode($toolRun['result'], JSON_UNESCAPED_UNICODE),
        ]);

        $assistantReply = $this->buildIntentToolSuccessReply($intent, $toolRun['result']);
        $this->storeMessage($session, [
            'role' => 'assistant',
            'content' => $assistantReply,
            'metadata' => ['intent' => $intent, 'tool' => $toolRun['tool']],
        ]);

        $metadata['awaiting_confirmation'] = false;
        $metadata['awaiting_confirmation_intent'] = null;
        $metadata['last_tool_result'] = $toolRun['result'];

        $session->metadata = $metadata;
        $session->context_summary = $this->summarizeLatestConversation($session);
        $session->current_state = $this->stateMachine->resolveNextState(
            currentState: (string) $session->current_state,
            intent: $intent,
            toolWasExecuted: true,
            handoffRequested: (bool) $session->handoff_requested,
        );
        $session->last_message_at = now();
        $session->save();

        return [
            'reply' => $assistantReply,
            'intent' => $session->detected_intent,
            'state' => (string) $session->current_state,
            'handoff_requested' => (bool) $session->handoff_requested,
            'tool_runs' => [$toolRun],
        ];
    }

    /**
     * @param  array<string, mixed>  $intentPayload
     * @return array{tool: string, arguments: array<string, mixed>, result: array<string, mixed>}
     */
    private function executeIntentWorkflowTool(ChatSession $session, string $intent, array $intentPayload, ?string $action = null): array
    {
        if (
            $intent === AgentConversationStateMachine::INTENT_PREREGISTRO
            && $this->intentSlotFiller->isSimplifiedAgentRegistrationAction($action)
        ) {
            $arguments = [
                'name' => (string) ($intentPayload['name'] ?? ''),
                'email' => (string) ($intentPayload['email'] ?? ''),
                'phone' => (string) ($intentPayload['phone_1'] ?? ''),
                'owner_code' => $this->chatAgentRegistrationService->resolveOwnerCode($intentPayload),
                'classification' => (string) ($intentPayload['classification'] ?? ''),
                'selected_agency_id' => $intentPayload['selected_agency_id'] ?? null,
            ];

            return [
                'tool' => 'register_chat_agent',
                'arguments' => $arguments,
                'result' => [
                    'registration' => $this->chatAgentRegistrationService->register($arguments),
                ],
            ];
        }

        if (
            $intent === AgentConversationStateMachine::INTENT_PREREGISTRO
            && $this->stateMachine->isSimplifiedAgencyMasterRegistrationAction($action)
        ) {
            $arguments = [
                'name_corporative' => (string) ($intentPayload['agency_corporate_name'] ?? ''),
                'tax_id' => (string) ($intentPayload['tax_id'] ?? ''),
                'email' => (string) ($intentPayload['email'] ?? ''),
                'phone' => (string) ($intentPayload['phone_1'] ?? ''),
            ];

            return [
                'tool' => 'register_chat_agency_master',
                'arguments' => $arguments,
                'result' => [
                    'registration' => $this->chatAgencyMasterRegistrationService->register($arguments),
                ],
            ];
        }

        if (
            $intent === AgentConversationStateMachine::INTENT_PREREGISTRO
            && $this->stateMachine->isSimplifiedAgencyGeneralRegistrationAction($action)
        ) {
            $arguments = [
                'name_corporative' => (string) ($intentPayload['agency_corporate_name'] ?? ''),
                'tax_id' => (string) ($intentPayload['tax_id'] ?? ''),
                'email' => (string) ($intentPayload['email'] ?? ''),
                'phone' => (string) ($intentPayload['phone_1'] ?? ''),
                'owner_code' => $this->registrationValidationService->resolveGeneralRegistrationOwnerCode($intentPayload),
            ];

            return [
                'tool' => 'register_chat_agency_general',
                'arguments' => $arguments,
                'result' => [
                    'registration' => $this->chatAgencyGeneralRegistrationService->register($arguments),
                ],
            ];
        }

        if ($intent === AgentConversationStateMachine::INTENT_PREREGISTRO) {
            $arguments = [
                ...$intentPayload,
                'created_by' => 'CHAT PUBLICO',
                'updated_by' => 'CHAT PUBLICO',
                'conversation_summary' => $this->summarizeLatestConversation($session),
            ];

            return [
                'tool' => 'create_prospect_preregistration',
                'arguments' => $arguments,
                'result' => [
                    'preregistration' => $this->prospectAgentRegistrationService->create($arguments),
                ],
            ];
        }

        return [
            'tool' => 'simulate_health_quote',
            'arguments' => $intentPayload,
            'result' => [
                'quote' => $this->publicQuoteSimulationService->simulate($intentPayload),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function buildIntentToolSuccessReply(string $intent, array $result): string
    {
        if ($intent === AgentConversationStateMachine::INTENT_PREREGISTRO) {
            /** @var array<string, mixed> $preregistration */
            $preregistration = is_array($result['preregistration'] ?? null) ? $result['preregistration'] : [];

            return sprintf(
                'Listo, tu preregistro fue creado exitosamente con ID %s y estatus %s. Si deseas, ahora te ayudo con una cotización de planes de salud.',
                (string) ($preregistration['prospect_agent_id'] ?? 'N/D'),
                (string) ($preregistration['status'] ?? 'N/D'),
            );
        }

        /** @var array<string, mixed> $quote */
        $quote = is_array($result['quote'] ?? null) ? $result['quote'] : [];
        /** @var array<string, mixed> $totals */
        $totals = is_array($quote['totals'] ?? null) ? $quote['totals'] : [];

        return sprintf(
            'Cotización calculada. Total anual: %s USD, semestral: %s USD, trimestral: %s USD, mensual: %s USD. Si quieres, te explico el detalle por edad.',
            number_format((float) ($totals['annual'] ?? 0), 2, '.', ','),
            number_format((float) ($totals['semiannual'] ?? 0), 2, '.', ','),
            number_format((float) ($totals['quarterly'] ?? 0), 2, '.', ','),
            number_format((float) ($totals['monthly'] ?? 0), 2, '.', ','),
        );
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function escalateToHuman(ChatSession $session, array $arguments): array
    {
        $reason = trim((string) ($arguments['reason'] ?? 'Escalamiento solicitado por el asistente.'));

        $session->handoff_requested = true;
        $session->handoff_reason = $reason;
        $session->status = 'handoff';
        $session->save();

        return [
            'handoff_requested' => true,
            'reason' => $reason,
            'message' => 'Un asesor humano continuará la atención.',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storeMessage(ChatSession $session, array $payload): ChatMessage
    {
        return $session->messages()->create($payload);
    }

    private function summarizeLatestConversation(ChatSession $session): ?string
    {
        $lastMessages = $session->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->latest('id')
            ->limit(6)
            ->get()
            ->reverse()
            ->map(function (ChatMessage $message): string {
                $prefix = $message->role === 'user' ? 'Usuario' : 'Asistente';

                return $prefix.': '.trim((string) $message->content);
            })
            ->filter()
            ->implode("\n");

        return $lastMessages !== '' ? mb_substr($lastMessages, 0, 1800) : null;
    }

    private function genericGuidedReply(): string
    {
        return 'Selecciona una opción para comenzar: cotización individual, cotización corporativa, registro agencia master, registro agencia general, registro agente o registro subagente.';
    }

    private function shouldEscalateToHuman(string $message): bool
    {
        $normalized = mb_strtolower($message);

        return str_contains($normalized, 'asesor humano')
            || str_contains($normalized, 'hablar con asesor')
            || str_contains($normalized, 'agente humano')
            || str_contains($normalized, 'persona real');
    }

    private function enrichCollectionQuestion(string $intent, string $field, string $question, array $intentPayload): string
    {
        if ($intent !== AgentConversationStateMachine::INTENT_COTIZACION) {
            return $question;
        }

        if ($field === 'plan_id') {
            return $question."\n\n".$this->buildPlanCatalogChatSummary();
        }

        if ($field === 'coverage_id') {
            $planId = (int) ($intentPayload['plan_id'] ?? 0);

            return $question."\n\n".$this->buildCoverageChatSummary($planId);
        }

        return $question;
    }

    private function buildPlanCatalogChatSummary(): string
    {
        return $this->publicPlanCatalogService->buildPlanCatalogChatSummary(
            $this->publicPlanCatalogService->getPlanCatalog(),
        );
    }

    private function buildCoverageChatSummary(int $planId): string
    {
        if ($planId <= 0) {
            return 'Primero indícame el plan (inicial, ideal o especial).';
        }

        $plans = $this->publicPlanCatalogService->getPlanCatalog();
        $selectedPlan = collect($plans)->first(fn (array $plan): bool => (int) ($plan['plan_id'] ?? 0) === $planId);

        return $this->publicPlanCatalogService->buildCoverageChatSummaryForPlan(
            is_array($selectedPlan) ? $selectedPlan : null,
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{
     *   reply: string,
     *   intent: string|null,
     *   state: string,
     *   handoff_requested: bool,
     *   tool_runs: array<int, array<string, mixed>>,
     *   external_redirect_url?: string|null
     * }|null
     */
    private function handleAgencySelection(
        ChatSession $session,
        string $intent,
        string $message,
        ?string $action,
        array $metadata,
    ): ?array {
        /** @var list<array{id: int, name: string, code: string|null, label: string}> $candidates */
        $candidates = is_array($metadata['agency_candidates'] ?? null) ? $metadata['agency_candidates'] : [];

        $selectedAgency = $this->registrationValidationService->resolveAgencySelection($message, $candidates);

        if ($selectedAgency === null) {
            $assistantReply = $this->stateMachine->isSimplifiedAgencyGeneralRegistrationAction($action)
                ? $this->registrationValidationService->masterAgencySelectionPrompt($candidates)
                : $this->registrationValidationService->agencySelectionPrompt($candidates);
            $assistantReply = 'No pude identificar tu selección. '.$assistantReply;

            $this->storeMessage($session, [
                'role' => 'assistant',
                'content' => $assistantReply,
                'metadata' => ['intent' => $intent, 'awaiting_agency_selection' => true],
            ]);

            $session->metadata = $metadata;
            $session->context_summary = $this->summarizeLatestConversation($session);
            $session->current_state = AgentConversationStateMachine::STATE_RECOLECCION_DATOS;
            $session->last_message_at = now();
            $session->save();

            return [
                'reply' => $assistantReply,
                'intent' => $session->detected_intent,
                'state' => (string) $session->current_state,
                'handoff_requested' => (bool) $session->handoff_requested,
                'tool_runs' => [],
            ];
        }

        $intentPayload = is_array($metadata['intent_payload'] ?? null) ? $metadata['intent_payload'] : [];
        $intentPayload = $this->stateMachine->isSimplifiedAgencyGeneralRegistrationAction($action)
            ? $this->registrationValidationService->applySelectedMasterAgency($selectedAgency, $intentPayload)
            : $this->registrationValidationService->applySelectedAgency($selectedAgency, $intentPayload);

        $metadata['awaiting_agency_selection'] = false;
        $metadata['awaiting_agency_retry'] = false;
        $metadata['awaiting_registration_correction'] = false;
        $metadata['agency_candidates'] = null;
        $metadata['intent_payload'] = $intentPayload;
        $metadata['awaiting_confirmation'] = true;
        $metadata['awaiting_confirmation_intent'] = $intent;

        $assistantReply = $this->intentSlotFiller->confirmationPrompt($intent, $intentPayload, $action);
        $this->storeMessage($session, [
            'role' => 'assistant',
            'content' => $assistantReply,
            'metadata' => [
                'intent' => $intent,
                'awaiting_confirmation' => true,
                'agency_selected' => $selectedAgency['id'],
            ],
        ]);

        $session->metadata = $metadata;
        $session->context_summary = $this->summarizeLatestConversation($session);
        $session->current_state = AgentConversationStateMachine::STATE_CONFIRMACION;
        $session->last_message_at = now();
        $session->save();

        return [
            'reply' => $assistantReply,
            'intent' => $session->detected_intent,
            'state' => (string) $session->current_state,
            'handoff_requested' => (bool) $session->handoff_requested,
            'tool_runs' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{
     *   reply: string,
     *   intent: string|null,
     *   state: string,
     *   handoff_requested: bool,
     *   tool_runs: array<int, array<string, mixed>>,
     *   external_redirect_url?: string|null
     * }|null
     */
    private function handleSimplifiedPreregistrationValidation(
        ChatSession $session,
        string $intent,
        array $intentPayload,
        ?string $action,
        array $metadata,
    ): ?array {
        $missingBeforeValidation = $this->intentSlotFiller->missingRequiredFields($intent, $intentPayload, $action);
        if ($missingBeforeValidation !== []) {
            return null;
        }

        $validation = match (true) {
            $this->stateMachine->isSimplifiedAgencyMasterRegistrationAction($action) => $this->registrationValidationService->validateSimplifiedAgencyMasterPayload($intentPayload),
            $this->stateMachine->isSimplifiedAgencyGeneralRegistrationAction($action) => $this->registrationValidationService->validateSimplifiedAgencyGeneralPayload($intentPayload),
            default => $this->registrationValidationService->validateSimplifiedPayload($intentPayload),
        };

        if ($validation['errors'] !== []) {
            $metadata['awaiting_confirmation'] = false;
            $metadata['awaiting_confirmation_intent'] = null;
            $metadata['awaiting_agency_selection'] = false;
            $metadata['agency_candidates'] = null;
            $metadata['awaiting_registration_correction'] = true;
            $metadata['intent_payload'] = $intentPayload;

            $assistantReply = $this->registrationValidationService->formatValidationErrors($validation['errors']);
            $this->storeMessage($session, [
                'role' => 'assistant',
                'content' => $assistantReply,
                'metadata' => ['intent' => $intent, 'validation_error' => true],
            ]);

            $session->metadata = $metadata;
            $session->context_summary = $this->summarizeLatestConversation($session);
            $session->current_state = AgentConversationStateMachine::STATE_RECOLECCION_DATOS;
            $session->last_message_at = now();
            $session->save();

            return [
                'reply' => $assistantReply,
                'intent' => $session->detected_intent,
                'state' => (string) $session->current_state,
                'handoff_requested' => (bool) $session->handoff_requested,
                'tool_runs' => [],
            ];
        }

        if ($this->stateMachine->isSimplifiedAgencyMasterRegistrationAction($action)) {
            $metadata['intent_payload'] = $intentPayload;
            $metadata['awaiting_agency_selection'] = false;
            $metadata['awaiting_agency_retry'] = false;
            $metadata['awaiting_registration_correction'] = false;
            $metadata['agency_candidates'] = null;
            $session->metadata = $metadata;

            return null;
        }

        if (
            $this->stateMachine->isSimplifiedAgencyGeneralRegistrationAction($action)
            && $this->registrationValidationService->isTdgMasterTerm((string) ($intentPayload['master_agency_name'] ?? ''))
        ) {
            $intentPayload = $this->registrationValidationService->applyTdgMasterAgency($intentPayload);
            $metadata['intent_payload'] = $intentPayload;
            $metadata['awaiting_agency_selection'] = false;
            $metadata['awaiting_agency_retry'] = false;
            $metadata['awaiting_registration_correction'] = false;
            $metadata['agency_candidates'] = null;
            $session->metadata = $metadata;

            return null;
        }

        $agencies = $validation['agencies'];

        if ($agencies === []) {
            $searchTerm = $this->stateMachine->isSimplifiedAgencyGeneralRegistrationAction($action)
                ? trim((string) ($intentPayload['master_agency_name'] ?? 'tu búsqueda'))
                : trim((string) ($intentPayload['agency_name'] ?? 'tu búsqueda'));
            $metadata['awaiting_confirmation'] = false;
            $metadata['awaiting_confirmation_intent'] = null;
            $metadata['awaiting_agency_retry'] = true;
            $metadata['awaiting_registration_correction'] = true;
            $metadata['awaiting_agency_selection'] = false;
            $metadata['agency_candidates'] = null;
            $metadata['intent_payload'] = $intentPayload;

            $assistantReply = $this->stateMachine->isSimplifiedAgencyGeneralRegistrationAction($action)
                ? $this->registrationValidationService->masterAgencyNotFoundMessage($searchTerm)
                : $this->registrationValidationService->agencyNotFoundMessage($searchTerm);
            $this->storeMessage($session, [
                'role' => 'assistant',
                'content' => $assistantReply,
                'metadata' => ['intent' => $intent, 'validation_error' => true],
            ]);

            $session->metadata = $metadata;
            $session->context_summary = $this->summarizeLatestConversation($session);
            $session->current_state = AgentConversationStateMachine::STATE_RECOLECCION_DATOS;
            $session->last_message_at = now();
            $session->save();

            return [
                'reply' => $assistantReply,
                'intent' => $session->detected_intent,
                'state' => (string) $session->current_state,
                'handoff_requested' => (bool) $session->handoff_requested,
                'tool_runs' => [],
            ];
        }

        if (count($agencies) > 1 || (
            count($agencies) === 1
            && ! $this->registrationValidationService->isExactAgencyMatch(
                $this->stateMachine->isSimplifiedAgencyGeneralRegistrationAction($action)
                    ? trim((string) ($intentPayload['master_agency_name'] ?? ''))
                    : trim((string) ($intentPayload['agency_name'] ?? '')),
                $agencies[0],
            )
        )) {
            $metadata['awaiting_agency_selection'] = true;
            $metadata['awaiting_agency_retry'] = false;
            $metadata['awaiting_registration_correction'] = false;
            $metadata['agency_candidates'] = $agencies;
            $metadata['awaiting_confirmation'] = false;
            $metadata['awaiting_confirmation_intent'] = null;
            $metadata['intent_payload'] = $intentPayload;

            $assistantReply = $this->stateMachine->isSimplifiedAgencyGeneralRegistrationAction($action)
                ? $this->registrationValidationService->masterAgencySelectionPrompt($agencies)
                : $this->registrationValidationService->agencySelectionPrompt($agencies);
            $this->storeMessage($session, [
                'role' => 'assistant',
                'content' => $assistantReply,
                'metadata' => [
                    'intent' => $intent,
                    'awaiting_agency_selection' => true,
                ],
            ]);

            $session->metadata = $metadata;
            $session->context_summary = $this->summarizeLatestConversation($session);
            $session->current_state = AgentConversationStateMachine::STATE_RECOLECCION_DATOS;
            $session->last_message_at = now();
            $session->save();

            return [
                'reply' => $assistantReply,
                'intent' => $session->detected_intent,
                'state' => (string) $session->current_state,
                'handoff_requested' => (bool) $session->handoff_requested,
                'tool_runs' => [],
            ];
        }

        $intentPayload = $this->stateMachine->isSimplifiedAgencyGeneralRegistrationAction($action)
            ? $this->registrationValidationService->applySelectedMasterAgency($agencies[0], $intentPayload)
            : $this->registrationValidationService->applySelectedAgency($agencies[0], $intentPayload);
        $metadata['intent_payload'] = $intentPayload;
        $metadata['awaiting_agency_selection'] = false;
        $metadata['awaiting_agency_retry'] = false;
        $metadata['awaiting_registration_correction'] = false;
        $metadata['agency_candidates'] = null;
        $session->metadata = $metadata;

        return null;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array{tool: string, arguments: array<string, mixed>, result: array<string, mixed>}  $toolRun
     * @return array{
     *   reply: string,
     *   intent: string|null,
     *   state: string,
     *   handoff_requested: bool,
     *   tool_runs: array<int, array<string, mixed>>,
     *   external_redirect_url?: string|null
     * }
     */
    private function handleAgencyProspectRegistrationToolResult(
        ChatSession $session,
        string $intent,
        array $metadata,
        array $toolRun,
    ): array {
        $this->storeMessage($session, [
            'role' => 'tool',
            'tool_name' => $toolRun['tool'],
            'tool_call_id' => 'manual-intent-flow',
            'tool_arguments' => $toolRun['arguments'],
            'tool_result' => $toolRun['result'],
            'content' => json_encode($toolRun['result'], JSON_UNESCAPED_UNICODE),
        ]);

        /** @var array<string, mixed> $preregistration */
        $preregistration = is_array($toolRun['result']['preregistration'] ?? null) ? $toolRun['result']['preregistration'] : [];

        $metadata['awaiting_confirmation'] = false;
        $metadata['awaiting_confirmation_intent'] = null;
        $metadata['last_tool_result'] = $toolRun['result'];
        $metadata['awaiting_another_action_offer'] = true;

        $assistantReply = $this->intentSlotFiller->agencyProspectRegistrationSuccessMessage(
            (int) ($preregistration['prospect_agent_id'] ?? 0),
            (string) ($preregistration['status'] ?? 'captación'),
        );

        $this->storeMessage($session, [
            'role' => 'assistant',
            'content' => $assistantReply,
            'metadata' => ['intent' => $intent, 'tool' => $toolRun['tool']],
        ]);

        $session->metadata = $metadata;
        $session->context_summary = $this->summarizeLatestConversation($session);
        $session->current_state = AgentConversationStateMachine::STATE_CONFIRMACION;
        $session->last_message_at = now();
        $session->save();

        return [
            'reply' => $assistantReply,
            'intent' => $session->detected_intent,
            'state' => (string) $session->current_state,
            'handoff_requested' => (bool) $session->handoff_requested,
            'tool_runs' => [$toolRun],
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array{tool: string, arguments: array<string, mixed>, result: array<string, mixed>}  $toolRun
     * @return array{
     *   reply: string,
     *   intent: string|null,
     *   state: string,
     *   handoff_requested: bool,
     *   tool_runs: array<int, array<string, mixed>>,
     *   external_redirect_url?: string|null
     * }
     */
    private function handleChatAgentRegistrationToolResult(
        ChatSession $session,
        string $intent,
        array $metadata,
        array $toolRun,
    ): array {
        $this->storeMessage($session, [
            'role' => 'tool',
            'tool_name' => $toolRun['tool'],
            'tool_call_id' => 'manual-intent-flow',
            'tool_arguments' => $toolRun['arguments'],
            'tool_result' => $toolRun['result'],
            'content' => json_encode($toolRun['result'], JSON_UNESCAPED_UNICODE),
        ]);

        /** @var array<string, mixed> $registration */
        $registration = is_array($toolRun['result']['registration'] ?? null) ? $toolRun['result']['registration'] : [];

        $metadata['awaiting_confirmation'] = false;
        $metadata['awaiting_confirmation_intent'] = null;
        $metadata['last_tool_result'] = $toolRun['result'];

        if (($registration['success'] ?? false) === true) {
            $credentials = is_array($registration['data'] ?? null) ? $registration['data'] : [];
            $metadata['registration_credentials'] = $credentials;
            $metadata['awaiting_whatsapp_offer'] = false;

            if (in_array($credentials['registration_type'] ?? '', ['agency_master', 'agency_general'], true)) {
                $metadata['awaiting_show_credentials'] = false;
                $metadata['awaiting_phone_credentials_offer'] = false;
                $metadata['awaiting_agency_master_not_received_offer'] = true;

                $assistantReply = ($credentials['registration_type'] ?? '') === 'agency_general'
                    ? $this->intentSlotFiller->agencyGeneralRegistrationDeliveredMessage(
                        (string) ($credentials['email'] ?? 'tu correo registrado'),
                        (string) ($credentials['phone'] ?? 'tu teléfono registrado'),
                    )
                    : $this->intentSlotFiller->agencyMasterRegistrationDeliveredMessage(
                        (string) ($credentials['email'] ?? 'tu correo registrado'),
                        (string) ($credentials['phone'] ?? 'tu teléfono registrado'),
                    );
            } else {
                $metadata['awaiting_show_credentials'] = true;
                $metadata['awaiting_agency_master_not_received_offer'] = false;

                $assistantReply = $this->intentSlotFiller->chatAgentRegistrationDeliveredMessage(
                    (string) ($credentials['email'] ?? 'tu correo registrado'),
                    (string) ($credentials['phone'] ?? 'tu teléfono registrado'),
                );
            }
        } else {
            $metadata['awaiting_whatsapp_offer'] = true;
            $metadata['awaiting_show_credentials'] = false;
            $metadata['registration_credentials'] = null;

            $assistantReply = trim((string) ($registration['message'] ?? ''));
            if ($assistantReply === '') {
                $assistantReply = $this->intentSlotFiller->chatAgentRegistrationFailurePrompt();
            } else {
                $assistantReply .= "\n\n".$this->intentSlotFiller->chatAgentRegistrationFailurePrompt();
            }
        }

        $this->storeMessage($session, [
            'role' => 'assistant',
            'content' => $assistantReply,
            'metadata' => ['intent' => $intent, 'tool' => $toolRun['tool']],
        ]);

        $session->metadata = $metadata;
        $session->context_summary = $this->summarizeLatestConversation($session);
        $session->current_state = AgentConversationStateMachine::STATE_CONFIRMACION;
        $session->last_message_at = now();
        $session->save();

        return [
            'reply' => $assistantReply,
            'intent' => $session->detected_intent,
            'state' => (string) $session->current_state,
            'handoff_requested' => (bool) $session->handoff_requested,
            'tool_runs' => [$toolRun],
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{
     *   reply: string,
     *   intent: string|null,
     *   state: string,
     *   handoff_requested: bool,
     *   tool_runs: array<int, array<string, mixed>>,
     *   external_redirect_url?: string|null
     * }
     */
    private function handleAgencyMasterNotReceivedOffer(
        ChatSession $session,
        string $intent,
        string $message,
        array $metadata,
    ): array {
        if ($this->intentSlotFiller->isConfirmation($message)) {
            $assistantReply = $this->intentSlotFiller->agencyMasterRegistrationReceivedAnotherActionOfferMessage();

            $metadata['awaiting_agency_master_not_received_offer'] = false;
            $metadata['registration_credentials'] = null;
            $metadata['awaiting_another_action_offer'] = true;

            $this->storeMessage($session, [
                'role' => 'assistant',
                'content' => $assistantReply,
                'metadata' => ['intent' => $intent, 'awaiting_another_action_offer' => true],
            ]);
        } elseif ($this->intentSlotFiller->isRejection($message)) {
            $assistantReply = $this->intentSlotFiller->chatAgentRegistrationChatCredentialsOfferMessage();

            $metadata['awaiting_agency_master_not_received_offer'] = false;
            $metadata['awaiting_phone_credentials_offer'] = true;

            $this->storeMessage($session, [
                'role' => 'assistant',
                'content' => $assistantReply,
                'metadata' => ['intent' => $intent, 'awaiting_phone_credentials_offer' => true],
            ]);

            $session->metadata = $metadata;
            $session->context_summary = $this->summarizeLatestConversation($session);
            $session->current_state = AgentConversationStateMachine::STATE_CONFIRMACION;
            $session->last_message_at = now();
            $session->save();

            return [
                'reply' => $assistantReply,
                'intent' => $session->detected_intent,
                'state' => (string) $session->current_state,
                'handoff_requested' => (bool) $session->handoff_requested,
                'tool_runs' => [],
            ];
        } else {
            $assistantReply = $this->intentSlotFiller->agencyMasterRegistrationNotReceivedReprompt();

            $this->storeMessage($session, [
                'role' => 'assistant',
                'content' => $assistantReply,
                'metadata' => ['intent' => $intent, 'awaiting_agency_master_not_received_offer' => true],
            ]);

            $session->metadata = $metadata;
            $session->last_message_at = now();
            $session->save();

            return [
                'reply' => $assistantReply,
                'intent' => $session->detected_intent,
                'state' => (string) $session->current_state,
                'handoff_requested' => (bool) $session->handoff_requested,
                'tool_runs' => [],
            ];
        }

        $session->metadata = $metadata;
        $session->context_summary = $this->summarizeLatestConversation($session);
        $session->current_state = AgentConversationStateMachine::STATE_CONFIRMACION;
        $session->last_message_at = now();
        $session->save();

        return [
            'reply' => $assistantReply,
            'intent' => $session->detected_intent,
            'state' => (string) $session->current_state,
            'handoff_requested' => (bool) $session->handoff_requested,
            'tool_runs' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{
     *   reply: string,
     *   intent: string|null,
     *   state: string,
     *   handoff_requested: bool,
     *   tool_runs: array<int, array<string, mixed>>,
     *   external_redirect_url?: string|null
     * }
     */
    private function handleShowCredentialsConfirmation(
        ChatSession $session,
        string $intent,
        string $message,
        array $metadata,
    ): array {
        if ($this->intentSlotFiller->isConfirmation($message)) {
            $assistantReply = $this->intentSlotFiller->chatAgentRegistrationReceivedAnotherActionOfferMessage();

            $metadata['awaiting_show_credentials'] = false;
            $metadata['registration_credentials'] = null;
            $metadata['awaiting_another_action_offer'] = true;

            $this->storeMessage($session, [
                'role' => 'assistant',
                'content' => $assistantReply,
                'metadata' => ['intent' => $intent, 'awaiting_another_action_offer' => true],
            ]);

            $session->metadata = $metadata;
            $session->context_summary = $this->summarizeLatestConversation($session);
            $session->current_state = AgentConversationStateMachine::STATE_CONFIRMACION;
            $session->last_message_at = now();
            $session->save();

            return [
                'reply' => $assistantReply,
                'intent' => $session->detected_intent,
                'state' => (string) $session->current_state,
                'handoff_requested' => (bool) $session->handoff_requested,
                'tool_runs' => [],
            ];
        }

        if ($this->intentSlotFiller->isRejection($message)) {
            $assistantReply = $this->intentSlotFiller->chatAgentRegistrationChatCredentialsOfferMessage();

            $metadata['awaiting_show_credentials'] = false;
            $metadata['awaiting_phone_credentials_offer'] = true;

            $this->storeMessage($session, [
                'role' => 'assistant',
                'content' => $assistantReply,
                'metadata' => ['intent' => $intent, 'awaiting_phone_credentials_offer' => true],
            ]);

            $session->metadata = $metadata;
            $session->context_summary = $this->summarizeLatestConversation($session);
            $session->current_state = AgentConversationStateMachine::STATE_CONFIRMACION;
            $session->last_message_at = now();
            $session->save();

            return [
                'reply' => $assistantReply,
                'intent' => $session->detected_intent,
                'state' => (string) $session->current_state,
                'handoff_requested' => (bool) $session->handoff_requested,
                'tool_runs' => [],
            ];
        }

        $assistantReply = $this->intentSlotFiller->chatAgentRegistrationDeliveryConfirmationReprompt();

        $this->storeMessage($session, [
            'role' => 'assistant',
            'content' => $assistantReply,
            'metadata' => ['intent' => $intent, 'awaiting_show_credentials' => true],
        ]);

        $session->metadata = $metadata;
        $session->last_message_at = now();
        $session->save();

        return [
            'reply' => $assistantReply,
            'intent' => $session->detected_intent,
            'state' => (string) $session->current_state,
            'handoff_requested' => (bool) $session->handoff_requested,
            'tool_runs' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{
     *   reply: string,
     *   intent: string|null,
     *   state: string,
     *   handoff_requested: bool,
     *   tool_runs: array<int, array<string, mixed>>,
     *   external_redirect_url?: string|null
     * }
     */
    private function handlePhoneCredentialsOfferConfirmation(
        ChatSession $session,
        string $intent,
        string $message,
        array $metadata,
    ): array {
        if ($this->intentSlotFiller->isRejection($message)) {
            $assistantReply = $this->intentSlotFiller->chatAgentRegistrationChatCredentialsDeclinedMessage();

            $metadata['awaiting_phone_credentials_offer'] = false;
            $metadata['registration_credentials'] = null;
            $metadata['awaiting_another_action_offer'] = true;

            $this->storeMessage($session, [
                'role' => 'assistant',
                'content' => $assistantReply,
                'metadata' => ['intent' => $intent, 'chat_credentials_declined' => true, 'awaiting_another_action_offer' => true],
            ]);

            $session->metadata = $metadata;
            $session->context_summary = $this->summarizeLatestConversation($session);
            $session->current_state = AgentConversationStateMachine::STATE_CONFIRMACION;
            $session->last_message_at = now();
            $session->save();

            return [
                'reply' => $assistantReply,
                'intent' => $session->detected_intent,
                'state' => (string) $session->current_state,
                'handoff_requested' => (bool) $session->handoff_requested,
                'tool_runs' => [],
            ];
        }

        if (! $this->intentSlotFiller->isConfirmation($message)) {
            $assistantReply = $this->intentSlotFiller->chatAgentRegistrationChatCredentialsOfferReprompt();

            $this->storeMessage($session, [
                'role' => 'assistant',
                'content' => $assistantReply,
                'metadata' => ['intent' => $intent, 'awaiting_phone_credentials_offer' => true],
            ]);

            $session->metadata = $metadata;
            $session->last_message_at = now();
            $session->save();

            return [
                'reply' => $assistantReply,
                'intent' => $session->detected_intent,
                'state' => (string) $session->current_state,
                'handoff_requested' => (bool) $session->handoff_requested,
                'tool_runs' => [],
            ];
        }

        return $this->deliverRegistrationCredentialsInChat($session, $intent, $metadata);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{
     *   reply: string,
     *   intent: string|null,
     *   state: string,
     *   handoff_requested: bool,
     *   tool_runs: array<int, array<string, mixed>>,
     *   external_redirect_url?: string|null
     * }
     */
    private function deliverRegistrationCredentialsInChat(
        ChatSession $session,
        string $intent,
        array $metadata,
    ): array {
        /** @var array<string, mixed> $credentials */
        $credentials = is_array($metadata['registration_credentials'] ?? null) ? $metadata['registration_credentials'] : [];
        $registrationType = (string) ($credentials['registration_type'] ?? 'chat_agent');

        $credentials = match ($registrationType) {
            'agency_master' => $this->chatAgencyMasterRegistrationService->enrichRegistrationCredentials($credentials),
            'agency_general' => $this->chatAgencyGeneralRegistrationService->enrichRegistrationCredentials($credentials),
            default => $this->chatAgentRegistrationService->enrichRegistrationCredentials($credentials),
        };

        $welcomeLetterUrl = null;
        $name = trim((string) ($credentials['name'] ?? ''));

        if ($name !== '') {
            try {
                ini_set('memory_limit', '2048M');

                $relativePath = match ($registrationType) {
                    'agency_master' => (string) ($credentials['code_agency'] ?? '') !== ''
                        ? $this->chatAgencyMasterRegistrationService->ensureWelcomeLetterPdf((string) $credentials['code_agency'], $name)
                        : null,
                    'agency_general' => (string) ($credentials['code_agency'] ?? '') !== ''
                        ? $this->chatAgencyGeneralRegistrationService->ensureWelcomeLetterPdf((string) $credentials['code_agency'], $name)
                        : null,
                    default => (int) ($credentials['agent_id'] ?? 0) > 0
                        ? $this->chatAgentRegistrationService->ensureWelcomeLetterPdf((int) $credentials['agent_id'], $name)
                        : null,
                };

                if (is_string($relativePath) && $relativePath !== '') {
                    $welcomeLetterUrl = $this->chatAgentRegistrationService->publicStorageDocumentUrl($relativePath);
                }
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        $assistantReply = match ($registrationType) {
            'agency_master' => $this->intentSlotFiller->agencyMasterRegistrationCredentialsMessage($credentials, $welcomeLetterUrl),
            'agency_general' => $this->intentSlotFiller->agencyGeneralRegistrationCredentialsMessage($credentials, $welcomeLetterUrl),
            default => $this->intentSlotFiller->chatAgentRegistrationCredentialsMessage($credentials, $welcomeLetterUrl),
        };
        $assistantReply .= "\n\n".$this->intentSlotFiller->chatAgentAnotherActionOfferMessage();

        $metadata['awaiting_show_credentials'] = false;
        $metadata['awaiting_phone_credentials_offer'] = false;
        $metadata['registration_credentials'] = null;
        $metadata['awaiting_another_action_offer'] = true;

        $this->storeMessage($session, [
            'role' => 'assistant',
            'content' => $assistantReply,
            'metadata' => ['intent' => $intent, 'credentials_delivered_in_chat' => true],
        ]);

        $session->metadata = $metadata;
        $session->context_summary = $this->summarizeLatestConversation($session);
        $session->current_state = AgentConversationStateMachine::STATE_CONFIRMACION;
        $session->last_message_at = now();
        $session->save();

        return [
            'reply' => $assistantReply,
            'intent' => $session->detected_intent,
            'state' => (string) $session->current_state,
            'handoff_requested' => (bool) $session->handoff_requested,
            'tool_runs' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{
     *   reply: string,
     *   intent: string|null,
     *   state: string,
     *   handoff_requested: bool,
     *   tool_runs: array<int, array<string, mixed>>,
     *   external_redirect_url?: string|null
     * }
     */
    private function deliverRegistrationCredentialsViaWhatsApp(
        ChatSession $session,
        string $intent,
        array $metadata,
        bool $clearAwaitingShowCredentials,
    ): array {
        /** @var array<string, mixed> $credentials */
        $credentials = is_array($metadata['registration_credentials'] ?? null) ? $metadata['registration_credentials'] : [];
        $registrationType = (string) ($credentials['registration_type'] ?? 'chat_agent');

        $credentials = match ($registrationType) {
            'agency_master' => $this->chatAgencyMasterRegistrationService->enrichRegistrationCredentials($credentials),
            'agency_general' => $this->chatAgencyGeneralRegistrationService->enrichRegistrationCredentials($credentials),
            default => $this->chatAgentRegistrationService->enrichRegistrationCredentials($credentials),
        };
        $phone = (string) ($credentials['phone'] ?? '');

        $sent = match ($registrationType) {
            'agency_master' => $this->chatAgencyMasterRegistrationService->queueRegistrationPackageViaWhatsApp($credentials),
            'agency_general' => $this->chatAgencyGeneralRegistrationService->queueRegistrationPackageViaWhatsApp($credentials),
            default => $this->chatAgentRegistrationService->queueRegistrationPackageViaWhatsApp($credentials),
        };

        $assistantReply = $sent
            ? $this->intentSlotFiller->chatAgentRegistrationCredentialsViaWhatsAppSentMessage($phone)
            : $this->intentSlotFiller->chatAgentRegistrationCredentialsViaWhatsAppFailedMessage();

        if ($sent) {
            if ($clearAwaitingShowCredentials) {
                $metadata['awaiting_show_credentials'] = false;
            }

            $metadata['awaiting_phone_credentials_offer'] = false;
            $metadata['registration_credentials'] = null;
            $metadata['awaiting_another_action_offer'] = true;
        } else {
            $metadata['awaiting_show_credentials'] = false;
            $metadata['awaiting_phone_credentials_offer'] = true;
        }

        $this->storeMessage($session, [
            'role' => 'assistant',
            'content' => $assistantReply,
            'metadata' => ['intent' => $intent, 'credentials_whatsapp_sent' => $sent],
        ]);

        $session->metadata = $metadata;
        $session->context_summary = $this->summarizeLatestConversation($session);
        $session->current_state = AgentConversationStateMachine::STATE_CONFIRMACION;
        $session->last_message_at = now();
        $session->save();

        return [
            'reply' => $assistantReply,
            'intent' => $session->detected_intent,
            'state' => (string) $session->current_state,
            'handoff_requested' => (bool) $session->handoff_requested,
            'tool_runs' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{
     *   reply: string,
     *   intent: string|null,
     *   state: string,
     *   handoff_requested: bool,
     *   tool_runs: array<int, array<string, mixed>>,
     *   external_redirect_url?: string|null,
     *   new_session_token?: string|null,
     *   open_action_menu?: bool
     * }
     */
    private function handleAnotherActionOfferConfirmation(
        ChatSession $session,
        string $intent,
        string $message,
        array $metadata,
    ): array {
        if ($this->intentSlotFiller->isRejection($message)) {
            $assistantReply = $this->intentSlotFiller->chatAgentFarewellMessage(
                $this->chatAgentRegistrationService->whatsappBusinessUrl(),
                $this->chatAgentRegistrationService->whatsappBusinessDisplayLabel(),
            );

            $metadata['awaiting_another_action_offer'] = false;
            $metadata['conversation_completed'] = true;

            $this->storeMessage($session, [
                'role' => 'assistant',
                'content' => $assistantReply,
                'metadata' => ['intent' => $intent, 'another_action_declined' => true],
            ]);

            $session->metadata = $metadata;
            $session->context_summary = $this->summarizeLatestConversation($session);
            $session->current_state = AgentConversationStateMachine::STATE_CONFIRMACION;
            $session->last_message_at = now();
            $session->save();

            return [
                'reply' => $assistantReply,
                'intent' => $session->detected_intent,
                'state' => (string) $session->current_state,
                'handoff_requested' => (bool) $session->handoff_requested,
                'tool_runs' => [],
            ];
        }

        if (! $this->intentSlotFiller->isConfirmation($message)) {
            $assistantReply = $this->intentSlotFiller->chatAgentAnotherActionOfferReprompt();

            $this->storeMessage($session, [
                'role' => 'assistant',
                'content' => $assistantReply,
                'metadata' => ['intent' => $intent, 'awaiting_another_action_offer' => true],
            ]);

            $session->metadata = $metadata;
            $session->last_message_at = now();
            $session->save();

            return [
                'reply' => $assistantReply,
                'intent' => $session->detected_intent,
                'state' => (string) $session->current_state,
                'handoff_requested' => (bool) $session->handoff_requested,
                'tool_runs' => [],
            ];
        }

        $session->update(['status' => 'closed']);

        $newSession = ChatSession::startPublic(
            ipAddress: $session->ip_address,
            userAgent: $session->user_agent,
        );

        $assistantReply = $this->intentSlotFiller->publicChatRestartWithActionsMessage();

        $this->storeMessage($newSession, [
            'role' => 'assistant',
            'content' => $assistantReply,
            'metadata' => ['public_chat_restarted' => true],
        ]);

        $newSession->current_state = AgentConversationStateMachine::STATE_SALUDO;
        $newSession->last_message_at = now();
        $newSession->save();

        return [
            'reply' => $assistantReply,
            'intent' => null,
            'state' => AgentConversationStateMachine::STATE_SALUDO,
            'handoff_requested' => false,
            'tool_runs' => [],
            'new_session_token' => (string) $newSession->public_token,
            'open_action_menu' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{
     *   reply: string,
     *   intent: string|null,
     *   state: string,
     *   handoff_requested: bool,
     *   tool_runs: array<int, array<string, mixed>>,
     *   external_redirect_url?: string|null
     * }
     */
    private function handleWhatsAppOfferConfirmation(
        ChatSession $session,
        string $intent,
        string $message,
        array $metadata,
    ): array {
        if (! $this->intentSlotFiller->isConfirmation($message)) {
            $assistantReply = 'Si deseas hablar con negocios por WhatsApp, responde si. También puedes contactarnos al 04127018390.';

            $this->storeMessage($session, [
                'role' => 'assistant',
                'content' => $assistantReply,
                'metadata' => ['intent' => $intent, 'awaiting_whatsapp_offer' => true],
            ]);

            $session->metadata = $metadata;
            $session->last_message_at = now();
            $session->save();

            return [
                'reply' => $assistantReply,
                'intent' => $session->detected_intent,
                'state' => (string) $session->current_state,
                'handoff_requested' => (bool) $session->handoff_requested,
                'tool_runs' => [],
            ];
        }

        $whatsappUrl = $this->chatAgentRegistrationService->whatsappBusinessUrl();
        $assistantReply = $this->intentSlotFiller->chatAgentWhatsAppRedirectMessage(
            $whatsappUrl,
            $this->chatAgentRegistrationService->whatsappBusinessDisplayLabel(),
        );

        $metadata['awaiting_whatsapp_offer'] = false;

        $this->storeMessage($session, [
            'role' => 'assistant',
            'content' => $assistantReply,
            'metadata' => ['intent' => $intent, 'whatsapp_redirect' => true],
        ]);

        $session->metadata = $metadata;
        $session->context_summary = $this->summarizeLatestConversation($session);
        $session->current_state = AgentConversationStateMachine::STATE_CONFIRMACION;
        $session->last_message_at = now();
        $session->save();

        return [
            'reply' => $assistantReply,
            'intent' => $session->detected_intent,
            'state' => (string) $session->current_state,
            'handoff_requested' => (bool) $session->handoff_requested,
            'tool_runs' => [],
            'external_redirect_url' => $whatsappUrl,
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function shouldBeginFreshWorkflowForActionSelection(
        string $message,
        array $metadata,
        ?string $action,
        ?string $previousAction = null,
    ): bool {
        if (! is_string($action) || ! $this->stateMachine->isValidAction($action)) {
            return false;
        }

        if (! $this->intentSlotFiller->isDefaultActionSelectionMessage($message)) {
            return false;
        }

        if ((bool) ($metadata['conversation_completed'] ?? false)) {
            return true;
        }

        if ((bool) ($metadata['awaiting_another_action_offer'] ?? false)) {
            return true;
        }

        return is_string($previousAction)
            && $previousAction !== ''
            && $previousAction !== $action;
    }

    /**
     * @return array<string, mixed>
     */
    private function freshWorkflowMetadataForAction(string $action): array
    {
        return [
            'selected_action' => $action,
            'intent_payload' => $this->intentSlotFiller->applyActionPreset($action, []),
            'conversation_completed' => false,
            'awaiting_another_action_offer' => false,
            'awaiting_confirmation' => false,
            'awaiting_confirmation_intent' => null,
            'agent_welcome_sent' => false,
            'awaiting_show_credentials' => false,
            'awaiting_phone_credentials_offer' => false,
            'awaiting_agency_master_not_received_offer' => false,
            'awaiting_whatsapp_offer' => false,
            'awaiting_agency_selection' => false,
            'registration_credentials' => null,
            'last_tool_result' => null,
            'individual_quote_catalog_shown' => false,
            'awaiting_individual_quote_action' => false,
            'individual_quote_collecting' => false,
            'awaiting_more_quote_plans' => false,
            'individual_quote_multiple_mode' => false,
            'individual_quote_entries' => [],
            'pending_quote_entry' => null,
            'awaiting_quote_age' => false,
            'quote_contact' => [],
            'awaiting_quote_contact_field' => null,
            'awaiting_quote_confirmation' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{
     *   reply: string,
     *   intent: string|null,
     *   state: string,
     *   handoff_requested: bool,
     *   tool_runs: array<int, array<string, mixed>>
     * }
     */
    private function handleIndividualQuoteChatWorkflow(
        ChatSession $session,
        string $intent,
        string $message,
        array $metadata,
    ): array {
        if ($metadata['awaiting_quote_confirmation'] ?? false) {
            return $this->handleIndividualQuoteConfirmation($session, $intent, $message, $metadata);
        }

        if (is_string($metadata['awaiting_quote_contact_field'] ?? null)) {
            return $this->handleIndividualQuoteContactCollection($session, $intent, $message, $metadata);
        }

        if ($metadata['awaiting_quote_age'] ?? false) {
            return $this->handleIndividualQuotePendingAge($session, $intent, $message, $metadata);
        }

        if ($metadata['awaiting_more_quote_plans'] ?? false) {
            return $this->handleIndividualQuoteMorePlans($session, $intent, $message, $metadata);
        }

        if ($metadata['individual_quote_collecting'] ?? false) {
            return $this->handleIndividualQuoteEntryCollection($session, $intent, $message, $metadata);
        }

        if ($metadata['awaiting_individual_quote_action'] ?? false) {
            return $this->handleIndividualQuoteModeChoice($session, $intent, $message, $metadata);
        }

        $catalogSummary = $this->buildPlanCatalogChatSummary();
        $assistantReply = $this->intentSlotFiller->individualQuoteWelcomeWithCatalogMessage($catalogSummary);

        $metadata['individual_quote_catalog_shown'] = true;
        $metadata['awaiting_individual_quote_action'] = true;
        $metadata['individual_quote_entries'] = [];
        $metadata['quote_contact'] = [];

        return $this->finalizeIndividualQuoteWorkflowReply($session, $intent, $metadata, $assistantReply);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{
     *   reply: string,
     *   intent: string|null,
     *   state: string,
     *   handoff_requested: bool,
     *   tool_runs: array<int, array<string, mixed>>
     * }
     */
    private function handleIndividualQuoteModeChoice(
        ChatSession $session,
        string $intent,
        string $message,
        array $metadata,
    ): array {
        $planId = $this->intentSlotFiller->parsePlanBenefitsRequest($message);

        if ($planId !== null) {
            $assistantReply = $this->publicPlanBenefitsService->buildBenefitsMessage($planId)
                ."\n\n"
                .$this->publicPlanBenefitsService->benefitsReminderMessage();

            return $this->finalizeIndividualQuoteWorkflowReply($session, $intent, $metadata, $assistantReply);
        }

        if ($this->intentSlotFiller->isCotizarKeyword($message)) {
            $metadata['awaiting_individual_quote_action'] = false;
            $metadata['individual_quote_collecting'] = true;
            $metadata['individual_quote_multiple_mode'] = false;
            $metadata['individual_quote_entries'] = [];

            $assistantReply = $this->intentSlotFiller->individualQuoteCotizarIntroMessage();

            return $this->finalizeIndividualQuoteWorkflowReply($session, $intent, $metadata, $assistantReply);
        }

        $assistantReply = 'No pude interpretar tu respuesta. '
            .$this->intentSlotFiller->individualQuoteModeChoiceReprompt();

        return $this->finalizeIndividualQuoteWorkflowReply($session, $intent, $metadata, $assistantReply);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{
     *   reply: string,
     *   intent: string|null,
     *   state: string,
     *   handoff_requested: bool,
     *   tool_runs: array<int, array<string, mixed>>
     * }
     */
    private function handleIndividualQuoteEntryCollection(
        ChatSession $session,
        string $intent,
        string $message,
        array $metadata,
    ): array {
        if ($this->intentSlotFiller->isMultipleKeyword($message)) {
            $metadata['individual_quote_multiple_mode'] = true;
            $metadata['individual_quote_entries'] = [];
            $metadata['awaiting_more_quote_plans'] = false;
            $assistantReply = $this->intentSlotFiller->individualQuoteMultipleIntroMessage();

            return $this->finalizeIndividualQuoteWorkflowReply($session, $intent, $metadata, $assistantReply);
        }

        $multipleMode = (bool) ($metadata['individual_quote_multiple_mode'] ?? false);
        $parsed = $this->intentSlotFiller->parseIndividualQuoteLine($message);

        if ($parsed === null) {
            $assistantReply = $this->intentSlotFiller->individualQuoteInvalidLineMessage($multipleMode);

            return $this->finalizeIndividualQuoteWorkflowReply($session, $intent, $metadata, $assistantReply);
        }

        if ($multipleMode && $parsed['format'] === 'compact') {
            $assistantReply = 'En cotización múltiple debes indicar plan, edad y personas (ejemplo: 2, 34, 1).';

            return $this->finalizeIndividualQuoteWorkflowReply($session, $intent, $metadata, $assistantReply);
        }

        if (! $multipleMode) {
            /** @var list<array<string, mixed>> $existingEntries */
            $existingEntries = is_array($metadata['individual_quote_entries'] ?? null) ? $metadata['individual_quote_entries'] : [];

            if ($existingEntries !== []) {
                $assistantReply = 'Solo puedes cotizar un plan a la vez. Para varios planes escribe «multiple».';

                return $this->finalizeIndividualQuoteWorkflowReply($session, $intent, $metadata, $assistantReply);
            }
        }

        return $this->appendIndividualQuoteEntry($session, $intent, $metadata, $parsed);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array{plan_id: int, age: int|null, total_persons: int, format: string}  $parsed
     * @return array{
     *   reply: string,
     *   intent: string|null,
     *   state: string,
     *   handoff_requested: bool,
     *   tool_runs: array<int, array<string, mixed>>
     * }
     */
    private function appendIndividualQuoteEntry(
        ChatSession $session,
        string $intent,
        array $metadata,
        array $parsed,
    ): array {
        $planId = (int) $parsed['plan_id'];
        $totalPersons = (int) $parsed['total_persons'];
        $age = $parsed['age'] !== null ? (int) $parsed['age'] : null;
        $ageRangeModel = null;

        if ($age === null) {
            $ageRangeId = $this->chatIndividualQuoteService->resolveSingleAgeRangeIdForPlan($planId);

            if ($ageRangeId === null) {
                $metadata['pending_quote_entry'] = [
                    'plan_id' => $planId,
                    'total_persons' => $totalPersons,
                ];
                $metadata['awaiting_quote_age'] = true;
                $metadata['individual_quote_collecting'] = false;

                $assistantReply = $this->intentSlotFiller->individualQuoteAskAgeMessage($planId, $totalPersons);

                return $this->finalizeIndividualQuoteWorkflowReply($session, $intent, $metadata, $assistantReply);
            }

            $ageRangeModel = \App\Models\AgeRange::query()->find($ageRangeId);
        } else {
            $ageRangeModel = $this->chatIndividualQuoteService->resolveAgeRangeForPlanAndAge($planId, $age);

            if ($ageRangeModel === null) {
                $assistantReply = sprintf(
                    'No encontré un rango de edad válido para %d años en el %s (ID %d). Recuerda: 1=Inicial, 2=Ideal, 3=Especial.',
                    $age,
                    $this->chatIndividualQuoteService->planLabel($planId),
                    $planId,
                );

                return $this->finalizeIndividualQuoteWorkflowReply($session, $intent, $metadata, $assistantReply);
            }
        }

        if ($ageRangeModel === null || (int) $ageRangeModel->plan_id !== $planId) {
            $assistantReply = sprintf(
                'El rango de edad no coincide con el plan indicado. Para el %s usa el ID %d (ejemplo: %d, %d, %d).',
                $this->chatIndividualQuoteService->planLabel($planId),
                $planId,
                $planId,
                $age ?? 0,
                $totalPersons,
            );

            return $this->finalizeIndividualQuoteWorkflowReply($session, $intent, $metadata, $assistantReply);
        }

        /** @var list<array<string, mixed>> $entries */
        $entries = is_array($metadata['individual_quote_entries'] ?? null) ? $metadata['individual_quote_entries'] : [];

        $entries[] = [
            'plan_id' => $planId,
            'plan_label' => $this->chatIndividualQuoteService->planLabel($planId),
            'age' => $age,
            'age_range_id' => (int) $ageRangeModel->id,
            'age_range_label' => (string) $ageRangeModel->range,
            'total_persons' => $totalPersons,
        ];

        $metadata['individual_quote_entries'] = $entries;
        $metadata['individual_quote_collecting'] = false;
        $metadata['pending_quote_entry'] = null;
        $metadata['awaiting_quote_age'] = false;

        if ($metadata['individual_quote_multiple_mode'] ?? false) {
            $metadata['awaiting_more_quote_plans'] = true;
            $assistantReply = $this->intentSlotFiller->individualQuoteAfterEntryPrompt(true);

            return $this->finalizeIndividualQuoteWorkflowReply($session, $intent, $metadata, $assistantReply);
        }

        return $this->beginIndividualQuoteContactCollection($session, $intent, $metadata);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{
     *   reply: string,
     *   intent: string|null,
     *   state: string,
     *   handoff_requested: bool,
     *   tool_runs: array<int, array<string, mixed>>
     * }
     */
    private function handleIndividualQuotePendingAge(
        ChatSession $session,
        string $intent,
        string $message,
        array $metadata,
    ): array {
        $age = $this->intentSlotFiller->parseQuoteAgeOnly($message);

        if ($age === null) {
            $assistantReply = 'Indica una edad válida entre 0 y 120 años (ejemplo: 45).';

            return $this->finalizeIndividualQuoteWorkflowReply($session, $intent, $metadata, $assistantReply);
        }

        /** @var array{plan_id: int, total_persons: int}|null $pending */
        $pending = is_array($metadata['pending_quote_entry'] ?? null) ? $metadata['pending_quote_entry'] : null;

        if ($pending === null) {
            $metadata['awaiting_quote_age'] = false;
            $metadata['individual_quote_collecting'] = true;

            $assistantReply = $this->intentSlotFiller->individualQuoteCotizarIntroMessage();

            return $this->finalizeIndividualQuoteWorkflowReply($session, $intent, $metadata, $assistantReply);
        }

        return $this->appendIndividualQuoteEntry($session, $intent, $metadata, [
            'plan_id' => (int) $pending['plan_id'],
            'age' => $age,
            'total_persons' => (int) $pending['total_persons'],
            'format' => 'compact',
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{
     *   reply: string,
     *   intent: string|null,
     *   state: string,
     *   handoff_requested: bool,
     *   tool_runs: array<int, array<string, mixed>>
     * }
     */
    private function handleIndividualQuoteMorePlans(
        ChatSession $session,
        string $intent,
        string $message,
        array $metadata,
    ): array {
        if ($this->intentSlotFiller->isRejection($message)) {
            $metadata['awaiting_more_quote_plans'] = false;

            return $this->beginIndividualQuoteContactCollection($session, $intent, $metadata);
        }

        $parsed = $this->intentSlotFiller->parseIndividualQuoteLine($message);

        if ($parsed === null) {
            $assistantReply = 'Responde no para continuar o agrega otro plan con el formato plan, edad, personas (ejemplo: 2, 30, 4).';

            return $this->finalizeIndividualQuoteWorkflowReply($session, $intent, $metadata, $assistantReply);
        }

        if ($parsed['format'] === 'compact' && $parsed['age'] === null) {
            $assistantReply = 'Para agregar otro plan en cotización múltiple usa plan, edad y personas (ejemplo: 2, 30, 4).';

            return $this->finalizeIndividualQuoteWorkflowReply($session, $intent, $metadata, $assistantReply);
        }

        $metadata['individual_quote_multiple_mode'] = true;
        $metadata['awaiting_more_quote_plans'] = false;
        $metadata['individual_quote_collecting'] = false;

        return $this->appendIndividualQuoteEntry($session, $intent, $metadata, $parsed);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{
     *   reply: string,
     *   intent: string|null,
     *   state: string,
     *   handoff_requested: bool,
     *   tool_runs: array<int, array<string, mixed>>
     * }
     */
    private function beginIndividualQuoteContactCollection(
        ChatSession $session,
        string $intent,
        array $metadata,
    ): array {
        $metadata['awaiting_more_quote_plans'] = false;
        $metadata['individual_quote_collecting'] = false;
        $metadata['awaiting_quote_contact_field'] = 'full_name';
        $metadata['quote_contact'] = is_array($metadata['quote_contact'] ?? null) ? $metadata['quote_contact'] : [];

        $assistantReply = $this->intentSlotFiller->individualQuoteAfterEntryPrompt(false)
            ."\n\n"
            .$this->intentSlotFiller->individualQuoteContactQuestion('full_name');

        return $this->finalizeIndividualQuoteWorkflowReply($session, $intent, $metadata, $assistantReply);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{
     *   reply: string,
     *   intent: string|null,
     *   state: string,
     *   handoff_requested: bool,
     *   tool_runs: array<int, array<string, mixed>>
     * }
     */
    private function handleIndividualQuoteContactCollection(
        ChatSession $session,
        string $intent,
        string $message,
        array $metadata,
    ): array {
        $field = (string) ($metadata['awaiting_quote_contact_field'] ?? '');
        $value = trim($message);

        /** @var array<string, string> $contact */
        $contact = is_array($metadata['quote_contact'] ?? null) ? $metadata['quote_contact'] : [];

        if ($value === '') {
            $assistantReply = $this->intentSlotFiller->individualQuoteContactQuestion($field);

            return $this->finalizeIndividualQuoteWorkflowReply($session, $intent, $metadata, $assistantReply);
        }

        if ($field === 'full_name') {
            $contact['full_name'] = mb_strtoupper($value);
            $metadata['quote_contact'] = $contact;
            $metadata['awaiting_quote_contact_field'] = 'agent_name';
            $assistantReply = $this->intentSlotFiller->individualQuoteContactQuestion('agent_name');

            return $this->finalizeIndividualQuoteWorkflowReply($session, $intent, $metadata, $assistantReply);
        }

        if ($field === 'agent_name') {
            $contact['agent_name'] = mb_strtoupper($value);
            $metadata['quote_contact'] = $contact;
            $metadata['awaiting_quote_contact_field'] = null;
            $metadata['awaiting_quote_confirmation'] = true;

            /** @var list<array<string, mixed>> $entries */
            $entries = is_array($metadata['individual_quote_entries'] ?? null) ? $metadata['individual_quote_entries'] : [];
            $assistantReply = $this->intentSlotFiller->individualQuoteConfirmationSummary($entries, $contact);

            return $this->finalizeIndividualQuoteWorkflowReply(
                $session,
                $intent,
                $metadata,
                $assistantReply,
                AgentConversationStateMachine::STATE_CONFIRMACION,
            );
        }

        $metadata['awaiting_quote_contact_field'] = 'full_name';
        $assistantReply = $this->intentSlotFiller->individualQuoteContactQuestion('full_name');

        return $this->finalizeIndividualQuoteWorkflowReply($session, $intent, $metadata, $assistantReply);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{
     *   reply: string,
     *   intent: string|null,
     *   state: string,
     *   handoff_requested: bool,
     *   tool_runs: array<int, array<string, mixed>>
     * }
     */
    private function handleIndividualQuoteConfirmation(
        ChatSession $session,
        string $intent,
        string $message,
        array $metadata,
    ): array {
        if (! $this->intentSlotFiller->isConfirmation($message)) {
            $assistantReply = 'Responde si para generar la cotización o indica los datos que deseas corregir.';

            return $this->finalizeIndividualQuoteWorkflowReply(
                $session,
                $intent,
                $metadata,
                $assistantReply,
                AgentConversationStateMachine::STATE_CONFIRMACION,
            );
        }

        /** @var array<string, string> $contact */
        $contact = is_array($metadata['quote_contact'] ?? null) ? $metadata['quote_contact'] : [];

        /** @var list<array<string, mixed>> $entries */
        $entries = is_array($metadata['individual_quote_entries'] ?? null) ? $metadata['individual_quote_entries'] : [];

        $registration = $this->chatIndividualQuoteService->register([
            'full_name' => (string) ($contact['full_name'] ?? ''),
            'agent_name' => (string) ($contact['agent_name'] ?? ''),
            'entries' => collect($entries)->map(fn (array $entry): array => [
                'plan_id' => (int) ($entry['plan_id'] ?? 0),
                'age' => array_key_exists('age', $entry) ? $entry['age'] : null,
                'age_range_id' => (int) ($entry['age_range_id'] ?? 0),
                'total_persons' => (int) ($entry['total_persons'] ?? 0),
            ])->values()->all(),
        ]);

        if (($registration['success'] ?? false) !== true) {
            $assistantReply = (string) ($registration['message'] ?? 'No pudimos generar la cotización.');

            return $this->finalizeIndividualQuoteWorkflowReply($session, $intent, $metadata, $assistantReply);
        }

        /** @var array<string, mixed> $data */
        $data = is_array($registration['data'] ?? null) ? $registration['data'] : [];
        $assistantReply = $this->intentSlotFiller->individualQuoteSuccessMessage((string) ($data['code'] ?? 'N/D'))
            ."\n\n"
            .$this->intentSlotFiller->chatAgentAnotherActionOfferMessage();

        $metadata['awaiting_quote_confirmation'] = false;
        $metadata['awaiting_individual_quote_action'] = false;
        $metadata['individual_quote_collecting'] = false;
        $metadata['awaiting_another_action_offer'] = true;
        $metadata['conversation_completed'] = true;
        $metadata['last_tool_result'] = [
            'tool' => 'register_chat_individual_quote',
            'result' => $registration,
        ];

        return $this->finalizeIndividualQuoteWorkflowReply(
            $session,
            $intent,
            $metadata,
            $assistantReply,
            AgentConversationStateMachine::STATE_CONFIRMACION,
            [[
                'tool' => 'register_chat_individual_quote',
                'arguments' => $contact,
                'result' => $registration,
            ]],
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<int, array<string, mixed>>  $toolRuns
     * @return array{
     *   reply: string,
     *   intent: string|null,
     *   state: string,
     *   handoff_requested: bool,
     *   tool_runs: array<int, array<string, mixed>>
     * }
     */
    private function finalizeIndividualQuoteWorkflowReply(
        ChatSession $session,
        string $intent,
        array $metadata,
        string $assistantReply,
        string $state = AgentConversationStateMachine::STATE_RECOLECCION_DATOS,
        array $toolRuns = [],
    ): array {
        $this->storeMessage($session, [
            'role' => 'assistant',
            'content' => $assistantReply,
            'metadata' => ['intent' => $intent, 'individual_quote_flow' => true],
        ]);

        $session->metadata = $metadata;
        $session->context_summary = $this->summarizeLatestConversation($session);
        $session->current_state = $state;
        $session->last_message_at = now();
        $session->save();

        return [
            'reply' => $assistantReply,
            'intent' => $session->detected_intent,
            'state' => (string) $session->current_state,
            'handoff_requested' => (bool) $session->handoff_requested,
            'tool_runs' => $toolRuns,
        ];
    }
}
