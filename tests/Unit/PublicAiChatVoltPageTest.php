<?php

declare(strict_types=1);

use App\Services\PublicAiAgent\AgentConversationStateMachine;
use App\Services\PublicAiAgent\AgentOrchestrator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Volt\Volt;

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

it('renderiza la vista pública del chat IA', function (): void {
    Volt::test('volt.public.ai_chat')
        ->assertSee('GU')
        ->assertSee('IA')
        ->assertSee('-CHAT')
        ->assertSee('Quiero!')
        ->assertSee('Nuestros Planes')
        ->assertDontSee('Cotización plan individual')
        ->assertSee('Registro de Agente')
        ->assertSee('Pregunta lo que necesites...')
        ->assertSeeHtml('textarea');
});

it('responde con contacto de asesores comerciales al escribir ayuda sin seleccionar accion', function (): void {
    $component = Volt::test('volt.public.ai_chat')
        ->set('draft', 'ayuda')
        ->call('sendMessage')
        ->assertSet('chatFeed.0.content', 'ayuda')
        ->assertSet('chatFeed.0.role', 'user')
        ->assertSet('chatFeed.1.role', 'assistant');

    $helpReply = $component->get('chatFeed')[1]['content'];

    expect($helpReply)
        ->toContain('Asesores Comerciales')
        ->toContain('https://wa.me/584127018390')
        ->toContain('0412 701 8390');
});

it('envía automáticamente la acción seleccionada y muestra respuesta del asistente', function (): void {
    $this->mock(AgentOrchestrator::class, function ($mock): void {
        $mock->shouldReceive('processUserMessage')
            ->once()
            ->andReturn([
                'reply' => 'Respuesta automática de prueba para registro de agente.',
                'intent' => AgentConversationStateMachine::INTENT_PREREGISTRO,
                'state' => AgentConversationStateMachine::STATE_RECOLECCION_DATOS,
                'handoff_requested' => false,
                'tool_runs' => [],
            ]);
    });

    Volt::test('volt.public.ai_chat')
        ->call('selectAction', 'registro_agente')
        ->assertSet('selectedAction', 'registro_agente')
        ->assertSee('Seleccioné: Registro de Agente')
        ->assertSet('chatFeed.1.content', 'Respuesta automática de prueba para registro de agente.')
        ->assertSet('chatFeed.1.animate', true);
});

it('reinicia el chat y limpia el historial visible', function (): void {
    Volt::test('volt.public.ai_chat')
        ->set('chatFeed', [
            ['id' => 'user-1', 'role' => 'user', 'content' => 'Hola'],
            ['id' => 'assistant-1', 'role' => 'assistant', 'content' => 'Respuesta previa', 'animate' => false],
        ])
        ->set('selectedAction', 'registro_agente')
        ->set('conversationState', 'recoleccion_datos')
        ->call('restartChat')
        ->assertSet('chatFeed', [])
        ->assertSet('selectedAction', '')
        ->assertSet('conversationState', 'saludo')
        ->assertSet('handoffRequested', false);
});

it('resalta terminos destacados en mensajes del chat publico', function (): void {
    Volt::test('volt.public.ai_chat')
        ->tap(function ($component): void {
            $formatted = $component->instance()->formatChatMessage('Escribe **ayuda** o pulsa **Quiero!** para continuar.');

            expect($formatted)
                ->toContain('<span class="font-semibold text-[1.1em] leading-snug text-emerald-200">ayuda</span>')
                ->toContain('<span class="font-semibold text-[1.1em] leading-snug text-emerald-200">Quiero!</span>');
        });
});

it('convierte enlaces markdown de whatsapp en links clicables', function (): void {
    Volt::test('volt.public.ai_chat')
        ->set('chatFeed', [
            [
                'id' => 'assistant-1',
                'role' => 'assistant',
                'content' => 'Contacto: [+58 412 701 8390](https://wa.me/584127018390)',
                'animate' => false,
            ],
        ])
        ->assertSeeHtml('href="https://wa.me/584127018390"')
        ->assertSeeHtml('target="_blank"')
        ->assertSee('+58 412 701 8390');
});
