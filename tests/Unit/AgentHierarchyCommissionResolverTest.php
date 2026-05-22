<?php

declare(strict_types=1);

use App\Models\Agent;
use App\Support\CommercialStructure\AgentHierarchyCommissionResolver;

it('resuelve al menos el agente actual cuando no hay owner_code', function (): void {
    $agent = new Agent([
        'name' => 'Agente Prueba',
        'agent_type_id' => 2,
        'owner_code' => '',
        'status' => 'ACTIVO',
        'commission_tdec' => 10.5,
        'commission_tdec_renewal' => 8,
        'commission_tdev' => 12,
        'commission_tdev_renewal' => 9,
    ]);
    $agent->id = 501;

    $resolution = AgentHierarchyCommissionResolver::resolve($agent);

    expect($resolution['nodes'])->toHaveCount(1)
        ->and($resolution['nodes'][0]['role'])->toBe('Agente actual')
        ->and($resolution['nodes'][0]['code'])->toBe('AGT-000501')
        ->and($resolution['warnings'])->not->toBeEmpty();
});

it('formatea la cadena jerárquica lineal de arriba hacia abajo', function (): void {
    $chain = AgentHierarchyCommissionResolver::formatLinearChain([
        [
            'role' => 'Casa matriz',
            'code' => 'TDG-100',
            'name' => 'TUDRENCASA',
        ],
        [
            'role' => 'Agencia master',
            'code' => 'TDG-200',
            'name' => 'Master Demo',
        ],
        [
            'role' => 'Agente actual',
            'code' => 'AGT-00010',
            'name' => 'Juan Pérez',
        ],
    ]);

    expect($chain)
        ->toContain('Casa matriz: TUDRENCASA (TDG-100)')
        ->toContain('Agencia master: Master Demo (TDG-200)')
        ->toContain('Agente actual: Juan Pérez (AGT-00010)')
        ->toContain(' → ');
});
