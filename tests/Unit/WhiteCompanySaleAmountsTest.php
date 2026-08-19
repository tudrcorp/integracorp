<?php

declare(strict_types=1);

use App\Support\WhiteCompanies\WhiteCompanyPaymentSettlement;
use App\Support\WhiteCompanies\WhiteCompanySaleAmounts;

it('guarda la venta del comprobante y la neta aliada en campos separados', function (): void {
    $settlement = new WhiteCompanyPaymentSettlement(
        annualSalePrice: 405,
        annualNeta: 224,
        paymentFrequency: 'TRIMESTRAL',
        whiteCompanyId: 21,
        feeId: 11,
    );

    expect(WhiteCompanySaleAmounts::fromApproval(103, $settlement))
        ->toBe([
            'total_amount' => 103.0,
            'white_company_neta' => 56.0,
        ])
        ->and(WhiteCompanySaleAmounts::fromApproval(122.5, null))
        ->toBe([
            'total_amount' => 122.5,
            'white_company_neta' => null,
        ]);
});

it('restaura el monto de venta cuando historicamente se guardo la neta', function (): void {
    expect(WhiteCompanySaleAmounts::restoreHistoricalSale(56, 56, 103))
        ->toBe([
            'total_amount' => 103.0,
            'white_company_neta' => 56.0,
        ])
        ->and(WhiteCompanySaleAmounts::restoreHistoricalSale(101.25, 56, 103))
        ->toBe([
            'total_amount' => 101.25,
            'white_company_neta' => 56.0,
        ]);
});

it('restaura cobranzas que copiaron la neta como monto de cuota', function (): void {
    expect(WhiteCompanySaleAmounts::restoreHistoricalCollection(56, 56, 103))->toBe(103.0)
        ->and(WhiteCompanySaleAmounts::restoreHistoricalCollection(103, 56, 103))->toBe(103.0);
});

it('liquida la venta aliada con el total del comprobante y neta aparte', function (): void {
    $controllerContent = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/PaidMembershipController.php');

    expect($controllerContent)
        ->toContain('WhiteCompanySaleAmounts::fromApproval')
        ->toContain('$sales->total_amount = $saleAmount')
        ->toContain('$sales->white_company_neta = $alliedNeta')
        ->toContain('$collections->total_amount = $saleAmount')
        ->toContain('installmentNeta()')
        ->not->toContain('installmentSalePrice()')
        ->not->toContain('$sales->total_amount = $settledAmount');
});

it('declara neta aliada en el modelo y en la migracion de ventas', function (): void {
    $model = file_get_contents(dirname(__DIR__, 2).'/app/Models/Sale.php');
    $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_08_19_192500_add_white_company_neta_to_sales_table.php');

    expect($model)
        ->toContain("'white_company_neta'")
        ->toContain("'white_company_neta' => 'decimal:2'")
        ->and($migration)
        ->toContain("decimal('white_company_neta'")
        ->toContain('WhiteCompanySaleAmounts::backfillAlliedSales');
});

it('muestra la neta aliada en la tabla de ventas de administracion sin pisar el monto total', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Sales/Tables/SalesTable.php');
    $totalPos = strpos($source, "TextColumn::make('total_amount')");
    $netaPos = strpos($source, "TextColumn::make('white_company_neta')");
    $legendPos = strpos($source, 'WhiteCompanySaleAmountLegend::forSale');

    expect($source)
        ->toContain("TextColumn::make('white_company_neta')")
        ->toContain("->label('Neta aliada')")
        ->and($totalPos)->not->toBeFalse()
        ->and($netaPos)->toBeGreaterThan($totalPos)
        ->and($legendPos)->toBeGreaterThan($netaPos)
        ->and(substr($source, $totalPos, $netaPos - $totalPos))
        ->not->toContain('WhiteCompanySaleAmountLegend');
});
