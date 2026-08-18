<?php

declare(strict_types=1);

use App\Support\WhiteCompanies\WhiteCompanyPaymentSettlement;

it('prorratea neta y precio de venta segun la frecuencia de pago', function (string $frequency, int $periods, float $neta, float $salePrice): void {
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
        ->and($settlement->installmentSalePrice())->toBe($salePrice);
})->with([
    'anual' => ['ANUAL', 1, 96.0, 180.0],
    'semestral' => ['SEMESTRAL', 2, 48.0, 90.0],
    'trimestral' => ['TRIMESTRAL', 4, 24.0, 45.0],
    'mensual' => ['MENSUAL', 12, 8.0, 15.0],
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

it('no persiste comisiones en la liquidacion de empresa aliada', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Support/WhiteCompanies/WhiteCompanyPaymentSettlement.php');

    expect($source)
        ->toContain('function installmentSalePrice')
        ->not->toContain('function storeCommission')
        ->not->toContain('new Commission');
});
