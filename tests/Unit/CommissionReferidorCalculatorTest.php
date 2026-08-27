<?php

declare(strict_types=1);

use App\Models\Agency;
use App\Models\Agent;
use App\Models\Commission;
use App\Support\CommercialStructure\CommissionReferidorCalculator;

it('calcula la comisión de referidor sobre el total de la venta', function (): void {
    $referrer = new Agency([
        'is_referidor' => true,
        'referidor_percentage' => '10.00',
        'name_corporative' => 'Referidor Master',
    ]);

    $agent = new Agent(['referidor_id' => 10, 'name' => 'Vendedor']);
    $agent->setRelation('referidor', $referrer);
    $agent->setRelation('referidorAgent', null);

    $result = CommissionReferidorCalculator::compute(
        agent: $agent,
        agency: null,
        totalAmount: 1000.0,
        payAmountUsd: 1000.0,
        payAmountVes: 40000.0,
    );

    expect($result['percentage'])->toBe(10.0)
        ->and($result['usd'])->toBe(100.0)
        ->and($result['ves'])->toBe(4000.0);
});

it('calcula referidor de una venta hecha por agencia sin agente', function (): void {
    $referrer = new Agent([
        'is_referidor' => true,
        'referidor_percentage' => '5.50',
        'name' => 'Agente referidor',
    ]);

    $agency = new Agency(['referidor_agent_id' => 9, 'name_corporative' => 'General vendiendo']);
    $agency->setRelation('referidorAgent', $referrer);
    $agency->setRelation('referidor', null);

    $result = CommissionReferidorCalculator::compute(
        agent: null,
        agency: $agency,
        totalAmount: 200.0,
        payAmountUsd: 200.0,
        payAmountVes: 0.0,
    );

    expect($result['percentage'])->toBe(5.5)
        ->and($result['usd'])->toBe(11.0)
        ->and($result['ves'])->toBe(0.0);
});

it('congela porcentaje usd y ves en la comisión sin romper la venta si no hay referidor', function (): void {
    $agency = new Agency;
    $agency->setRelation('referidor', null);
    $agency->setRelation('referidorAgent', null);

    $commission = new Commission([
        'amount' => 500,
        'pay_amount_usd' => 500,
        'pay_amount_ves' => 18000,
        'code_agency' => 'GEN-001',
    ]);
    $commission->setRelation('agent', null);
    $commission->setRelation('agency', $agency);

    CommissionReferidorCalculator::apply($commission);

    expect((float) $commission->porcent_referidor)->toBe(0.0)
        ->and((float) $commission->commission_referidor_usd)->toBe(0.0)
        ->and((float) $commission->commission_referidor_ves)->toBe(0.0);
});

it('aplica el cálculo congelado al crear la comisión', function (): void {
    $referrer = new Agency([
        'is_referidor' => true,
        'referidor_percentage' => '8.00',
    ]);

    $agent = new Agent(['referidor_id' => 3]);
    $agent->setRelation('referidor', $referrer);
    $agent->setRelation('referidorAgent', null);

    $commission = new Commission([
        'amount' => 250,
        'pay_amount_usd' => 250,
        'pay_amount_ves' => 10000,
    ]);
    $commission->setRelation('agent', $agent);
    $commission->setRelation('agency', null);

    CommissionReferidorCalculator::apply($commission);

    expect((float) $commission->porcent_referidor)->toBe(8.0)
        ->and((float) $commission->commission_referidor_usd)->toBe(20.0)
        ->and((float) $commission->commission_referidor_ves)->toBe(800.0)
        ->and($commission->referidorPercentage())->toBe(8.0);
});

it('congela la comisión de referidor al crear el registro', function (): void {
    $model = file_get_contents(dirname(__DIR__, 2).'/app/Models/Commission.php');

    expect($model)
        ->toContain('CommissionReferidorCalculator::apply($commission)')
        ->toContain('static::creating')
        ->toContain("'porcent_referidor'")
        ->toContain("'commission_referidor_usd'")
        ->toContain("'commission_referidor_ves'");
});

it('incluye referidor en totales de detallado de comisiones', function (): void {
    $list = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Commissions/Pages/ListCommissions.php');
    $master = file_get_contents(dirname(__DIR__, 2).'/app/Tables/Columns/CommissionMaster.php');
    $general = file_get_contents(dirname(__DIR__, 2).'/app/Tables/Columns/CommissionGeneral.php');
    $exporter = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Exports/CommissionExporter.php');
    $modal = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/administration/commissions/modals/commission-hierarchy-details-modal.blade.php');

    expect($list)
        ->toContain('commission_referidor_usd')
        ->toContain('commission_referidor_ves');

    expect($master)->toContain('commission_referidor_usd');
    expect($general)->toContain('commission_referidor_ves');
    expect($exporter)->toContain("'% REFERIDOR'");
    expect($modal)->toContain('Nivel Referidor');
});
