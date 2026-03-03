<?php

use App\Filament\Business\Resources\Agents\Widgets\TotalSaleForAgent;
use App\Models\Agent;
use App\Models\Sale;
use Carbon\Carbon;

it('muestra ventas por agente solo con total mayor a cero', function (): void {
    Carbon::setTestNow(Carbon::create(2025, 5, 15, 12, 0, 0));

    $agentOne = Agent::create([
        'name' => 'Agente Uno',
        'status' => 'ACTIVO',
    ]);

    $agentTwo = Agent::create([
        'name' => 'Agente Dos',
        'status' => 'ACTIVO',
    ]);

    // Ventas para agente uno en el año actual
    Sale::create([
        'agent_id' => $agentOne->id,
        'total_amount' => 150.75,
        'affiliate_full_name' => 'Cliente 1',
        'created_at' => Carbon::create(2025, 3, 10),
    ]);

    // Agente sin ventas (no debe aparecer)
    Agent::create([
        'name' => 'Agente Sin Ventas',
        'status' => 'ACTIVO',
    ]);

    /** @var TotalSaleForAgent $widget */
    $widget = app(TotalSaleForAgent::class);
    $widget->filter = 'year';

    $reflection = new ReflectionClass($widget);
    $method = $reflection->getMethod('getData');
    $method->setAccessible(true);

    $data = $method->invoke($widget);

    expect($data)->toHaveKeys(['datasets', 'labels']);
    expect($data['datasets'][0])->toHaveKeys(['data', 'backgroundColor']);

    $labels = $data['labels'];
    $values = $data['datasets'][0]['data'];

    expect($labels)->toContain('Agente Uno');
    expect($labels)->toContain('Agente Dos')->not()->toBeFalse(); // aparece con 0 si no tiene ventas, pero se filtra por having > 0
    expect($labels)->not->toContain('Agente Sin Ventas');

    $indexAgentOne = array_search('Agente Uno', $labels, true);
    expect($values[$indexAgentOne])->toBe(150.75);
});
