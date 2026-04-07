<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

uses(Tests\TestCase::class);

it('registra las rutas async de aviso de cobro', function (): void {
    expect(Route::has('aviso-cobro.regenerate-async'))->toBeTrue()
        ->and(Route::has('aviso-cobro.send-email'))->toBeTrue();
});
