<?php

declare(strict_types=1);

use App\Support\Telemedicine\TelemedicineDerivedServiceBadge;

it('detecta ingreso a clínica con distintas grafías', function (): void {
    expect(TelemedicineDerivedServiceBadge::specificServiceIsIngresoAClinica('Ingreso a clínica'))->toBeTrue()
        ->and(TelemedicineDerivedServiceBadge::specificServiceIsIngresoAClinica('INGRESO A CLINICA'))->toBeTrue()
        ->and(TelemedicineDerivedServiceBadge::specificServiceIsIngresoAClinica('Hospital / Ingreso a clinica'))->toBeTrue()
        ->and(TelemedicineDerivedServiceBadge::specificServiceIsIngresoAClinica('Otro servicio'))->toBeFalse()
        ->and(TelemedicineDerivedServiceBadge::specificServiceIsIngresoAClinica(null))->toBeFalse()
        ->and(TelemedicineDerivedServiceBadge::specificServiceIsIngresoAClinica(''))->toBeFalse();
});
