<?php

use App\Filament\Business\Resources\Agencies\Widgets\TotalSaleForEstructureChart;
use App\Models\Agency;
use App\Models\Sale;

it('muestra el total de ventas por agencia usando code vs code_agency', function (): void {
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

    Sale::create([
        'code_agency' => $agencyOne->code,
        'total_amount' => 100.50,
        'affiliate_full_name' => 'Cliente 1',
    ]);

    Sale::create([
        'code_agency' => $agencyOne->code,
        'total_amount' => 50,
        'affiliate_full_name' => 'Cliente 2',
    ]);

    Sale::create([
        'code_agency' => $agencyTwo->code,
        'total_amount' => 200,
        'affiliate_full_name' => 'Cliente 3',
    ]);

    $widget = app(TotalSaleForEstructureChart::class);

    $reflection = new ReflectionClass($widget);
    $method = $reflection->getMethod('getData');
    $method->setAccessible(true);

    $data = $method->invoke($widget);

    expect($data)->toHaveKeys(['datasets', 'labels']);
    expect($data['datasets'][0])->toHaveKey('data');

    $values = $data['datasets'][0]['data'];

    sort($values);

    expect($values)->toContain(150.50);
    expect($values)->toContain(200.0);
});
