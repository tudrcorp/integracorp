<?php

declare(strict_types=1);

use App\Services\TdevReports\TdevReportCommissionFromPercentageUpdater;

it('calcula monto comisión como porcentaje de la suma upgrade más PVP', function (): void {
    expect(TdevReportCommissionFromPercentageUpdater::computeMontoComision(10.5, 89.5, 10.0))
        ->toBe(10.0);
});

it('devuelve null si el porcentaje es null', function (): void {
    expect(TdevReportCommissionFromPercentageUpdater::computeMontoComision(10.0, 90.0, null))
        ->toBeNull();
});

it('redondea a cuatro decimales', function (): void {
    expect(TdevReportCommissionFromPercentageUpdater::computeMontoComision(33.333, 66.667, 7.5))
        ->toBe(7.5);
});
