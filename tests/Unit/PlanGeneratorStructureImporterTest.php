<?php

declare(strict_types=1);

use App\Enums\PlanPricingMode;
use App\Models\Benefit;
use App\Models\Coverage;
use App\Models\Plan;
use App\Support\PlanGenerators\PlanGeneratorStructureImporter;
use App\Support\Plans\PlanCodeGenerator;
use App\Support\Plans\PlanStructurePersistence;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

/**
 * El importador convierte un plan del catálogo en el estado de formulario que
 * ya consume el generador de cotizaciones, para que el analista solo cargue las
 * imágenes y la población del cliente.
 *
 * Los tests que escriben abren una transacción y la revierten.
 */
beforeEach(function (): void {
    DB::beginTransaction();
});

afterEach(function (): void {
    DB::rollBack();
});

function planImportable(PlanPricingMode $mode, string $nombre): Plan
{
    return Plan::query()->create([
        'code' => PlanCodeGenerator::next(),
        'description' => $nombre,
        'business_unit_id' => 1,
        'type' => 'BASICO',
        'status' => 'ACTIVO',
        'created_by' => 'pest',
        'pricing_mode' => $mode->value,
        'structure_version' => Plan::STRUCTURE_VERSION_WIZARD,
    ]);
}

function beneficioImportable(string $descripcion): Benefit
{
    return Benefit::query()->create([
        'description' => $descripcion,
        'status' => 'ACTIVO',
        'created_by' => 'pest',
    ]);
}

function planConMatriz(): Plan
{
    $plan = planImportable(PlanPricingMode::Coberturas, 'PLAN IMPORTABLE');
    $naturales = beneficioImportable('Accidentes naturales');
    $bano = beneficioImportable('Accidente de baño');

    PlanStructurePersistence::persist($plan, [
        'pricing_mode' => PlanPricingMode::Coberturas->value,
        'plan_coverages' => [
            'k1' => ['coverage_key' => 'k1', 'price' => 1000],
            'k2' => ['coverage_key' => 'k2', 'price' => 5000],
        ],
        'plan_benefits' => [
            'b1' => ['benefit_id' => $naturales->id, 'limits' => [
                ['coverage_key' => 'k1', 'limit' => 1000],
                ['coverage_key' => 'k2', 'limit' => 5000],
            ]],
            'b2' => ['benefit_id' => $bano->id, 'limits' => [
                ['coverage_key' => 'k1', 'limit' => 400],
                ['coverage_key' => 'k2', 'limit' => null],
            ]],
        ],
        'plan_age_ranges' => [
            'r1' => ['range' => '1 a 23', 'age_init' => 1, 'age_end' => 23, 'rates' => [
                ['coverage_key' => 'k1', 'rate' => 120],
                ['coverage_key' => 'k2', 'rate' => 200],
            ]],
        ],
    ]);

    return $plan->fresh();
}

it('abrevia los montos como se nombran las columnas en las cotizaciones reales', function (): void {
    expect(PlanGeneratorStructureImporter::abbreviateAmount(5000))->toBe('5K')
        ->and(PlanGeneratorStructureImporter::abbreviateAmount(10000))->toBe('10K')
        ->and(PlanGeneratorStructureImporter::abbreviateAmount(1500))->toBe('1.5K')
        ->and(PlanGeneratorStructureImporter::abbreviateAmount(500))->toBe('500');
});

it('arma columnas con nombre comercial del plan y monto abreviado', function (): void {
    $plan = planConMatriz();

    $estructura = PlanGeneratorStructureImporter::build($plan);

    expect(array_column($estructura['columns'], 'header_label'))
        ->toBe(['PLAN IMPORTABLE 1K', 'PLAN IMPORTABLE 5K']);
});

