<?php

declare(strict_types=1);

use App\Models\AfilliationCorporatePlan;
use App\Services\CorporateAffiliateRemovalService;

it('convierte tarifa anual a monto por periodo según frecuencia', function () {
    expect(CorporateAffiliateRemovalService::annualFeeToPerPeriodAmount(1200.0, 'ANUAL'))->toBe(1200.0)
        ->and(CorporateAffiliateRemovalService::annualFeeToPerPeriodAmount(1200.0, 'SEMESTRAL'))->toBe(600.0)
        ->and(CorporateAffiliateRemovalService::annualFeeToPerPeriodAmount(1200.0, 'TRIMESTRAL'))->toBe(300.0)
        ->and(CorporateAffiliateRemovalService::annualFeeToPerPeriodAmount(1200.0, 'MENSUAL'))->toBe(100.0);
});

it('recalcula subtotales de fila de plan corporativo', function () {
    $row = new AfilliationCorporatePlan([
        'fee' => 100.0,
        'total_persons' => 3,
    ]);
    CorporateAffiliateRemovalService::recalculateCorporatePlanRowTotals($row);

    expect((float) $row->subtotal_anual)->toBe(300.0)
        ->and((float) $row->subtotal_quarterly)->toBe(75.0)
        ->and((float) $row->subtotal_biannual)->toBe(150.0)
        ->and((float) $row->subtotal_monthly)->toBe(25.0);
});
