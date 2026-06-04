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

it('calcula edad de renovación desde fecha de nacimiento a la fecha de corrida', function (): void {
    $calculator = new AffiliationAffiliateFeeCalculator;
    $reference = Carbon::createFromFormat('d/m/Y', '04/06/2026');
    $affiliate = new \App\Models\Affiliate([
        'birth_date' => '1990-03-15',
        'age' => 25,
    ]);

    expect($calculator->resolveAffiliateAgeForRenewal($affiliate, $reference))->toBe(36);
});

it('usa edad almacenada en renovación solo si no hay fecha de nacimiento', function (): void {
    $calculator = new AffiliationAffiliateFeeCalculator;
    $reference = Carbon::createFromFormat('d/m/Y', '04/06/2026');
    $affiliate = new \App\Models\Affiliate([
        'age' => 42,
    ]);

    expect($calculator->resolveAffiliateAgeForRenewal($affiliate, $reference))->toBe(42);
});

it('calcula montos por frecuencia de pago', function (): void {
    $calculator = new AffiliationAffiliateFeeCalculator;

    expect($calculator->totalAmountForPaymentFrequency(1200.0, 'ANUAL'))->toBe(1200.0)
        ->and($calculator->totalAmountForPaymentFrequency(1200.0, 'SEMESTRAL'))->toBe(600.0)
        ->and($calculator->totalAmountForPaymentFrequency(1200.0, 'TRIMESTRAL'))->toBe(300.0);
});
