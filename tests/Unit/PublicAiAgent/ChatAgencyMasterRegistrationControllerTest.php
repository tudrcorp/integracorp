<?php

declare(strict_types=1);

use App\Jobs\SendCartaBienvenidaAgenteAgenciaTwo;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

it('responde 200 cuando el registro de agencia master es exitoso via api interna', function (): void {
    if (! Schema::hasTable('agencies') || ! Schema::hasTable('users')) {
        $this->markTestSkipped('Tablas agencies/users no disponibles.');
    }

    Bus::fake();

    $email = 'chat-agency-master-api-'.uniqid().'@test.invalid';

    $response = $this->withoutMiddleware()
        ->postJson(route('api.internal.chat.agency-master-registration'), [
            'name_corporative' => 'Agencia Master API Test',
            'tax_id' => 'J123456789',
            'email' => $email,
            'phone' => '04127018390',
        ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.email', $email)
        ->assertJsonStructure(['data' => ['password', 'login_url', 'code_agency']]);

    Bus::assertDispatched(SendCartaBienvenidaAgenteAgenciaTwo::class);

    $agency = Agency::query()->where('email', $email)->first();
    $user = User::query()->where('email', $email)->first();

    expect($agency)->not->toBeNull()
        ->and($agency->owner_code)->toBe($agency->code)
        ->and($agency->status)->toBe('ACTIVO')
        ->and($user)->not->toBeNull()
        ->and($user->status)->toBe('ACTIVO');

    if ($user !== null) {
        User::query()->where('id', $user->id)->delete();
    }

    if ($agency !== null) {
        Agency::query()->where('id', $agency->id)->delete();
    }
});

it('responde 422 cuando el correo ya existe en registro de agencia master via api', function (): void {
    if (! Schema::hasTable('users')) {
        $this->markTestSkipped('Tabla users no disponible.');
    }

    $email = 'chat-agency-master-dup-'.uniqid().'@test.invalid';

    User::query()->create([
        'name' => 'Usuario API',
        'email' => $email,
        'password' => bcrypt('password'),
    ]);

    $response = $this->withoutMiddleware()
        ->postJson(route('api.internal.chat.agency-master-registration'), [
            'name_corporative' => 'Agencia Master Dup',
            'tax_id' => 'V123456789',
            'email' => $email,
            'phone' => '04141234567',
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('success', false);

    User::query()->where('email', $email)->delete();
});
