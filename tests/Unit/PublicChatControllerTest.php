<?php

declare(strict_types=1);

use App\Http\Controllers\PublicChatController;
use App\Models\ChatSession;
use App\Services\PublicAiAgent\AgentOrchestrator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

afterEach(function (): void {
    \Mockery::close();
});

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

it('crea una sesión pública de chat', function (): void {
    $controller = app(PublicChatController::class);
    $request = Request::create('/api/public-chat/session', 'POST');

    $response = $controller->session($request);
    $payload = $response->getData(true);

    expect($response->getStatusCode())->toBe(200)
        ->and($payload)->toHaveKeys([
            'session_token',
            'state',
            'intent',
            'handoff_requested',
        ]);

    expect(ChatSession::query()->count('*'))->toBe(1);
});

it('responde un mensaje usando el orquestador', function (): void {
    $controller = app(PublicChatController::class);
    $mock = \Mockery::mock(AgentOrchestrator::class);
    $mock->shouldReceive('processUserMessage')
        ->once()
        ->andReturn([
            'reply' => 'Perfecto, iniciemos con tu cotización.',
            'intent' => 'cotizacion_planes_salud',
            'state' => 'recoleccion_datos',
            'handoff_requested' => false,
            'tool_runs' => [],
        ]);

    $this->app->instance(AgentOrchestrator::class, $mock);

    $request = Request::create('/api/public-chat/message', 'POST', [
        'message' => 'Hola, quiero cotizar.',
    ]);
    $response = $controller->message($request, $mock);
    $payload = $response->getData(true);

    expect($response->getStatusCode())->toBe(200)
        ->and($payload['reply'])->toBe('Perfecto, iniciemos con tu cotización.')
        ->and($payload['intent'])->toBe('cotizacion_planes_salud')
        ->and($payload['state'])->toBe('recoleccion_datos');
});
