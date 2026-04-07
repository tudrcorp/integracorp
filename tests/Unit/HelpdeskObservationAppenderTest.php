<?php

declare(strict_types=1);

use App\Support\HelpdeskObservationAppender;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    Config::set('app.timezone', 'America/Caracas');
    Carbon::setTestNow(Carbon::parse('2026-03-27 14:30:00', 'America/Caracas'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('mergeObservation crea el primer bloque con encabezado', function () {
    $out = HelpdeskObservationAppender::mergeObservation('', 'Primera nota', 'Ana');

    expect($out)->toContain('[27/03/2026 14:30 · Ana]')
        ->and($out)->toContain('Primera nota');
});

it('mergeObservation concatena sin perder el historial', function () {
    $first = HelpdeskObservationAppender::mergeObservation('', 'Uno', 'Ana');
    $second = HelpdeskObservationAppender::mergeObservation($first, 'Dos', 'Pedro');

    expect($second)->toContain('Uno')
        ->and($second)->toContain('Dos')
        ->and($second)->toContain('[27/03/2026 14:30 · Pedro]');
});

it('mergeObservation ignora nota vacía', function () {
    $base = 'texto previo';
    expect(HelpdeskObservationAppender::mergeObservation($base, '   ', 'Ana'))->toBe($base);
});
