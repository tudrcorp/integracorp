<?php

declare(strict_types=1);

use App\Support\AffiliateVaucherIlsRemainingDays;
use Carbon\Carbon;

uses(Tests\TestCase::class);

it('devuelve null si dateEnd está vacío', function (): void {
    expect(AffiliateVaucherIlsRemainingDays::remainingDaysUntilEnd(null))->toBeNull()
        ->and(AffiliateVaucherIlsRemainingDays::remainingDaysUntilEnd(''))->toBeNull();
});

it('devuelve 0 si la fecha fin ya pasó', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-03-26 10:00:00'));

    expect(AffiliateVaucherIlsRemainingDays::remainingDaysUntilEnd('01-01-2020'))->toBe(0)
        ->and(AffiliateVaucherIlsRemainingDays::remainingDaysUntilEnd('01/01/2020'))->toBe(0);

    Carbon::setTestNow();
});

it('calcula días hasta dateEnd desde hoy', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-03-26 08:00:00'));

    expect(AffiliateVaucherIlsRemainingDays::remainingDaysUntilEnd('30-03-2026'))->toBe(4)
        ->and(AffiliateVaucherIlsRemainingDays::remainingDaysUntilEnd('26/03/2026'))->toBe(0);

    Carbon::setTestNow();
});

it('acepta instancia Carbon como dateEnd', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-03-26 08:00:00'));

    $end = Carbon::parse('2026-03-30');

    expect(AffiliateVaucherIlsRemainingDays::remainingDaysUntilEnd($end))->toBe(4);

    Carbon::setTestNow();
});
