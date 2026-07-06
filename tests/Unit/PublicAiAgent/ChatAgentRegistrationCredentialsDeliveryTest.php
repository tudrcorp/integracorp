<?php

declare(strict_types=1);

use App\Jobs\SendChatAgentRegistrationWhatsAppJob;
use App\Models\ChatSession;
use App\Services\PublicAiAgent\AgentConversationStateMachine;
use App\Services\PublicAiAgent\AgentOrchestrator;
use App\Services\PublicAiAgent\ChatAgencyMasterRegistrationService;
use App\Services\PublicAiAgent\ChatAgentRegistrationService;
use App\Services\PublicAiAgent\IntentSlotFiller;
use Illuminate\Support\Facades\Bus;

uses(Tests\TestCase::class);

it('indica envio por whatsapp y correo tras registro exitoso', function (): void {
    $slotFiller = new IntentSlotFiller;

    $message = $slotFiller->chatAgentRegistrationDeliveredMessage('maria@test.invalid', '+584141234567');

    expect($message)
        ->toContain('WhatsApp')
        ->toContain('maria@test.invalid')
        ->toContain('+584141234567')
        ->toContain('correo')
        ->toContain('carta de bienvenida')
        ->toContain('Responde si')
        ->toContain('o no');
});

it('detecta respuestas negativas con no', function (): void {
    $slotFiller = new IntentSlotFiller;

    expect($slotFiller->isRejection('no'))->toBeTrue()
        ->and($slotFiller->isRejection('n'))->toBeTrue()
        ->and($slotFiller->isRejection('si'))->toBeFalse();
});

it('construye caption de whatsapp con datos de acceso', function (): void {
    $service = new ChatAgentRegistrationService;

    $caption = $service->buildRegistrationWhatsAppCaption([
        'email' => 'agente@test.invalid',
        'password' => 'Secreta123',
        'code_agent' => 'AGT-00099',
        'login_url' => 'https://portal.test/login',
    ]);

    expect($caption)
        ->toContain('agente@test.invalid')
        ->toContain('Secreta123')
        ->toContain('AGT-00099')
        ->toContain('https://portal.test/login')
        ->toContain('carta de bienvenida');
});

it('construye cuerpo de mensaje whatsapp con datos de acceso', function (): void {
    $service = new ChatAgentRegistrationService;

    $body = $service->buildRegistrationWhatsAppChatBody([
        'email' => 'agente@test.invalid',
        'password' => 'Secreta123',
        'code_agent' => 'AGT-00099',
        'login_url' => 'https://portal.test/login',
    ]);

    expect($body)
        ->toContain('agente@test.invalid')
        ->toContain('Secreta123')
        ->toContain('AGT-00099')
        ->toContain('https://portal.test/login');
});

it('al confirmar recepcion ofrece otra accion', function (): void {
    $registrationService = Mockery::mock(ChatAgentRegistrationService::class);
    $registrationService->shouldNotReceive('queueRegistrationPackageViaWhatsApp');

    $orchestrator = new AgentOrchestrator(
        stateMachine: new AgentConversationStateMachine,
        intentSlotFiller: new IntentSlotFiller,
        prospectAgentRegistrationService: app(\App\Services\PublicAiAgent\ProspectAgentRegistrationService::class),
        publicQuoteSimulationService: app(\App\Services\PublicAiAgent\PublicQuoteSimulationService::class),
        registrationValidationService: new \App\Services\PublicAiAgent\PublicAgentRegistrationValidationService,
        chatAgentRegistrationService: $registrationService,
        chatAgencyMasterRegistrationService: new ChatAgencyMasterRegistrationService,
        chatAgencyGeneralRegistrationService: new \App\Services\PublicAiAgent\ChatAgencyGeneralRegistrationService,
        publicPlanCatalogService: new \App\Services\PublicAiAgent\PublicPlanCatalogService,
    );

    $session = ChatSession::startPublic('127.0.0.1', 'Pest');
    $session->detected_intent = AgentConversationStateMachine::INTENT_PREREGISTRO;
    $session->metadata = [
        'selected_action' => AgentConversationStateMachine::ACTION_REGISTER_AGENT,
        'agent_welcome_sent' => true,
        'awaiting_show_credentials' => true,
        'registration_credentials' => [
            'email' => 'maria@test.invalid',
            'phone' => '+584141234567',
            'agent_id' => 99,
            'name' => 'María Pérez',
            'password' => 'Secreta123',
            'code_agent' => 'AGT-00099',
            'login_url' => 'https://portal.test/login',
        ],
    ];
    $session->save();

    $result = $orchestrator->processUserMessage(
        $session,
        'si',
        AgentConversationStateMachine::ACTION_REGISTER_AGENT,
    );

    expect($result['reply'])
        ->toContain('recibido la información correctamente')
        ->toContain('¿Deseas realizar alguna otra acción');

    $session->refresh();
    expect($session->metadata['awaiting_another_action_offer'] ?? false)->toBeTrue();
});

