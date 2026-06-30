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

it('ubica el boton enviar dentro de la barra en mobile', function (): void {
    $volt = file_get_contents(base_path('resources/views/livewire/volt/public/ai_chat.blade.php'));

    expect($volt)
        ->toContain('Enviar (dentro de la barra en mobile para alinear márgenes)')
        ->toContain('class="hidden h-10 w-10 shrink-0 items-center justify-center rounded-full border border-white/15 bg-black/40 text-white shadow-lg backdrop-blur-md transition hover:bg-black/50 disabled:cursor-not-allowed disabled:opacity-40 sm:flex sm:h-11 sm:w-11"');
});

it('oculta los menus del composer al enfocar el textarea y los restaura al enviar', function (): void {
    $volt = file_get_contents(base_path('resources/views/livewire/volt/public/ai_chat.blade.php'));
    $actionMenu = file_get_contents(base_path('resources/views/pwa/guia-chat-action-menu.blade.php'));
    $serviceMenu = file_get_contents(base_path('resources/views/pwa/guia-chat-service-menu.blade.php'));
    $guiaChatUi = file_get_contents(base_path('resources/js/guia-chat-ui.js'));
    $typewriter = file_get_contents(base_path('resources/js/chat-typewriter.js'));

    expect($volt)
        ->toContain('hasDraftText: false')
        ->toContain('composerMenusVisible()')
        ->toContain('syncComposerChrome()')
        ->toContain('submitDraft()')
        ->toContain('guiaChatActionLabels')
        ->toContain('guiaChatBeginAction')
        ->toContain('guiaChatSelectAction')
        ->toContain('guiaChatSelectServiceOption')
        ->toContain('guiaChatCall')
        ->toContain('$wire.selectAction(actionKey)')
        ->toContain('@script')
        ->toContain('id="guia-chat-root"')
        ->toContain('chat-send-finished')
        ->toContain('$store.guiaChatUi.optimisticUserMessage')
        ->toContain('isTypingVisible()')
        ->toContain('awaitingReply')
        ->toContain('guia-chat-reply-visible')
        ->toContain('guia-chat-typing-indicator')
        ->toContain('x-on:focus="draftFocused = true; syncComposerChrome(); $dispatch(\'chat-composer-focus\')"')
        ->toContain('x-show="composerMenusVisible()"')
        ->toContain('releaseComposerChrome')
        ->toContain('chat-composer-release')
        ->and($volt)->toContain("chatTypewriter(@js(\$chatMessage['content']), @js(\$this->formatChatMessage(\$chatMessage['content'])), 'reply')");

    expect($guiaChatUi)
        ->toContain("Alpine.store('guiaChatUi'")
        ->toContain('beginSend(message = null)')
        ->toContain('endSend()');

    expect($typewriter)
        ->toContain("mode = 'welcome'")
        ->toContain("mode === 'reply'")
        ->toContain('REPLY_PREPARE_DELAY_MS')
        ->toContain('prepareAndReply()')
        ->toContain('preparing: mode === \'reply\'');

    expect($actionMenu)
        ->not->toContain('ring-1 ring-emerald-400/50')
        ->not->toContain('border border-white/30')
        ->toContain('x-on:chat-composer-focus.window="closeMenu()"')
        ->and($serviceMenu)->toContain('x-on:chat-composer-focus.window="closeMenu()"');

    $actionPanel = file_get_contents(base_path('resources/views/pwa/partials/guia-chat-action-menu-panel.blade.php'));

    expect($actionPanel)
        ->toContain('guiaChatSelectAction')
        ->not->toContain('wire:click="selectAction');

    $serviceMenu = file_get_contents(base_path('resources/views/pwa/guia-chat-service-menu.blade.php'));

    expect($serviceMenu)->toContain('guiaChatSelectServiceOption');
});

it('oculta el avatar del asistente en mobile', function (): void {
    $volt = file_get_contents(base_path('resources/views/livewire/volt/public/ai_chat.blade.php'));
    $head = file_get_contents(base_path('resources/views/partials/guia-chat-head.blade.php'));

    expect($volt)
        ->toContain('assistant-avatar.png')
        ->toContain('hidden h-9 w-9 shrink-0 rounded-full border border-white/25 bg-white object-cover shadow-md sm:block')
        ->and($volt)->toContain('guia-chat-composer-input')
        ->and($head)->toContain('.guia-chat-composer-input::-webkit-scrollbar');
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
        ->assertSee('¿Qué necesitas?...')
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
