<?php

declare(strict_types=1);

use App\Jobs\SendCartaBienvenidaAgenteAgenciaTwo;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

it('responde 200 cuando el registro de agencia general es exitoso via api interna', function (): void {
    if (! Schema::hasTable('agencies') || ! Schema::hasTable('users')) {
        $this->markTestSkipped('Tablas agencies/users no disponibles.');
    }

    Bus::fake();

    $email = 'chat-agency-general-api-'.uniqid().'@test.invalid';
    $taxId = 'J'.random_int(100000000, 999999999);

    $response = $this->withoutMiddleware()
        ->postJson(route('api.internal.chat.agency-general-registration'), [
            'name_corporative' => 'Agencia General API Test',
            'tax_id' => $taxId,
            'email' => $email,
            'phone' => '04127018390',
            'owner_code' => 'TDG-100',
        ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.email', $email)
        ->assertJsonStructure(['data' => ['password', 'login_url', 'code_agency', 'owner_code']]);

    Bus::assertDispatched(SendCartaBienvenidaAgenteAgenciaTwo::class);

    $agency = Agency::query()->where('email', $email)->first();
    $user = User::query()->where('email', $email)->first();

    expect($agency)->not->toBeNull()
        ->and($agency->owner_code)->toBe('TDG-100')
        ->and((int) $agency->agency_type_id)->toBe(3)
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
