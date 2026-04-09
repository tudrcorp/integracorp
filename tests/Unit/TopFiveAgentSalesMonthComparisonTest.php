<?php

declare(strict_types=1);

use App\Support\Charts\TopFiveAgentSalesMonthComparison;

it('devuelve como máximo 5 agentes ordenados por ventas del mes actual', function () {
    $current = collect([
        (object) ['agent_id' => 1, 'label' => 'A', 'total' => 50],
        (object) ['agent_id' => 2, 'label' => 'B', 'total' => 200],
        (object) ['agent_id' => 3, 'label' => 'C', 'total' => 150],
        (object) ['agent_id' => 4, 'label' => 'D', 'total' => 300],
        (object) ['agent_id' => 5, 'label' => 'E', 'total' => 100],
        (object) ['agent_id' => 6, 'label' => 'F', 'total' => 400],
    ]);

    $previous = collect([
        (object) ['agent_id' => 1, 'label' => 'A', 'total' => 10],
        (object) ['agent_id' => 6, 'label' => 'F', 'total' => 5],
    ]);

    $result = TopFiveAgentSalesMonthComparison::mergeAndTakeTopFiveByCurrentMonth($current, $previous);

    expect($result)->toHaveCount(5)
        ->and($result->first()['label'])->toBe('F')
        ->and($result->first()['current'])->toBe(400.0)
        ->and($result->pluck('label')->all())->toBe(['F', 'D', 'B', 'C', 'E']);
});

it('desempata por ventas del mes anterior cuando el mes actual es igual', function () {
    $current = collect([
        (object) ['agent_id' => 1, 'label' => 'A', 'total' => 100],
        (object) ['agent_id' => 2, 'label' => 'B', 'total' => 100],
    ]);

    $previous = collect([
        (object) ['agent_id' => 1, 'label' => 'A', 'total' => 50],
        (object) ['agent_id' => 2, 'label' => 'B', 'total' => 80],
    ]);

    $result = TopFiveAgentSalesMonthComparison::mergeAndTakeTopFiveByCurrentMonth($current, $previous);

    expect($result->first()['label'])->toBe('B')
        ->and($result->last()['label'])->toBe('A');
});

it('unifica agent_id numérico y string en la misma clave', function () {
    $current = collect([
        (object) ['agent_id' => 1, 'label' => 'Uno', 'total' => 10],
    ]);

    $previous = collect([
        (object) ['agent_id' => '1', 'label' => 'Uno', 'total' => 20],
    ]);

    $result = TopFiveAgentSalesMonthComparison::mergeAndTakeTopFiveByCurrentMonth($current, $previous);

    expect($result)->toHaveCount(1)
        ->and($result->first()['current'])->toBe(10.0)
        ->and($result->first()['previous'])->toBe(20.0);
});
