<?php

declare(strict_types=1);

use App\Support\AffiliationAffiliateFeeCalculator;
use Carbon\Carbon;

it('calcula fecha de renovación sumando un año a effective_date', function (): void {
    $calculator = new AffiliationAffiliateFeeCalculator;

    $renewal = $calculator->renewalDateFromEffectiveDate('20/02/2026');

    expect($renewal)->not->toBeNull()
        ->and($renewal->format('d/m/Y'))->toBe('20/02/2027');
});

it('calcula días restantes hasta la renovación', function (): void {
    $calculator = new AffiliationAffiliateFeeCalculator;
    $today = Carbon::createFromFormat('d/m/Y', '20/01/2027');

    $days = $calculator->daysUntilRenewal('20/02/2026', $today);

    expect($days)->toBe(31);
});

it('devuelve días negativos cuando la renovación ya venció', function (): void {
    $calculator = new AffiliationAffiliateFeeCalculator;
    $today = Carbon::createFromFormat('d/m/Y', '21/05/2027');

    $days = $calculator->daysUntilRenewal('21/05/2025', $today);

    expect($days)->toBe(-365);
});

it('usa diffInDays con signo para permitir retraso', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Support/AffiliationAffiliateFeeCalculator.php');

    expect($source)->toContain('diffInDays($renewalDate, absolute: false)');
});

it('entra en período de renovación cuando faltan 30 días o menos', function (): void {
    $calculator = new AffiliationAffiliateFeeCalculator;
    $today = Carbon::createFromFormat('d/m/Y', '21/04/2026');

    $days = $calculator->daysUntilRenewal('21/05/2025', $today);

    expect($days)->toBe(30)
        ->and($days)->toBeLessThanOrEqual(30);
});

it('permanece vigente cuando faltan más de 30 días', function (): void {
    $calculator = new AffiliationAffiliateFeeCalculator;
    $today = Carbon::createFromFormat('d/m/Y', '01/01/2026');

    $days = $calculator->daysUntilRenewal('21/05/2025', $today);

    expect($days)->toBeGreaterThan(30);
});

it('calcula montos por frecuencia de pago', function (): void {
    $calculator = new AffiliationAffiliateFeeCalculator;

    expect($calculator->totalAmountForPaymentFrequency(1200.0, 'ANUAL'))->toBe(1200.0)
        ->and($calculator->totalAmountForPaymentFrequency(1200.0, 'SEMESTRAL'))->toBe(600.0)
        ->and($calculator->totalAmountForPaymentFrequency(1200.0, 'TRIMESTRAL'))->toBe(300.0);
});
