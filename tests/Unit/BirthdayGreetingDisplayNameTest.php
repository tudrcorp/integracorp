<?php

declare(strict_types=1);

use App\Support\Birthday\BirthdayGreetingDisplayName;

it('devuelve vacío si el nombre viene vacío o nulo', function (): void {
    expect(BirthdayGreetingDisplayName::from(null))->toBe('')
        ->and(BirthdayGreetingDisplayName::from(''))->toBe('')
        ->and(BirthdayGreetingDisplayName::from('   '))->toBe('');
});

it('conserva un solo nombre o nombre y apellido', function (): void {
    expect(BirthdayGreetingDisplayName::from('DAPHNE'))->toBe('DAPHNE')
        ->and(BirthdayGreetingDisplayName::from('DAPHNE ACOSTA'))->toBe('DAPHNE ACOSTA');
});

it('toma primer nombre y primer apellido con dos apellidos', function (): void {
    expect(BirthdayGreetingDisplayName::from('DAPHNE ACOSTA DIAZ'))->toBe('DAPHNE ACOSTA');
});

it('omite el segundo nombre y conserva el primer apellido', function (): void {
    expect(BirthdayGreetingDisplayName::from('DAPHNE JOANNA ACOSTA DIAZ'))->toBe('DAPHNE ACOSTA')
        ->and(BirthdayGreetingDisplayName::from('  MARIA   DE   LOS   ANGELES  PEREZ  GONZALEZ '))
        ->toBe('MARIA PEREZ');
});

it('el controlador de animación usa el nombre corto del saludo', function (): void {
    $root = dirname(__DIR__, 2);
    $controller = file_get_contents($root.'/app/Http/Controllers/BirthdayAnimationAccessController.php');
    $cumpleAnim = file_get_contents($root.'/public/animacion-cumpleanos/js/cumple-anim.js');

    expect($controller)
        ->toContain('BirthdayGreetingDisplayName::from')
        ->and($cumpleAnim)->toContain('shortGreetingName')
        ->and($cumpleAnim)->toContain('parts[count - 2]');
});
