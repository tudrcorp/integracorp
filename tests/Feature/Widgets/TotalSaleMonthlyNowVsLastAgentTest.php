<?php

use App\Filament\Business\Resources\Agents\Widgets\TotalSaleMonthlyNowVsLastAgent;
use App\Models\Agent;
use App\Models\Sale;
use Carbon\Carbon;

it('muestra comparacion de ventas por agente entre mes actual y anterior', function (): void {
    Carbon::setTestNow(Carbon::create(2025, 5, 15, 12, 0, 0));

    $agentOne = Agent::create([
        'name' => 'Agente Uno',
        'status' => 'ACTIVO',
    ]);

    $agentTwo = Agent::create([
        'name' => 'Agente Dos',
        'status' => 'ACTIVO',
    ]);

    // Ventas mes actual (mayo 2025) para agente uno
    Sale::create([
        'agent_id' => $agentOne->id,
        'total_amount' => 100.50,
        'affiliate_full_name' => 'Cliente 1',
        'created_at' => Carbon::create(2025, 5, 10),
    ]);

    // Ventas mes anterior (abril 2025) para agente dos
    Sale::create([
        'agent_id' => $agentTwo->id,
        'total_amount' => 200,
        'affiliate_full_name' => 'Cliente 2',
        'created_at' => Carbon::create(2025, 4, 20),
    ]);

    // Agente sin ventas en ninguno de los meses (no debe aparecer)
    Agent::create([
        'name' => 'Agente Sin Ventas',
        'status' => 'ACTIVO',
    ]);

    $widget = app(TotalSaleMonthlyNowVsLastAgent::class);

    $reflection = new ReflectionClass($widget);
    $method = $reflection->getMethod('getData');
    $method->setAccessible(true);

    $data = $method->invoke($widget);

    expect($data)->toHaveKeys(['datasets', 'labels']);
    expect($data['datasets'])->toHaveCount(2);

    $labels = $data['labels'];

    expect($labels)->toContain('Agente Uno');
    expect($labels)->toContain('Agente Dos');
    expect($labels)->not->toContain('Agente Sin Ventas');

    $currentDataset = $data['datasets'][0]['data'];
    $previousDataset = $data['datasets'][1]['data'];

    expect(count($currentDataset))->toBe(count($labels));
    expect(count($previousDataset))->toBe(count($labels));

    $indexAgentOne = array_search('Agente Uno', $labels, true);
    $indexAgentTwo = array_search('Agente Dos', $labels, true);

    expect($currentDataset[$indexAgentOne])->toBe(100.50);
    expect($previousDataset[$indexAgentOne])->toBe(0.0);

    expect($currentDataset[$indexAgentTwo])->toBe(0.0);
    expect($previousDataset[$indexAgentTwo])->toBe(200.0);
});
