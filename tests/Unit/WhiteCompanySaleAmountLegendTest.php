<?php

declare(strict_types=1);

use App\Models\Affiliation;
use App\Models\Sale;
use App\Support\WhiteCompanies\WhiteCompanySaleAmountLegend;

it('formatea neta total y numero de cuota segun la frecuencia', function (): void {
    expect(WhiteCompanySaleAmountLegend::format(192, 'TRIMESTRAL', 1))
        ->toBe('Neta total: 192,00 US$ · Cuota 1 de 4')
        ->and(WhiteCompanySaleAmountLegend::format(192, 'TRIMESTRAL', 4))
        ->toBe('Neta total: 192,00 US$ · Cuota 4 de 4')
        ->and(WhiteCompanySaleAmountLegend::format(192, 'TRIMESTRAL', 5))
        ->toBe('Neta total: 192,00 US$ · Cuota 1 de 4')
        ->and(WhiteCompanySaleAmountLegend::format(96, 'ANUAL', 1))
        ->toBe('Neta total: 96,00 US$ · Pago único');
});

it('oculta la leyenda cuando la venta no es de empresa aliada', function (): void {
    $sale = new Sale(['payment_frequency' => 'TRIMESTRAL']);
    $sale->setRelation('affiliationByCode', new Affiliation);

    expect(WhiteCompanySaleAmountLegend::forSale($sale))->toBeNull();
});

it('muestra la leyenda solo cuando la afiliacion tiene neta congelada', function (): void {
    $affiliation = new Affiliation(['white_company_neta' => 192]);
    $sale = new Sale(['payment_frequency' => 'TRIMESTRAL']);
    $sale->setRelation('affiliationByCode', $affiliation);

    expect(WhiteCompanySaleAmountLegend::forSale($sale, 2))
        ->toBe('Neta total: 192,00 US$ · Cuota 2 de 4');
});

it('conecta la leyenda bajo la neta aliada de la tabla de ventas de administracion', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Sales/Tables/SalesTable.php');
    $netaPos = strpos($source, "TextColumn::make('white_company_neta')");
    $legendPos = strpos($source, 'WhiteCompanySaleAmountLegend::forSale');

    expect($source)
        ->toContain('WhiteCompanySaleAmountLegend::forSale')
        ->toContain("TextColumn::make('white_company_neta')")
        ->toContain("TextColumn::make('total_amount')")
        ->toContain("->with('affiliationByCode')")
        ->and($legendPos)->toBeGreaterThan($netaPos);
});
