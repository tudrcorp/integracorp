<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

it('responde 422 cuando faltan datos en la api interna de cotizacion', function (): void {
    $response = $this->withoutMiddleware()
        ->postJson(route('api.internal.chat.individual-quote'), []);

    $response->assertStatus(422)
        ->assertJsonPath('success', false);
});
