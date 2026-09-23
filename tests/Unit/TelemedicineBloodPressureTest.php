<?php

declare(strict_types=1);

use App\Support\Telemedicine\TelemedicineBloodPressure;

it('muestra la presión arterial como sistólica/diastólica', function (mixed $value, string $expected): void {
    expect(TelemedicineBloodPressure::format($value))->toBe($expected);
})->with([
    'escrita con punto por el campo numérico' => ['110.70', '110/70 mmHg'],
    'decimal que perdió el cero final' => ['110.7', '110/70 mmHg'],
    'con coma' => ['120,80', '120/80 mmHg'],
    'con guion' => ['120-80', '120/80 mmHg'],
    'ya correcta con unidad' => ['128/82 mmHg', '128/82 mmHg'],
    'ya correcta sin unidad' => ['128/82', '128/82 mmHg'],
    'sin dato' => [null, '—'],
    'vacía' => ['  ', '—'],
    'diastólica mayor que sistólica: no se inventa' => ['80.120', '80.120'],
    'diastólica imposible: se deja como está' => ['150.10', '150.10'],
    'texto libre' => ['no se pudo tomar', 'no se pudo tomar'],
]);