it('vuelca los costos límite en las celdas y deja sin tope los que estaban en NULL', function (): void {
    $plan = planConMatriz();

    $estructura = PlanGeneratorStructureImporter::build($plan);
    $columnas = array_column($estructura['columns'], 'column_key');

    $bano = collect($estructura['rows'])->firstWhere('benefit_label', 'ACCIDENTE DE BAÑO');

    expect($bano)->not->toBeNull()
        // Incluido en ambas coberturas, con tope solo en la de 1.000.
        ->and($bano['cells'][$columnas[0]]['is_selected'])->toBeTrue()
        ->and($bano['cells'][$columnas[0]]['coverage_amount'])->toBe(400.0)
        ->and($bano['cells'][$columnas[1]]['is_selected'])->toBeTrue()
        ->and($bano['cells'][$columnas[1]]['coverage_amount'])->toBeNull();
});

it('vuelca las tarifas por rango de edad y deja la población vacía', function (): void {
    $plan = planConMatriz();

    $estructura = PlanGeneratorStructureImporter::build($plan);
    $columnas = array_column($estructura['columns'], 'column_key');
    $fila = collect($estructura['rate_rows'])->first();

    expect($fila['age_range_label'])->toBe('1 a 23 años')
        // La población es un dato del cliente, no del plan.
        ->and($fila['population'])->toBeNull()
        ->and($fila['cells'][$columnas[0]]['rate_amount'])->toBe(120.0)
        ->and($fila['cells'][$columnas[1]]['rate_amount'])->toBe(200.0);
});

it('respeta el subconjunto de coberturas elegido', function (): void {
    $plan = planConMatriz();

    $cincoMil = Coverage::query()->where('plan_id', $plan->id)->where('price', 5000)->firstOrFail();

    $estructura = PlanGeneratorStructureImporter::build($plan, [(string) $cincoMil->id]);

    expect($estructura['columns'])->toHaveCount(1)
        ->and($estructura['columns'][0]['header_label'])->toBe('PLAN IMPORTABLE 5K')
        ->and($estructura['summary']['columns'])->toBe(1);

    foreach ($estructura['rows'] as $fila) {
        expect($fila['cells'])->toHaveCount(1);
    }

    foreach ($estructura['rate_rows'] as $fila) {
        expect($fila['cells'])->toHaveCount(1);
    }
});

it('convierte un paquete de beneficios en una única columna con su tarifa plana', function (): void {
    $plan = planImportable(PlanPricingMode::Paquete, 'PAQUETE IMPORTABLE');

    PlanStructurePersistence::persist($plan, [
        'pricing_mode' => PlanPricingMode::Paquete->value,
        'package_benefit_ids' => [(int) beneficioImportable('Telemedicina')->id],
        'package_age_ranges' => [
            'r1' => ['range' => '0 a 40', 'age_init' => 0, 'age_end' => 40, 'rate' => 160],
            'r2' => ['range' => '41 a 99', 'age_init' => 41, 'age_end' => 99, 'rate' => 380],
        ],
    ]);

    $estructura = PlanGeneratorStructureImporter::build($plan->fresh());
    $columna = $estructura['columns'][0]['column_key'];

    expect($estructura['columns'])->toHaveCount(1)
        ->and($estructura['columns'][0]['header_label'])->toBe('PAQUETE IMPORTABLE')
        ->and($estructura['rate_rows'])->toHaveCount(2)
        ->and(array_map(
            fn (array $fila): mixed => $fila['cells'][$columna]['rate_amount'],
            array_values($estructura['rate_rows']),
        ))->toBe([160.0, 380.0]);
});

it('ofrece una sola opción de columna para un paquete de beneficios', function (): void {
    $plan = planImportable(PlanPricingMode::Paquete, 'PAQUETE OPCIONES');

    expect(PlanGeneratorStructureImporter::coverageOptions($plan))
        ->toBe([PlanGeneratorStructureImporter::PACKAGE_COLUMN_ID => 'PAQUETE OPCIONES']);
});

it('genera claves de columna únicas para no pisar celdas entre columnas', function (): void {
    $plan = planConMatriz();

    $claves = array_column(PlanGeneratorStructureImporter::build($plan)['columns'], 'column_key');

    expect($claves)->toHaveCount(2)
        ->and(array_unique($claves))->toHaveCount(2);
});

it('no arma columnas si el subconjunto elegido no existe en el plan', function (): void {
    $plan = planConMatriz();

    $estructura = PlanGeneratorStructureImporter::build($plan, ['999999']);

    expect($estructura['columns'])->toBe([]);
});
