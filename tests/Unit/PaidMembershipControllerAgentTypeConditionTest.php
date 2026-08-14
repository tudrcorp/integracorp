<?php

declare(strict_types=1);

it('compara tipo de agente usando agent_type_id al calcular comision de agente', function (): void {
    $controllerPath = dirname(__DIR__, 2).'/app/Http/Controllers/PaidMembershipController.php';
    $controllerContent = file_get_contents($controllerPath);

    expect($controllerContent)
        ->toContain('$agent_type->agent_type_id == 2')
        ->not->toContain('$agent_type == 2');
});

it('resuelve agencia con mensaje accionable al compensar pago individual', function (): void {
    $controllerContent = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/PaidMembershipController.php');

    expect($controllerContent)
        ->toContain('AgencyTypeForCommission::resolveOrFail')
        ->toContain('AgencyNotFoundForCommissionException')
        ->toContain('Compensación detenida: agencia inválida')
        ->not->toContain("Agency::select('code', 'agency_type_id')->where('code', \$data_afiliaciones['code_agency'])->first()");
});

it('resuelve agencia con mensaje accionable al compensar pago corporativo', function (): void {
    $controllerContent = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/PaidMembershipCorporateController.php');

    expect($controllerContent)
        ->toContain('AgencyTypeForCommission::resolveOrFail')
        ->toContain('AgencyNotFoundForCommissionException')
        ->toContain('Compensación detenida: agencia inválida')
        ->not->toContain("Agency::select('code', 'agency_type_id')->where('code', \$data_afiliaciones['code_agency'])->first()");
});

it('liquida venta y comision de empresa aliada con la neta pactada', function (): void {
    $controllerContent = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/PaidMembershipController.php');

    expect($controllerContent)
        ->toContain('WhiteCompanyNegotiatedRateResolver')
        ->toContain('WhiteCompanyPaymentSettlement')
        ->toContain('settlementForAffiliation')
        ->toContain('installmentNeta()')
        ->toContain('storeCommission')
        ->toContain('WhiteCompanyNegotiatedRateMissingException')
        ->toContain('Compensación detenida: neta no pactada')
        ->toContain('$sales->total_amount = $settledAmount')
        ->toContain('$collections->total_amount = $settledAmount');
});
