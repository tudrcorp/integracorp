<?php

declare(strict_types=1);

use App\Models\ChatSession;
use App\Services\PublicAiAgent\AgentConversationStateMachine;
use App\Services\PublicAiAgent\AgentOrchestrator;
use App\Services\PublicAiAgent\IntentSlotFiller;
use App\Services\PublicAiAgent\PublicPlanBenefitsService;
use App\Services\PublicAiAgent\PublicPlanCatalogService;
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

it('construye resumen ordenado de nuestros planes con edades coberturas y beneficios', function (): void {
    $catalogService = new PublicPlanCatalogService;
    $benefitsService = new PublicPlanBenefitsService;

    $overview = $catalogService->buildOurPlansOverviewMessage($benefitsService, [
        [
            'plan_id' => 1,
            'description' => 'Plan Inicial',
            'coverages' => [],
            'age_ranges' => [],
        ],
        [
            'plan_id' => 2,
            'description' => 'Plan Ideal',
            'coverages' => [
                ['coverage_id' => 2, 'price' => 1000.0],
                ['coverage_id' => 6, 'price' => 10000.0],
            ],
            'age_ranges' => [],
        ],
        [
            'plan_id' => 3,
            'description' => 'Plan Especial',
            'coverages' => [
                ['coverage_id' => 10, 'price' => 50000.0],
            ],
            'age_ranges' => [],
        ],
    ]);

    expect($overview)
        ->toContain('VISTA RÁPIDA')
        ->toContain('1 · Plan Inicial')
        ->toContain('2 · Plan Ideal')
        ->toContain('3 · Plan Especial')
        ->toContain('PLAN 1 — PLAN INICIAL')
        ->toContain('Rango de edad')
        ->toContain('0 a +99 años (ilimitado)')
        ->toContain('Coberturas disponibles')
        ->toContain('Beneficios — Asistencia Médica en Sitio')
        ->toContain('Telemedicina')
        ->toContain('PLAN 2 — PLAN IDEAL')
        ->toContain('US$1.000')
        ->toContain('PLAN 3 — PLAN ESPECIAL')
        ->toContain('US$50.000')
        ->toContain('Asistencia Médica por Emergencias');
});

it('muestra informacion completa al seleccionar la accion nuestros planes', function (): void {
    $session = ChatSession::startPublic(ipAddress: '127.0.0.1', userAgent: 'test');

    $orchestrator = app(AgentOrchestrator::class);

    $result = $orchestrator->processUserMessage(
        $session,
        'Seleccioné: Nuestros Planes',
        AgentConversationStateMachine::ACTION_NUESTROS_PLANES,
    );

    expect($result['reply'])
        ->toContain('Nuestros Planes')
        ->toContain('VISTA RÁPIDA')
        ->toContain('Rango de edad')
        ->toContain('Coberturas disponibles')
        ->toContain('Beneficios —')
        ->toContain('¿Deseas realizar alguna otra acción');

    $session->refresh();

    expect($session->metadata['nuestros_planes_overview_shown'] ?? false)->toBeTrue()
        ->and($session->metadata['awaiting_another_action_offer'] ?? false)->toBeTrue()
        ->and($session->detected_intent)->toBe(AgentConversationStateMachine::INTENT_CONSULTA_PLANES);
});

it('amplia beneficios de un plan cuando el usuario lo solicita', function (): void {
    $session = ChatSession::startPublic(ipAddress: '127.0.0.1', userAgent: 'test');

    $session->metadata = [
        'selected_action' => AgentConversationStateMachine::ACTION_NUESTROS_PLANES,
        'nuestros_planes_overview_shown' => true,
        'awaiting_another_action_offer' => true,
    ];
    $session->detected_intent = AgentConversationStateMachine::INTENT_CONSULTA_PLANES;
    $session->save();

    $orchestrator = app(AgentOrchestrator::class);

    $result = $orchestrator->processUserMessage(
        $session->fresh(),
        '2 beneficios',
        AgentConversationStateMachine::ACTION_NUESTROS_PLANES,
    );

    expect($result['reply'])
        ->toContain('2 .- Plan Ideal')
        ->toContain('Asistencia Médica por Accidente')
        ->toContain('Consulta online o presencial');
});

it('registra nuestros planes como primera accion valida', function (): void {
    $stateMachine = new AgentConversationStateMachine;

    expect($stateMachine->actionKeys()[0])->toBe(AgentConversationStateMachine::ACTION_NUESTROS_PLANES)
        ->and($stateMachine->isOurPlansAction(AgentConversationStateMachine::ACTION_NUESTROS_PLANES))->toBeTrue()
        ->and($stateMachine->intentFromAction(AgentConversationStateMachine::ACTION_NUESTROS_PLANES))
        ->toBe(AgentConversationStateMachine::INTENT_CONSULTA_PLANES);
});

it('incluye mensaje de bienvenida orientado a nuestros planes', function (): void {
    $slotFiller = new IntentSlotFiller;
    $overview = (new PublicPlanCatalogService)->buildOurPlansOverviewMessage(new PublicPlanBenefitsService);

    $message = $slotFiller->ourPlansWelcomeMessage($overview);

    expect($message)
        ->toContain('**Nuestros Planes**')
        ->toContain('Tu Doctor en Casa')
        ->toContain('1 beneficios')
        ->toContain('**ayuda**');
});
