<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

it('invitado es redirigido al intentar vista previa de ficha de proveedor', function (): void {
    $this->get(route('operations.suppliers.ficha.preview', ['supplier' => 1]))
        ->assertRedirect();
});
