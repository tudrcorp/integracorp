<?php

declare(strict_types=1);

$basePath = dirname(__DIR__, 2);

it('resuelve neta pactada por empresa aliada y congela snapshot en la afiliacion', function () use ($basePath): void {
    $source = file_get_contents($basePath.'/app/Support/WhiteCompanies/WhiteCompanyNegotiatedRateResolver.php');

    expect($source)
        ->toContain('function settlementForAffiliation(Affiliation $affiliation)')
        ->toContain('whiteCompanyForAgencyCode')
        ->toContain('WhiteCompanyFee::query()')
        ->toContain('white_company_sale_price')
        ->toContain('white_company_neta')
        ->toContain('WhiteCompanyNegotiatedRateMissingException')
        ->toContain('resolveFeeForPlanCoverageAndAge')
        ->toContain('fromPersonLines')
        ->toContain('lineForAffiliate')
        ->toContain('affiliatesForSettlement');
});

it('exige matriz de negociacion cuando la afiliacion es de empresa aliada', function (): void {
    expect(\App\Exceptions\WhiteCompanyNegotiatedRateMissingException::make(
        'Vive Plus',
        'AFF-1',
        1,
        null,
    )->getMessage())
        ->toContain('Vive Plus')
        ->toContain('AFF-1')
        ->toContain('cargar la matriz de negociación');
});

it('exige matriz de negociacion cuando falta la neta de una persona', function (): void {
    expect(\App\Exceptions\WhiteCompanyNegotiatedRateMissingException::forPerson(
        'Vive Plus',
        'AFF-1',
        'Juan Perez',
        3,
        10,
        40,
    )->getMessage())
        ->toContain('Vive Plus')
        ->toContain('Juan Perez')
        ->toContain('40 años')
        ->toContain('cargar esa combinación');
});
