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

it('permite editar tasa BCV en comprobante y pago multiple de afiliaciones administration', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Affiliations/Tables/AffiliationsTable.php');

    expect($source)
        ->toContain('bcvRateTextInput')
        ->toContain('setCalculatedBcvRate')
        ->toContain('tasa_bcv_manual')
        ->not->toContain("->label('Tasa BCV (calculada)')")
        ->not->toMatch('/TextInput::make\(\'tasa_bcv\'\)[\s\S]*?->disabled\(\)/');
});
