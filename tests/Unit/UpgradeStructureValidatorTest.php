<?php

declare(strict_types=1);

use App\Support\Upgrades\UpgradeStructureValidator;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validUpgradeFormState(array $overrides = []): array
{
    return array_replace([
        'name' => '  odontología   ampliada ',
        'description' => 'Cubre ortodoncia',
        'status' => 'ACTIVO',
        'services' => [
            'a' => ['id' => null, 'name' => 'Limpieza', 'description' => null],
        ],
        'coverages' => [
            'c1' => ['id' => null, 'amount' => '5000', 'ranges' => [
                'r1' => ['id' => null, 'age_from' => '0', 'age_to' => '17', 'price' => '20'],
                'r2' => ['id' => null, 'age_from' => '18', 'age_to' => '64', 'price' => '35.5'],
            ]],
        ],
    ], $overrides);
}

it('acepta una estructura válida y la normaliza', function (): void {
    $state = validUpgradeFormState();

    expect(UpgradeStructureValidator::errors($state))->toBe([]);

    $normalized = UpgradeStructureValidator::normalize($state);

    expect($normalized['name'])->toBe('ODONTOLOGÍA AMPLIADA')
        ->and($normalized['coverages'][0]['amount'])->toBe(5000.0)
        ->and($normalized['coverages'][0]['ranges'][0])->toBe(['id' => null, 'age_from' => 0, 'age_to' => 17, 'price' => 20.0])
        ->and($normalized['coverages'][0]['ranges'][1]['price'])->toBe(35.5);
});

it('detecta rangos solapados aunque vengan desordenados', function (): void {
    $state = validUpgradeFormState(['coverages' => [
        ['amount' => 5000, 'ranges' => [
            ['age_from' => 30, 'age_to' => 64, 'price' => 35],
            ['age_from' => 0, 'age_to' => 30, 'price' => 20],
        ]],
    ]]);

    expect(UpgradeStructureValidator::errors($state))
        ->toContain('Cobertura 5.000,00 US$: el rango 0–30 se solapa con 30–64. Una edad solo puede estar en un rango.');
});

it('admite rangos contiguos y con huecos', function (): void {
    $state = validUpgradeFormState(['coverages' => [
        ['amount' => 5000, 'ranges' => [
            ['age_from' => 0, 'age_to' => 17, 'price' => 20],
            ['age_from' => 18, 'age_to' => 40, 'price' => 30],
            ['age_from' => 50, 'age_to' => 120, 'price' => 90],
        ]],
    ]]);

    expect(UpgradeStructureValidator::errors($state))->toBe([]);
});

it('rechaza estructuras incompletas o fuera de rango', function (array $overrides, string $expected): void {
    $errors = UpgradeStructureValidator::errors(validUpgradeFormState($overrides));

    expect(implode(' | ', $errors))->toContain($expected);
})->with([
    'sin nombre' => [['name' => '   '], 'Indique el nombre del upgrade.'],
    'estatus raro' => [['status' => 'BORRADO'], 'Activo o Inactivo'],
    'sin coberturas' => [['coverages' => []], 'Agregue al menos una cobertura'],
    'cobertura sin rangos' => [['coverages' => [['amount' => 5000, 'ranges' => []]]], 'agregue al menos un rango'],
    'desde mayor que hasta' => [['coverages' => [['amount' => 5000, 'ranges' => [['age_from' => 40, 'age_to' => 20, 'price' => 10]]]]], 'no puede ser mayor'],
    'edad fuera de límites' => [['coverages' => [['amount' => 5000, 'ranges' => [['age_from' => 0, 'age_to' => 130, 'price' => 10]]]]], 'entre 0 y 120'],
    'edad negativa' => [['coverages' => [['amount' => 5000, 'ranges' => [['age_from' => -1, 'age_to' => 30, 'price' => 10]]]]], 'entre 0 y 120'],
    'edad decimal' => [['coverages' => [['amount' => 5000, 'ranges' => [['age_from' => 1.5, 'age_to' => 30, 'price' => 10]]]]], 'indique la edad desde y hasta'],
    'precio cero' => [['coverages' => [['amount' => 5000, 'ranges' => [['age_from' => 0, 'age_to' => 30, 'price' => 0]]]]], 'el precio debe ser mayor a 0'],
    'precio excesivo' => [['coverages' => [['amount' => 5000, 'ranges' => [['age_from' => 0, 'age_to' => 30, 'price' => 100000]]]]], 'no puede superar'],
    'monto de cobertura repetido' => [['coverages' => [
        ['amount' => 5000, 'ranges' => [['age_from' => 0, 'age_to' => 30, 'price' => 10]]],
        ['amount' => '5000.00', 'ranges' => [['age_from' => 0, 'age_to' => 30, 'price' => 10]]],
    ]], 'está repetida'],
    'dos coberturas sin monto' => [['coverages' => [
        ['amount' => null, 'ranges' => [['age_from' => 0, 'age_to' => 30, 'price' => 10]]],
        ['amount' => '', 'ranges' => [['age_from' => 0, 'age_to' => 30, 'price' => 10]]],
    ]], 'Solo puede haber una cobertura sin monto'],
    'servicio repetido' => [['services' => [['name' => 'Limpieza'], ['name' => ' limpieza ']]], 'está repetido'],
    'servicio sin nombre' => [['services' => [['name' => '']]], 'no tiene nombre'],
]);
