<?php

declare(strict_types=1);

use App\Support\WhiteCompanies\WhiteCompanyPaymentSettlement;

it('prorratea neta y precio de venta segun la frecuencia de pago', function (string $frequency, int $periods, float $neta, float $salePrice, float $partner): void {
    $settlement = new WhiteCompanyPaymentSettlement(
        annualSalePrice: 180,
        annualNeta: 96,
        paymentFrequency: $frequency,
        whiteCompanyId: 1,
        feeId: 10,
    );

    expect($settlement->periods())->toBe($periods)
        ->and(WhiteCompanyPaymentSettlement::periodsForFrequency($frequency))->toBe($periods)
        ->and($settlement->installmentNeta())->toBe($neta)
        ->and($settlement->installmentSalePrice())->toBe($salePrice)
        ->and($settlement->installmentPartner())->toBe($partner)
        ->and($settlement->installmentReportAmounts())->toBe([
            'sale_price' => $salePrice,
            'neta_tdg' => $neta,
            'neta_partner' => $partner,
        ]);
})->with([
    'anual' => ['ANUAL', 1, 96.0, 180.0, 84.0],
    'semestral' => ['SEMESTRAL', 2, 48.0, 90.0, 42.0],
    'trimestral' => ['TRIMESTRAL', 4, 24.0, 45.0, 21.0],
    'mensual' => ['MENSUAL', 12, 8.0, 15.0, 7.0],
]);

it('suma la neta de cada persona del plan inicial y la divide por la frecuencia', function (): void {
    $settlement = WhiteCompanyPaymentSettlement::fromPersonLines(
        [
            ['sale_price' => 180, 'neta' => 96, 'fee_id' => 1],
            ['sale_price' => 180, 'neta' => 96, 'fee_id' => 1],
        ],
        'TRIMESTRAL',
        1,
    );

    expect($settlement->annualSalePrice)->toBe(360.0)
        ->and($settlement->annualNeta)->toBe(192.0)
        ->and($settlement->installmentNeta())->toBe(48.0)
        ->and($settlement->installmentSalePrice())->toBe(90.0)
        ->and($settlement->feeId)->toBe(1);
});

it('suma netas distintas por cobertura y rango de edad', function (): void {
    $settlement = WhiteCompanyPaymentSettlement::fromPersonLines(
        [
            ['sale_price' => 189, 'neta' => 123, 'fee_id' => 10],
            ['sale_price' => 250, 'neta' => 80, 'fee_id' => 22],
        ],
        'TRIMESTRAL',
        1,
    );

    expect($settlement->annualSalePrice)->toBe(439.0)
        ->and($settlement->annualNeta)->toBe(203.0)
        ->and($settlement->installmentNeta())->toBe(50.75)
        ->and($settlement->installmentSalePrice())->toBe(109.75)
        ->and($settlement->feeId)->toBeNull();
});

it('arma la liquidacion desde los anuales congelados de la afiliacion', function (): void {
    $settlement = WhiteCompanyPaymentSettlement::fromFrozenAffiliationRates(180, 96, 'TRIMESTRAL', 17);

    expect($settlement->annualSalePrice)->toBe(180.0)
        ->and($settlement->annualNeta)->toBe(96.0)
        ->and($settlement->paymentFrequency)->toBe('TRIMESTRAL')
        ->and($settlement->whiteCompanyId)->toBe(17)
        ->and($settlement->installmentReportAmounts())->toBe([
            'sale_price' => 45.0,
            'neta_tdg' => 24.0,
            'neta_partner' => 21.0,
        ]);
});

it('calcula neta aliada sobre el monto declarado del comprobante', function (): void {
    $settlement = WhiteCompanyPaymentSettlement::fromFrozenAffiliationRates(405, 224, 'TRIMESTRAL', 21);

    expect($settlement->reportAmountsUsingDeclaredVoucher(103))
        ->toBe([
            'sale_price' => 103.0,
            'neta_tdg' => 56.0,
            'neta_partner' => 47.0,
        ])
        ->and($settlement->reportAmountsUsingDeclaredVoucher(0))
        ->toBe($settlement->installmentReportAmounts());
});

it('no persiste comisiones en la liquidacion de empresa aliada', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Support/WhiteCompanies/WhiteCompanyPaymentSettlement.php');

    expect($source)
        ->toContain('function installmentSalePrice')
        ->not->toContain('function storeCommission')
        ->not->toContain('new Commission');
});
