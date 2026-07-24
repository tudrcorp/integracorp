<?php

declare(strict_types=1);

use App\Http\Controllers\CommissionController;

it('calcula la jerarquia dinamica solo sobre total_amount con agencia general y master', function (): void {
    $result = CommissionController::buildSubAgentCommissionsFromTotalAmount(
        totalAmount: 1000.0,
        commissionTdecSubAgent: 5.0,
        commissionTdecAgentSuperior: 8.0,
        agencySuperiorCommissionTdec: 12.0,
        agencySuperiorTypeId: 3,
        agencySuperiorOwnerCode: 'TDG-200',
        agencyMasterCommissionTdec: 15.0,
    );

    expect($result['porcent_sub_agente'])->toBe(5.0)
        ->and($result['porcent_agente_superior'])->toBe(3.0)
        ->and($result['porcent_agencia_general'])->toBe(4.0)
        ->and($result['porcent_agencia_master'])->toBe(3.0)
        ->and($result['porcentaje_sub_agente_usd'])->toBe(50.0)
        ->and($result['porcentaje_agente_superior_usd'])->toBe(30.0)
        ->and($result['porcentaje_agencia_general_usd'])->toBe(40.0)
        ->and($result['porcentaje_agencia_master_usd'])->toBe(30.0)
        ->and($result['porcentaje_sub_agente_ves'])->toBe(0.0)
        ->and($result['money'])->toBe('usd')
        ->and($result['total_amount'])->toBe(1000.0);
});

it('calcula master directo cuando la agencia superior es master', function (): void {
    $result = CommissionController::buildSubAgentCommissionsFromTotalAmount(
        totalAmount: 500.0,
        commissionTdecSubAgent: 4.0,
        commissionTdecAgentSuperior: 7.0,
        agencySuperiorCommissionTdec: 10.0,
        agencySuperiorTypeId: 1,
        agencySuperiorOwnerCode: 'TDG-300',
        agencyMasterCommissionTdec: null,
    );

    expect($result['porcent_sub_agente'])->toBe(4.0)
        ->and($result['porcent_agente_superior'])->toBe(3.0)
        ->and($result['porcent_agencia_general'])->toBe(0.0)
        ->and($result['porcent_agencia_master'])->toBe(3.0)
        ->and($result['porcentaje_sub_agente_usd'])->toBe(20.0)
        ->and($result['porcentaje_agente_superior_usd'])->toBe(15.0)
        ->and($result['porcentaje_agencia_general_usd'])->toBe(0.0)
        ->and($result['porcentaje_agencia_master_usd'])->toBe(15.0);
});

it('no calcula master cuando la general pertenece a TDG-100', function (): void {
    $result = CommissionController::buildSubAgentCommissionsFromTotalAmount(
        totalAmount: 200.0,
        commissionTdecSubAgent: 2.0,
        commissionTdecAgentSuperior: 5.0,
        agencySuperiorCommissionTdec: 8.0,
        agencySuperiorTypeId: 3,
        agencySuperiorOwnerCode: 'TDG-100',
        agencyMasterCommissionTdec: null,
    );

    expect($result['porcent_agencia_general'])->toBe(3.0)
        ->and($result['porcent_agencia_master'])->toBe(0.0)
        ->and($result['porcentaje_agencia_general_usd'])->toBe(6.0)
        ->and($result['porcentaje_agencia_master_usd'])->toBe(0.0);
});

it('usa total_amount como base unica en calculateCommissionSubAgente', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/CommissionController.php');
    $methodStart = strpos($source, 'public static function calculateCommissionSubAgente');
    $nextMethod = strpos($source, 'public static function calculateCommissionAgente');
    $methodSource = substr($source, $methodStart, $nextMethod - $methodStart);

    expect($methodSource)
        ->toContain('buildSubAgentCommissionsFromTotalAmount')
        ->toContain('totalAmount: (float) $record->total_amount')
        ->not->toContain('pay_amount_usd')
        ->not->toContain('pay_amount_ves');
});
