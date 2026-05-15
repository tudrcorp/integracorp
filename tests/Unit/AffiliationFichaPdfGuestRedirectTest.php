<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

it('invitado es redirigido al intentar vista previa de ficha de afiliación individual', function (): void {
    $this->get(route('administration.affiliations.ficha.preview', ['affiliation' => 1]))
        ->assertRedirect();
});
