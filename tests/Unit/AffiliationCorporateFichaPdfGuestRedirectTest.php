<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

it('invitado es redirigido al intentar vista previa de ficha de afiliación corporativa', function (): void {
    $this->get(route('administration.affiliation-corporates.ficha.preview', ['affiliationCorporate' => 1]))
        ->assertRedirect();
});