it('al no recibir informacion ofrece entrega por chat', function (): void {
    $registrationService = Mockery::mock(ChatAgentRegistrationService::class);
    $registrationService->shouldNotReceive('queueRegistrationPackageViaWhatsApp');

    $orchestrator = new AgentOrchestrator(
        stateMachine: new AgentConversationStateMachine,
        intentSlotFiller: new IntentSlotFiller,
        prospectAgentRegistrationService: app(\App\Services\PublicAiAgent\ProspectAgentRegistrationService::class),
        publicQuoteSimulationService: app(\App\Services\PublicAiAgent\PublicQuoteSimulationService::class),
        registrationValidationService: new \App\Services\PublicAiAgent\PublicAgentRegistrationValidationService,
        chatAgentRegistrationService: $registrationService,
        chatAgencyMasterRegistrationService: new ChatAgencyMasterRegistrationService,
        chatAgencyGeneralRegistrationService: new \App\Services\PublicAiAgent\ChatAgencyGeneralRegistrationService,
        publicPlanCatalogService: new \App\Services\PublicAiAgent\PublicPlanCatalogService,
    );

    $session = ChatSession::startPublic('127.0.0.1', 'Pest');
    $session->detected_intent = AgentConversationStateMachine::INTENT_PREREGISTRO;
    $session->metadata = [
        'selected_action' => AgentConversationStateMachine::ACTION_REGISTER_AGENT,
        'agent_welcome_sent' => true,
        'awaiting_show_credentials' => true,
        'registration_credentials' => [
            'email' => 'maria@test.invalid',
            'phone' => '+584141234567',
            'agent_id' => 99,
            'name' => 'María Pérez',
            'password' => 'Secreta123',
            'code_agent' => 'AGT-00099',
            'login_url' => 'https://portal.test/login',
        ],
    ];
    $session->save();

    $result = $orchestrator->processUserMessage(
        $session,
        'no',
        AgentConversationStateMachine::ACTION_REGISTER_AGENT,
    );

    expect($result['reply'])
        ->toContain('aquí en el chat')
        ->toContain('carta de bienvenida')
        ->toContain('Responde si');

    $session->refresh();
    expect($session->metadata['awaiting_phone_credentials_offer'] ?? false)->toBeTrue();
});

it('al aceptar entrega por chat muestra credenciales y enlace de carta', function (): void {
    $registrationService = Mockery::mock(ChatAgentRegistrationService::class);
    $registrationService
        ->shouldReceive('enrichRegistrationCredentials')
        ->once()
        ->andReturnUsing(fn (array $credentials): array => $credentials);
    $registrationService
        ->shouldReceive('ensureWelcomeLetterPdf')
        ->once()
        ->with(99, 'María Pérez')
        ->andReturn('chat-agent-welcome/AGT-00099.pdf');
    $registrationService
        ->shouldReceive('publicStorageDocumentUrl')
        ->once()
        ->with('chat-agent-welcome/AGT-00099.pdf')
        ->andReturn('https://cdn.test/chat-agent-welcome/AGT-00099.pdf');
    $registrationService->shouldNotReceive('queueRegistrationPackageViaWhatsApp');

    $orchestrator = new AgentOrchestrator(
        stateMachine: new AgentConversationStateMachine,
        intentSlotFiller: new IntentSlotFiller,
        prospectAgentRegistrationService: app(\App\Services\PublicAiAgent\ProspectAgentRegistrationService::class),
        publicQuoteSimulationService: app(\App\Services\PublicAiAgent\PublicQuoteSimulationService::class),
        registrationValidationService: new \App\Services\PublicAiAgent\PublicAgentRegistrationValidationService,
        chatAgentRegistrationService: $registrationService,
        chatAgencyMasterRegistrationService: new ChatAgencyMasterRegistrationService,
        chatAgencyGeneralRegistrationService: new \App\Services\PublicAiAgent\ChatAgencyGeneralRegistrationService,
        publicPlanCatalogService: new \App\Services\PublicAiAgent\PublicPlanCatalogService,
    );

    $session = ChatSession::startPublic('127.0.0.1', 'Pest');
    $session->detected_intent = AgentConversationStateMachine::INTENT_PREREGISTRO;
    $session->metadata = [
        'selected_action' => AgentConversationStateMachine::ACTION_REGISTER_AGENT,
        'agent_welcome_sent' => true,
        'awaiting_phone_credentials_offer' => true,
        'registration_credentials' => [
            'email' => 'maria@test.invalid',
            'phone' => '+584141234567',
            'agent_id' => 99,
            'name' => 'María Pérez',
            'password' => 'Secreta123',
            'code_agent' => 'AGT-00099',
            'login_url' => 'https://portal.test/login',
        ],
    ];
    $session->save();

    $result = $orchestrator->processUserMessage(
        $session,
        'si',
        AgentConversationStateMachine::ACTION_REGISTER_AGENT,
    );

    expect($result['reply'])
        ->toContain('maria@test.invalid')
        ->toContain('Secreta123')
        ->toContain('AGT-00099')
        ->toContain('https://portal.test/login')
        ->toContain('[Carta de bienvenida](https://cdn.test/chat-agent-welcome/AGT-00099.pdf)')
        ->toContain('¿Deseas realizar alguna otra acción');
});

it('encola job de whatsapp con credenciales validas', function (): void {
    Bus::fake();

    $service = new ChatAgentRegistrationService;

    $queued = $service->queueRegistrationPackageViaWhatsApp([
        'email' => 'agente@test.invalid',
        'phone' => '+584141234567',
        'agent_id' => 99,
        'name' => 'María Pérez',
        'password' => 'Secreta123',
        'code_agent' => 'AGT-00099',
        'login_url' => 'https://portal.test/login',
    ]);

    expect($queued)->toBeTrue();

    Bus::assertDispatched(SendChatAgentRegistrationWhatsAppJob::class, function (SendChatAgentRegistrationWhatsAppJob $job): bool {
        return $job->credentials['email'] === 'agente@test.invalid'
            && $job->credentials['phone'] === '+584141234567'
            && $job->credentials['agent_id'] === 99;
    });
});
