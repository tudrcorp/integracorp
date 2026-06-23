<?php

declare(strict_types=1);

use App\Models\ChatSession;
use App\Services\PublicAiAgent\AgentConversationStateMachine;
use App\Services\PublicAiAgent\AgentOrchestrator;
use App\Services\PublicAiAgent\IntentSlotFiller;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    Schema::dropIfExists('chat_messages');
    Schema::dropIfExists('chat_sessions');

    Schema::create('chat_sessions', function (Blueprint $table): void {
        $table->id();
        $table->string('public_token', 80)->unique();
        $table->string('status')->default('active');
        $table->string('current_state')->default('saludo');
        $table->string('detected_intent')->nullable();
        $table->boolean('handoff_requested')->default(false);
        $table->text('handoff_reason')->nullable();
        $table->text('context_summary')->nullable();
        $table->string('ip_address', 45)->nullable();
        $table->text('user_agent')->nullable();
        $table->json('metadata')->nullable();
        $table->timestamp('last_message_at')->nullable();
        $table->timestamps();
    });

    Schema::create('chat_messages', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('chat_session_id');
        $table->string('role', 20);
        $table->longText('content')->nullable();
        $table->string('tool_name')->nullable();
        $table->string('tool_call_id')->nullable();
        $table->json('tool_arguments')->nullable();
        $table->json('tool_result')->nullable();
        $table->string('model')->nullable();
        $table->unsignedInteger('prompt_tokens')->nullable();
        $table->unsignedInteger('completion_tokens')->nullable();
        $table->unsignedInteger('total_tokens')->nullable();
        $table->json('metadata')->nullable();
        $table->timestamps();
    });
});

it('incluye oferta de otra accion en mensaje de whatsapp enviado', function (): void {
    $slotFiller = new IntentSlotFiller;

    expect($slotFiller->chatAgentRegistrationCredentialsViaWhatsAppSentMessage('+584141234567'))
        ->toContain('Te enviamos por WhatsApp')
        ->toContain('¿Deseas realizar alguna otra acción');
});

it('reinicia el chat y abre acciones cuando el usuario acepta otra accion', function (): void {
    $orchestrator = app(AgentOrchestrator::class);
    $session = ChatSession::startPublic('127.0.0.1', 'Pest');
    $session->detected_intent = AgentConversationStateMachine::INTENT_PREREGISTRO;
    $session->metadata = [
        'selected_action' => AgentConversationStateMachine::ACTION_REGISTER_AGENT,
        'awaiting_another_action_offer' => true,
    ];
    $session->save();

    $oldToken = $session->public_token;

    $result = $orchestrator->processUserMessage($session, 'si', AgentConversationStateMachine::ACTION_REGISTER_AGENT);

    expect($result['open_action_menu'] ?? false)->toBeTrue()
        ->and($result['new_session_token'] ?? '')->not->toBe('')
        ->and($result['new_session_token'])->not->toBe($oldToken)
        ->and($result['reply'])->toContain('Reiniciamos la conversación')
        ->and($session->refresh()->status)->toBe('closed');
});

it('reinicia el chat y abre acciones cuando el usuario acepta otra accion tras cotizacion individual', function (): void {
    $orchestrator = app(AgentOrchestrator::class);
    $session = ChatSession::startPublic('127.0.0.1', 'Pest');
    $session->detected_intent = AgentConversationStateMachine::INTENT_COTIZACION;
    $session->metadata = [
        'selected_action' => AgentConversationStateMachine::ACTION_QUOTE_INDIVIDUAL,
        'awaiting_another_action_offer' => true,
    ];
    $session->save();

    $oldToken = $session->public_token;

    $result = $orchestrator->processUserMessage($session, 'si', AgentConversationStateMachine::ACTION_QUOTE_INDIVIDUAL);

    expect($result['open_action_menu'] ?? false)->toBeTrue()
        ->and($result['new_session_token'] ?? '')->not->toBe('')
        ->and($result['new_session_token'])->not->toBe($oldToken)
        ->and($result['reply'])->toContain('Reiniciamos la conversación')
        ->and($session->refresh()->status)->toBe('closed');
});

it('se despide cuando el usuario no desea otra accion', function (): void {
    $orchestrator = app(AgentOrchestrator::class);
    $session = ChatSession::startPublic('127.0.0.1', 'Pest');
    $session->detected_intent = AgentConversationStateMachine::INTENT_PREREGISTRO;
    $session->metadata = [
        'selected_action' => AgentConversationStateMachine::ACTION_REGISTER_AGENT,
        'awaiting_another_action_offer' => true,
    ];
    $session->save();

    $result = $orchestrator->processUserMessage($session, 'no', AgentConversationStateMachine::ACTION_REGISTER_AGENT);

    expect($result['reply'])->toContain('Puedes volver cuando gustes')
        ->and($result['reply'])->toContain('wa.me/584127018390')
        ->and($result['reply'])->toContain('0412 701 8390')
        ->and($result['reply'])->toContain('asesores de negocios');

    $session->refresh();
    expect($session->metadata['conversation_completed'] ?? false)->toBeTrue();
});

