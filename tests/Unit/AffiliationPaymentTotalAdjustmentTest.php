<?php

declare(strict_types=1);

use App\Models\Affiliation;
use App\Support\AffiliationPaymentTotalAdjustment;

it('calcula total ajustado por porcentaje positivo o negativo', function (): void {
    expect(AffiliationPaymentTotalAdjustment::adjust(100.0, 10.0))->toBe(110.0)
        ->and(AffiliationPaymentTotalAdjustment::adjust(100.0, -10.0))->toBe(90.0)
        ->and(AffiliationPaymentTotalAdjustment::adjust(250.5, 0.0))->toBe(250.5);
});

it('calcula total en VES a partir del total USD y tasa BCV', function (): void {
    expect(AffiliationPaymentTotalAdjustment::vesTotalFromUsd(1256.0, 36.0))->toBe(45216.0);
});

it('incluye tasa BCV y total VES en vista previa del total', function (): void {
    $html = (string) AffiliationPaymentTotalAdjustment::previewHtml(1256.0, 0.0, 36.0);

    expect($html)
        ->toContain('Tasa BCV')
        ->toContain('Total a pagar (VES)')
        ->toContain('36.00 Bs/US$ (BCV oficial)')
        ->toContain('Bs. 45,216.00');
});

it('muestra guiones en vista previa cuando no hay tasa BCV', function (): void {
    $html = (string) AffiliationPaymentTotalAdjustment::previewHtml(1256.0, 0.0);

    expect($html)
        ->toContain('Tasa BCV')
        ->toContain('Total a pagar (VES)')
        ->toContain('>—</dd>');
});

it('totalAmountHelperText omite cobertura cuando coverage_id es inválido o la relación es null', function (): void {
    $affiliation = new Affiliation([
        'coverage_id' => 0,
        'payment_frequency' => 'ANUAL',
    ]);

    $affiliation->setRelation('plan', new \App\Models\Plan(['description' => 'Plan Oro']));
    $affiliation->setRelation('coverage', null);

    expect(AffiliationPaymentTotalAdjustment::totalAmountHelperText($affiliation))
        ->toBe('Plan: Plan Oro - Frecuencia: ANUAL');
});

it('totalAmountHelperText incluye cobertura cuando la relación existe', function (): void {
    $affiliation = new Affiliation([
        'coverage_id' => 5,
        'payment_frequency' => 'SEMESTRAL',
    ]);

    $affiliation->setRelation('plan', new \App\Models\Plan(['description' => 'Plan Plata']));
    $affiliation->setRelation('coverage', new \App\Models\Coverage(['price' => 25000]));

    expect(AffiliationPaymentTotalAdjustment::totalAmountHelperText($affiliation))
        ->toBe('Plan: Plan Plata - Cobertura: 25000 - Frecuencia: SEMESTRAL');
});

it('expone ajuste por porcentaje en comprobante de pago de afiliaciones administration', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Affiliations/Tables/AffiliationsTable.php');

    expect($source)
        ->toContain('payment_adjustment_percentage')
        ->toContain('payment_total_preview')
        ->toContain('AffiliationPaymentTotalAdjustment')
        ->toContain('applyPaymentTotalPercentageAdjustment')
        ->and(substr_count($source, 'payment_adjustment_percentage'))->toBeGreaterThanOrEqual(2);
});

it('expone ajuste por porcentaje en comprobante de pago de afiliaciones business', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Tables/AffiliationsTable.php');

    expect($source)
        ->toContain('payment_adjustment_percentage')
        ->toContain('payment_total_preview')
        ->toContain('AffiliationPaymentTotalAdjustment')
        ->toContain('applyPaymentTotalPercentageAdjustment')
        ->toContain('LINK DE PAGO')
        ->and(substr_count($source, 'payment_adjustment_percentage'))->toBeGreaterThanOrEqual(1);
});

it('expone ajuste por porcentaje en pago multiple de afiliaciones administration', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Affiliations/Tables/AffiliationsTable.php');

    expect($source)
        ->toContain("BulkAction::make('pay_multiple_affiliations')")
        ->and(preg_match(
            "/pay_multiple_affiliations'[\s\S]*?payment_adjustment_percentage[\s\S]*?payment_total_preview/",
            $source
        ))->toBe(1);
});
