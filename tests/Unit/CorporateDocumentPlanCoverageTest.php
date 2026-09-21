<?php

declare(strict_types=1);

use App\Support\AffiliationAffiliateFeeCalculator;
use App\Support\CorporateDocumentPlanCoverage;

it('no pide price sobre una cobertura nula en los documentos corporativos', function (): void {
    $root = dirname(__DIR__, 2);
    $documents = [
        $root.'/resources/views/documents/factura-corporativa.blade.php',
        $root.'/resources/views/documents/aviso-de-pago-corporativo.blade.php',
        $root.'/resources/views/documents/aviso-de-cobro-corporativo.blade.php',
        $root.'/resources/views/documents/regenerar-aviso-de-pago-corporativo.blade.php',
    ];

    foreach ($documents as $path) {
        $source = file_get_contents($path);

        expect($source)
            ->toContain('CorporateDocumentPlanCoverage::priceForLine')
            ->not->toContain("\$plan == 'PLAN INICIAL'")
            ->not->toContain('Coverage::where(\'id\'')
            ->and($source)->toContain('@if (filled($coverage))');
    }
});

it('omite el monto cuando no hay coverage_id, como un plan inicial', function (): void {
    expect(CorporateDocumentPlanCoverage::priceForLine(null, null))->toBeNull()
        ->and(CorporateDocumentPlanCoverage::priceForLine(AffiliationAffiliateFeeCalculator::INITIAL_PLAN_ID, null))->toBeNull()
        ->and(CorporateDocumentPlanCoverage::priceForLine(99, ''))->toBeNull()
        ->and(CorporateDocumentPlanCoverage::priceForLine(99, 0))->toBeNull();
});

it('resuelve el precio con el calculador de planes sin coberturas, no con el nombre PLAN INICIAL', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Support/CorporateDocumentPlanCoverage.php');

    expect($source)
        ->toContain('planHasNoCoverages')
        ->toContain('value(\'price\')')
        ->not->toContain('->first()->price');
});
