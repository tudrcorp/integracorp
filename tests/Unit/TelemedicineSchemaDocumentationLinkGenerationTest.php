<?php

declare(strict_types=1);

use App\Models\User;

uses(Tests\TestCase::class);

test('solo usuario autenticado puede generar enlace temporal de documentacion', function () {
    $this->get(route('telemedicine.schema.documentation.link'))
        ->assertRedirect(route('login'));
});

test('usuario autenticado puede generar enlace temporal firmado', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('telemedicine.schema.documentation.link'));

    $response->assertSuccessful();
    $response->assertSee('URL temporal', false);
    $response->assertSee('Válida por 12 horas', false);
    $response->assertSee('/docs/telemedicina/esquema?expires=', false);
    $response->assertSee('&amp;signature=', false);
});
