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

it('administration calcula tasa BCV en comprobante individual y bulk action', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Affiliations/Tables/AffiliationsTable.php');

    expect($source)
        ->toContain('AffiliationPaymentBcvRateCalculator::rateFromVesAndUsdTotal')
        ->toContain('syncPaymentBcvRateFromVesAmount')
        ->toContain('syncPaymentBcvRateFromUsdPart')
        ->toContain('Monto recibido en VES')
        ->toContain("BulkAction::make('pay_multiple_affiliations')")
        ->toContain("->label('Tasa BCV (calculada)')")
        ->toMatch('/TextInput::make\(\'tasa_bcv\'\)[\s\S]*?->disabled\(\)/')
        ->not->toContain('applyOfficialBcvRate');

    $bulkSection = (string) strstr($source, "BulkAction::make('pay_multiple_affiliations')");

    expect($bulkSection)
        ->toContain('syncPaymentBcvRateFromTotal($get, $set, $state)')
        ->toContain('syncPaymentBcvRateFromVesAmount($get, $set, $state)')
        ->toContain('syncPaymentBcvRateFromUsdPart($get, $set, $state)')
        ->toContain('self::bcvRateTextInput()');
});

it('administration corporativa calcula tasa BCV desde monto VES en comprobante de pago', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php');

    expect($source)
        ->toContain('AffiliationPaymentBcvRateCalculator::rateFromVesAndUsdTotal')
        ->toContain('syncPaymentBcvRateFromVesAmount')
        ->toContain('syncPaymentBcvRateFromUsdPart')
        ->toContain('Monto recibido en VES')
        ->toContain("->label('Tasa BCV (calculada)')")
        ->toMatch('/TextInput::make\(\'tasa_bcv\'\)[\s\S]*?->disabled\(\)/')
        ->not->toContain('$set(\'pay_amount_ves\', $state * $get(\'total_amount\'))')
        ->not->toContain('$get(\'tasa_bcv\') > 0');
});
