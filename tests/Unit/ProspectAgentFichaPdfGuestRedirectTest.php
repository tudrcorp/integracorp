<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

it('redirige a login si un invitado intenta ver la ficha del prospecto', function (): void {
    $this->get(route('business.prospect-agents.ficha.preview', ['prospectAgent' => 1]))
        ->assertRedirect();
});

it('redirige a login si un invitado intenta descargar la ficha del prospecto', function (): void {
    $this->get(route('business.prospect-agents.ficha.download', ['prospectAgent' => 1]))
        ->assertRedirect();
});
