<?php

use App\Filament\Business\Resources\Agencies\Widgets\AgentActiveForEstructureChart;
use App\Models\Agency;
use App\Models\Agent;

it('muestra la cantidad de agentes activos por agencia usando owner_code vs code', function (): void {
    $agencyOne = Agency::create([
        'code' => 'AG001',
        'name_corporative' => 'Agencia 1',
        'status' => 'ACTIVO',
    ]);

    $agencyTwo = Agency::create([
        'code' => 'AG002',
        'name_corporative' => 'Agencia 2',
        'status' => 'ACTIVO',
    ]);

    Agent::create([
        'owner_code' => $agencyOne->code,
        'status' => 'ACTIVO',
        'name' => 'Agente 1',
        'code_agent' => 'AGT1',
    ]);

    Agent::create([
        'owner_code' => $agencyOne->code,
        'status' => 'INACTIVO',
        'name' => 'Agente 2',
        'code_agent' => 'AGT2',
    ]);

    Agent::create([
        'owner_code' => $agencyTwo->code,
        'status' => 'ACTIVO',
        'name' => 'Agente 3',
        'code_agent' => 'AGT3',
    ]);

    $widget = app(AgentActiveForEstructureChart::class);

    $reflection = new ReflectionClass($widget);
    $method = $reflection->getMethod('getData');
    $method->setAccessible(true);

    $data = $method->invoke($widget);

    expect($data)->toHaveKeys(['datasets', 'labels']);
    expect($data['datasets'][0])->toHaveKey('data');

    $labels = $data['labels'];
    $values = $data['datasets'][0]['data'];

    $indexAgencyOne = array_search('Agencia 1', $labels, true);
    $indexAgencyTwo = array_search('Agencia 2', $labels, true);

    expect($indexAgencyOne)->not->toBeFalse();
    expect($indexAgencyTwo)->not->toBeFalse();

    expect($values[$indexAgencyOne])->toBe(1);
    expect($values[$indexAgencyTwo])->toBe(1);
});
