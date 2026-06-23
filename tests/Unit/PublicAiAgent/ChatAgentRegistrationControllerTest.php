<?php

declare(strict_types=1);

use App\Jobs\SendCartaBienvenidaAgenteAgencia;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

it('responde 200 cuando el registro es exitoso via api interna', function (): void {
    if (! Schema::hasTable('agents') || ! Schema::hasTable('users')) {
        $this->markTestSkipped('Tablas agents/users no disponibles.');
    }

    Bus::fake();

    $email = 'chat-api-http-'.uniqid().'@test.invalid';

    $response = $this->withoutMiddleware()
        ->postJson(route('api.internal.chat.agent-registration'), [
            'name' => 'Agente API Test',
            'email' => $email,
            'phone' => '04127018390',
            'owner_code' => 'TDG-101',
            'classification' => 'agent',
        ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.email', $email)
        ->assertJsonStructure(['data' => ['password', 'login_url', 'code_agent']]);

    Bus::assertDispatched(SendCartaBienvenidaAgenteAgencia::class);

    $user = User::query()->where('email', $email)->first();
    if ($user !== null && $user->agent_id) {
        User::query()->where('id', $user->id)->delete();
        \Illuminate\Support\Facades\DB::table('agents')->where('id', $user->agent_id)->delete();
    }
});

it('responde 422 cuando el correo ya existe via api interna', function (): void {
    if (! Schema::hasTable('users')) {
        $this->markTestSkipped('Tabla users no disponible.');
    }

    $email = 'chat-api-dup-'.uniqid().'@test.invalid';

    User::query()->create([
        'name' => 'Usuario API',
        'email' => $email,
        'password' => bcrypt('password'),
    ]);

    $response = $this->withoutMiddleware()
        ->postJson(route('api.internal.chat.agent-registration'), [
            'name' => 'Agente API Dup',
            'email' => $email,
            'phone' => '04141234567',
            'owner_code' => 'TDG-101',
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('success', false);

    User::query()->where('email', $email)->delete();
});
