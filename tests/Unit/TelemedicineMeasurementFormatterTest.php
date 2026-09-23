<?php

declare(strict_types=1);

use App\Support\Telemedicine\TelemedicineMeasurementFormatter;

it('muestra las medidas con coma decimal y sin ceros de relleno', function (mixed $value, string $unit, int $decimals, string $expected): void {
    expect(TelemedicineMeasurementFormatter::format($value, $unit, $decimals))->toBe($expected);
})->with([
    'peso guardado con tres decimales' => ['71.200', 'kg', 2, '71,2 kg'],
    'peso entero' => ['70.000', 'kg', 2, '70 kg'],
    'estatura' => ['1.750', 'm', 2, '1,75 m'],
    'estatura con coma' => ['1,62', 'm', 2, '1,62 m'],
    'número' => [68, 'kg', 2, '68 kg'],
    'imc a un decimal' => ['25.94', '', 1, '25,9'],
    'sin dato no imprime la unidad' => [null, 'm', 2, '—'],
    'vacío' => ['   ', 'kg', 2, '—'],
    'texto libre se respeta' => ['no se pudo medir', 'kg', 2, 'no se pudo medir'],
]);
