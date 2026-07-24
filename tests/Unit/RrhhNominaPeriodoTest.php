<?php

declare(strict_types=1);

use App\Support\Rrhh\RrhhNominaPeriodo;
use Illuminate\Support\Carbon;

it('genera 24 periodos por anio con dos quincenas por mes', function (): void {
    $options = RrhhNominaPeriodo::optionsForYear(2026);

    expect($options)->toHaveCount(24)
        ->and($options[1])->toBe('P01 · 01/01/2026 — 15/01/2026')
        ->and($options[2])->toBe('P02 · 16/01/2026 — 31/01/2026')
        ->and($options[3])->toBe('P03 · 01/02/2026 — 15/02/2026')
        ->and($options[4])->toBe('P04 · 16/02/2026 — 28/02/2026')
        ->and($options[24])->toBe('P24 · 16/12/2026 — 31/12/2026');
});

it('resuelve fechas del periodo seleccionado', function (): void {
    $periodo = RrhhNominaPeriodo::resolve(2026, 2);

    expect($periodo)
        ->toMatchArray([
            'anio' => 2026,
            'periodo' => 2,
            'quincena' => 2,
            'mes' => 1,
            'fecha_desde' => '2026-01-16',
            'fecha_hasta' => '2026-01-31',
        ]);
});

it('calcula la mitad del sueldo mensual para el periodo', function (): void {
    expect(RrhhNominaPeriodo::sueldoDelPeriodo(100))->toBe(50.0)
        ->and(RrhhNominaPeriodo::sueldoDelPeriodo(99.99))->toBe(50.0)
        ->and(RrhhNominaPeriodo::sueldoDelPeriodo(0))->toBe(0.0);
});

it('detecta el periodo actual segun la fecha', function (): void {
    expect(RrhhNominaPeriodo::currentPeriodNumber(Carbon::parse('2026-01-10')))->toBe(1)
        ->and(RrhhNominaPeriodo::currentPeriodNumber(Carbon::parse('2026-01-20')))->toBe(2)
        ->and(RrhhNominaPeriodo::currentPeriodNumber(Carbon::parse('2026-07-01')))->toBe(13)
        ->and(RrhhNominaPeriodo::currentPeriodNumber(Carbon::parse('2026-07-16')))->toBe(14);
});
