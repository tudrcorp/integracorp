<?php

declare(strict_types=1);

use App\Exceptions\AgencyNotFoundForCommissionException;

it('arma mensaje de compensacion con contexto para el desarrollador', function (): void {
    $exception = AgencyNotFoundForCommissionException::make('ABC-123', 'TDEC-IND-000387', 15);

    expect($exception->getMessage())
        ->toContain('No se encontró la agencia ABC-123 al calcular comisiones')
        ->toContain('Afiliación: TDEC-IND-000387')
        ->toContain('Comprobante: #15')
        ->toContain('Agencia en afiliación: ABC-123')
        ->toContain('corregir el code_agency')
        ->toContain('No se realizó ningún cambio');
});

it('expone resolveOrFail en el soporte de comisiones', function (): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/app/Support/PaidMemberships/AgencyTypeForCommission.php'))
        ->toContain('resolveOrFail')
        ->toContain('AgencyNotFoundForCommissionException::make');
});
