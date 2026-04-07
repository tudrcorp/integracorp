<?php

declare(strict_types=1);

use App\Support\FilamentDateDisplay;
use Carbon\Carbon;

it('formatea cadenas d/m/Y sin volver a parsear con Carbon', function () {
    expect(FilamentDateDisplay::toDmy('15/01/2017'))->toBe('15/01/2017');
});

it('formatea instancias Carbon a d/m/Y', function () {
    expect(FilamentDateDisplay::toDmy(Carbon::parse('2017-01-15')))->toBe('15/01/2017');
});

it('formatea fechas ISO en string', function () {
    expect(FilamentDateDisplay::toDmy('2017-01-15'))->toBe('15/01/2017');
});

it('devuelve null para vacío', function () {
    expect(FilamentDateDisplay::toDmy(null))->toBeNull()
        ->and(FilamentDateDisplay::toDmy(''))->toBeNull();
});