it('inicia flujo de cotizacion al seleccionar nueva accion despues de despedida', function (): void {
    $orchestrator = app(AgentOrchestrator::class);
    $session = ChatSession::startPublic('127.0.0.1', 'Pest');
    $session->detected_intent = AgentConversationStateMachine::INTENT_PREREGISTRO;
    $session->metadata = [
        'selected_action' => AgentConversationStateMachine::ACTION_REGISTER_AGENT,
        'conversation_completed' => true,
        'agent_welcome_sent' => true,
        'awaiting_confirmation' => true,
        'intent_payload' => [
            'name' => 'María Pérez',
            'email' => 'maria33@correo.com',
            'phone_1' => '04141234567',
            'classification' => 'agent',
            'agency_name' => 'Agencia Master Ejemplo',
        ],
    ];
    $session->save();

    $result = $orchestrator->processUserMessage(
        $session,
        'Seleccioné: Cotización plan individual',
        AgentConversationStateMachine::ACTION_QUOTE_INDIVIDUAL,
    );

    expect($result['intent'])->toBe(AgentConversationStateMachine::INTENT_COTIZACION)
        ->and($result['reply'])->toContain('¿Qué plan deseas cotizar?')
        ->and($result['reply'])->not->toContain('María Pérez')
        ->and($result['reply'])->not->toContain('Voy a crear el preregistro');
});

it('inicia nueva accion al seleccionarla mientras ofrece otra accion sin responder si o no', function (): void {
    $orchestrator = app(AgentOrchestrator::class);
    $session = ChatSession::startPublic('127.0.0.1', 'Pest');
    $session->detected_intent = AgentConversationStateMachine::INTENT_PREREGISTRO;
    $session->metadata = [
        'selected_action' => AgentConversationStateMachine::ACTION_REGISTER_AGENT,
        'awaiting_another_action_offer' => true,
        'agent_welcome_sent' => true,
        'intent_payload' => [
            'name' => 'María Pérez',
            'email' => 'maria33@correo.com',
            'phone_1' => '04141234567',
            'classification' => 'agent',
            'agency_name' => 'Agencia Master Ejemplo',
        ],
    ];
    $session->save();

    $result = $orchestrator->processUserMessage(
        $session,
        'Seleccioné: Cotización plan individual',
        AgentConversationStateMachine::ACTION_QUOTE_INDIVIDUAL,
    );

    expect($result['intent'])->toBe(AgentConversationStateMachine::INTENT_COTIZACION)
        ->and($result['reply'])->toContain('¿Qué plan deseas cotizar?')
        ->and($result['reply'])->not->toContain('Voy a crear el preregistro');
});

it('se despide y ofrece otra accion al declinar entrega por chat', function (): void {
    $registrationService = Mockery::mock(\App\Services\PublicAiAgent\ChatAgentRegistrationService::class);
    $registrationService->shouldNotReceive('queueRegistrationPackageViaWhatsApp');

    $orchestrator = new AgentOrchestrator(
        stateMachine: new AgentConversationStateMachine,
        intentSlotFiller: new IntentSlotFiller,
        prospectAgentRegistrationService: app(\App\Services\PublicAiAgent\ProspectAgentRegistrationService::class),
        publicQuoteSimulationService: app(\App\Services\PublicAiAgent\PublicQuoteSimulationService::class),
        registrationValidationService: new \App\Services\PublicAiAgent\PublicAgentRegistrationValidationService,
        chatAgentRegistrationService: $registrationService,
        chatAgencyMasterRegistrationService: new \App\Services\PublicAiAgent\ChatAgencyMasterRegistrationService,
        chatAgencyGeneralRegistrationService: new \App\Services\PublicAiAgent\ChatAgencyGeneralRegistrationService,
        publicPlanCatalogService: new \App\Services\PublicAiAgent\PublicPlanCatalogService,
    );

    $session = ChatSession::startPublic('127.0.0.1', 'Pest');
    $session->detected_intent = AgentConversationStateMachine::INTENT_PREREGISTRO;
    $session->metadata = [
        'selected_action' => AgentConversationStateMachine::ACTION_REGISTER_AGENT,
        'awaiting_phone_credentials_offer' => true,
        'registration_credentials' => [
            'email' => 'maria@test.invalid',
            'phone' => '+584141234567',
        ],
    ];
    $session->save();

    $declined = $orchestrator->processUserMessage(
        $session,
        'no',
        AgentConversationStateMachine::ACTION_REGISTER_AGENT,
    );

    expect($declined['reply'])
        ->toContain('Esperamos que puedas recibir la información pronto')
        ->toContain('¿Deseas realizar alguna otra acción');

    $session->refresh();
    expect($session->metadata['awaiting_another_action_offer'] ?? false)->toBeTrue();

    $oldToken = $session->public_token;

    $restart = $orchestrator->processUserMessage(
        $session,
        'si',
        AgentConversationStateMachine::ACTION_REGISTER_AGENT,
    );

    expect($restart['open_action_menu'] ?? false)->toBeTrue()
        ->and($restart['new_session_token'] ?? '')->not->toBe($oldToken)
        ->and($restart['reply'])->toContain('Reiniciamos la conversación');
});
