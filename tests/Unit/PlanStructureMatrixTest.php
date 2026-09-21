<?php

declare(strict_types=1);

use App\Support\Plans\PlanStructureMatrix;

uses(Tests\TestCase::class);

/**
 * Las coberturas del plan son las columnas de las dos matrices del asistente
 * (costos límite por beneficio y tarifas por rango de edad). Lo que se prueba
 * acá es que agregar o quitar una cobertura nunca corra de columna un valor ya
 * cargado: el emparejamiento es por `coverage_key`, no por posición.
 *
 * Cálculo puro sobre arrays: no toca base de datos.
 */
it('ordena las columnas por monto de cobertura ascendente', function (): void {
    $columns = PlanStructureMatrix::columns([
        'a' => ['coverage_key' => 'a', 'price' => 10000],
        'b' => ['coverage_key' => 'b', 'price' => 1000],
        'c' => ['coverage_key' => 'c', 'price' => 3000],
    ]);

    expect(array_column($columns, 'price'))->toBe([1000.0, 3000.0, 10000.0])
        ->and(array_column($columns, 'key'))->toBe(['b', 'c', 'a']);
});

it('descarta coberturas sin monto numérico', function (): void {
    $columns = PlanStructureMatrix::columns([
        'a' => ['coverage_key' => 'a', 'price' => 1000],
        'b' => ['coverage_key' => 'b', 'price' => null],
        'c' => ['coverage_key' => 'c', 'price' => ''],
        'd' => 'no es una fila',
    ]);

    expect($columns)->toHaveCount(1)
        ->and($columns[0]['key'])->toBe('a');
});

it('crea una celda por cobertura cuando la fila está vacía', function (): void {
    $columns = PlanStructureMatrix::columns([
        'a' => ['coverage_key' => 'a', 'price' => 1000],
        'b' => ['coverage_key' => 'b', 'price' => 3000],
    ]);

    $cells = PlanStructureMatrix::syncCells($columns, [], 'limit');

    expect($cells)->toHaveCount(2)
        ->and($cells[0])->toBe(['coverage_key' => 'a', 'coverage_price' => 1000.0, 'limit' => null])
        ->and($cells[1])->toBe(['coverage_key' => 'b', 'coverage_price' => 3000.0, 'limit' => null]);
});

it('conserva los valores ya cargados al agregar una cobertura nueva', function (): void {
    $cells = [
        ['coverage_key' => 'a', 'coverage_price' => 1000.0, 'limit' => 400],
        ['coverage_key' => 'b', 'coverage_price' => 3000.0, 'limit' => 2000],
    ];

    // El analista vuelve al paso de coberturas y agrega un monto intermedio.
    $columns = PlanStructureMatrix::columns([
        'a' => ['coverage_key' => 'a', 'price' => 1000],
        'nueva' => ['coverage_key' => 'nueva', 'price' => 2000],
        'b' => ['coverage_key' => 'b', 'price' => 3000],
    ]);

    $synced = PlanStructureMatrix::syncCells($columns, $cells, 'limit');

    expect($synced)->toHaveCount(3)
        ->and($synced[0]['limit'])->toBe(400)
        ->and($synced[1])->toMatchArray(['coverage_key' => 'nueva', 'limit' => null])
        ->and($synced[2]['limit'])->toBe(2000);
});

it('descarta la celda de una cobertura eliminada sin mover las demás', function (): void {
    $cells = [
        ['coverage_key' => 'a', 'coverage_price' => 1000.0, 'limit' => 400],
        ['coverage_key' => 'b', 'coverage_price' => 3000.0, 'limit' => 2000],
        ['coverage_key' => 'c', 'coverage_price' => 5000.0, 'limit' => 5000],
    ];

    $columns = PlanStructureMatrix::columns([
        'a' => ['coverage_key' => 'a', 'price' => 1000],
        'c' => ['coverage_key' => 'c', 'price' => 5000],
    ]);

    $synced = PlanStructureMatrix::syncCells($columns, $cells, 'limit');

    expect($synced)->toHaveCount(2)
        ->and($synced[0]['limit'])->toBe(400)
        ->and($synced[1]['limit'])->toBe(5000);
});

it('distingue un límite en cero de la ausencia de límite', function (): void {
    $columns = PlanStructureMatrix::columns([
        'a' => ['coverage_key' => 'a', 'price' => 1000],
        'b' => ['coverage_key' => 'b', 'price' => 3000],
    ]);

    $synced = PlanStructureMatrix::syncCells($columns, [
        ['coverage_key' => 'a', 'limit' => 0],
    ], 'limit');

    expect($synced[0]['limit'])->toBe(0)
        ->and($synced[1]['limit'])->toBeNull();
});

it('sincroniza todas las filas de una matriz conservando su clave', function (): void {
    $columns = PlanStructureMatrix::columns([
        'a' => ['coverage_key' => 'a', 'price' => 1000],
    ]);

    $rows = PlanStructureMatrix::syncRows($columns, [
        'fila-uuid' => [
            'benefit_id' => 7,
            'limits' => [['coverage_key' => 'a', 'limit' => 400]],
        ],
    ], 'limits', 'limit');

    expect($rows)->toHaveKey('fila-uuid')
        ->and($rows['fila-uuid']['benefit_id'])->toBe(7)
        ->and($rows['fila-uuid']['limits'][0]['limit'])->toBe(400);
});

it('deriva la clave de una cobertura ya guardada desde su id', function (): void {
    $columns = PlanStructureMatrix::columns([
        ['id' => 42, 'price' => 1000],
    ]);

    expect($columns[0]['key'])->toBe(PlanStructureMatrix::keyForPersistedCoverage(42))
        ->and($columns[0]['key'])->toBe('cov-42');
});

it('formatea la etiqueta de columna como monto en dólares', function (): void {
    expect(PlanStructureMatrix::columnLabel(1000))->toBe('US $1,000.00')
        ->and(PlanStructureMatrix::columnLabel(10000.5))->toBe('US $10,000.50');
});
