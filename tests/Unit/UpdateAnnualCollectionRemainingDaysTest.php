<?php

declare(strict_types=1);

use App\Jobs\UpdateAnnualCollectionRemainingDays;
use App\Models\AnnualCollection;
use Carbon\Carbon;

afterEach(function (): void {
    Carbon::setTestNow();
});

it('elige el primer mes con flag true a partir del mes actual (cobro en junio)', function (): void {
    Carbon::setTestNow('2026-05-15');

    $job = new UpdateAnnualCollectionRemainingDays;
    $method = (new \ReflectionClass($job))->getMethod('resolveNextTrueMonth');
    $method->setAccessible(true);

    $record = new AnnualCollection;
    foreach (range(1, 12) as $m) {
        $record->{'month_'.$m} = $m === 6;
    }

    expect($method->invoke($job, $record, Carbon::today()))->toBe(6);
});

it('devuelve null si ningun mes esta en true', function (): void {
    Carbon::setTestNow('2026-05-15');

    $job = new UpdateAnnualCollectionRemainingDays;
    $method = (new \ReflectionClass($job))->getMethod('resolveNextTrueMonth');
    $method->setAccessible(true);

    $record = new AnnualCollection;
    foreach (range(1, 12) as $m) {
        $record->{'month_'.$m} = false;
    }

    expect($method->invoke($job, $record, Carbon::today()))->toBeNull();
});

it('calcula dias restantes hasta la fecha de pago del mes marcado en true', function (): void {
    Carbon::setTestNow('2026-05-15');

    $job = new UpdateAnnualCollectionRemainingDays;
    $method = (new \ReflectionClass($job))->getMethod('calculateRemainingDays');
    $method->setAccessible(true);

    $record = new AnnualCollection;
    $record->include_date = '15/05/2026';
    $record->payment_frequency = 'MENSUAL';
    foreach (range(1, 12) as $m) {
        $record->{'month_'.$m} = $m === 6;
    }

    expect($method->invoke($job, $record, Carbon::today()))->toBe(31);
});
