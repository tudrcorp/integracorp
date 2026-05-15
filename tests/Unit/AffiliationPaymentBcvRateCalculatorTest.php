<?php

declare(strict_types=1);

use App\Support\AffiliationPaymentBcvRateCalculator;

it('calcula tasa BCV a partir de monto VES y total USD', function (): void {
    expect(AffiliationPaymentBcvRateCalculator::rateFromVesAndUsdTotal('3600', '100'))
        ->toBe('36');
});

it('devuelve null si falta monto VES o total USD válidos', function (): void {
    expect(AffiliationPaymentBcvRateCalculator::rateFromVesAndUsdTotal(null, '100'))->toBeNull()
        ->and(AffiliationPaymentBcvRateCalculator::rateFromVesAndUsdTotal('100', '0'))->toBeNull();
});

it('calcula tasa con saldo restante en USD (pago múltiple)', function (): void {
    expect(AffiliationPaymentBcvRateCalculator::rateFromVesAndRemainingUsd('1800', '50'))
        ->toBe('36');
});
