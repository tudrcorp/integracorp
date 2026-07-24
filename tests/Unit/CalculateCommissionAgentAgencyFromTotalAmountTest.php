<?php

declare(strict_types=1);

use App\Http\Controllers\CommissionController;

it('calcula comision de agente solo sobre total_amount', function (): void {
    $result = CommissionController::buildAgentCommissionFromTotalAmount(
        totalAmount: 1000.0,
        commissionTdecAgent: 8.0,
    );

    expect($result['porcent_agent'])->toBe(8.0)
        ->and($result['porcentaje_agente'])->toBe(80.0)
        ->and($result['money'])->toBe('usd')
        ->and($result['total_amount'])->toBe(1000.0);
});

it('calcula jerarquia general + master sobre total_amount', function (): void {
    $result = CommissionController::buildGeneralAgencyCommissionsFromTotalAmount(
        totalAmount: 1000.0,
        agentCommissionPercent: 8.0,
        agencyGeneralCommissionTdec: 12.0,
        agencyGeneralOwnerCode: 'TDG-200',
        agencyMasterCommissionTdec: 15.0,
    );

    expect($result['porcent_gral'])->toBe(4.0)
        ->and($result['porcent_master'])->toBe(3.0)
        ->and($result['porcentaje_agencia_general'])->toBe(40.0)
        ->and($result['porcentaje_agencia_master'])->toBe(30.0)
        ->and($result['money'])->toBe('usd');
});

it('no calcula master cuando la general pertenece a TDG-100', function (): void {
    $result = CommissionController::buildGeneralAgencyCommissionsFromTotalAmount(
        totalAmount: 1000.0,
        agentCommissionPercent: 8.0,
        agencyGeneralCommissionTdec: 12.0,
        agencyGeneralOwnerCode: 'TDG-100',
        agencyMasterCommissionTdec: null,
    );

    expect($result['porcent_gral'])->toBe(4.0)
        ->and($result['porcent_master'])->toBe(0.0)
        ->and($result['porcentaje_agencia_general'])->toBe(40.0)
        ->and($result['porcentaje_agencia_master'])->toBe(0.0);
});

it('calcula master directa sobre total_amount', function (): void {
    $result = CommissionController::buildMasterAgencyCommissionFromTotalAmount(
        totalAmount: 1000.0,
        agentCommissionPercent: 8.0,
        agencyMasterCommissionTdec: 15.0,
    );

    expect($result['porcent_master'])->toBe(7.0)
        ->and($result['porcentaje_agencia_master'])->toBe(70.0)
        ->and($result['money'])->toBe('usd');
});

it('usa total_amount como base unica en agente, general y master', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/CommissionController.php');

    foreach ([
        'calculateCommissionAgente' => 'calculateCommissionGeneral',
        'calculateCommissionGeneral' => 'calculateCommissionMaster',
        'calculateCommissionMaster' => 'calculateCommission',
    ] as $method => $nextMethod) {
        $methodStart = strpos($source, "public static function {$method}");
        $nextStart = strpos($source, "public static function {$nextMethod}");
        $methodSource = substr($source, $methodStart, $nextStart - $methodStart);

        expect($methodSource)
            ->toContain('total_amount')
            ->not->toContain('pay_amount_usd')
            ->not->toContain('pay_amount_ves');
    }
});
