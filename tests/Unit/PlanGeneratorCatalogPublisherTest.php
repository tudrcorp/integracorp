<?php

declare(strict_types=1);

use App\Enums\PlanPricingMode;
use App\Models\AgeRange;
use App\Models\BenefitCoverage;
use App\Models\BenefitPlan;
use App\Models\Coverage;
use App\Models\Fee;
use App\Models\Plan;
use App\Models\PlanGenerator;
use App\Support\PlanGenerators\PlanGeneratorCatalogPublisher;
use App\Support\PlanGenerators\PlanGeneratorPersistence;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

/**
 * Publicación de la matriz de un plan generado en el catálogo de planes.
 *
 * Es lo que le da a la afiliación un `plan_id`, `coverage_id` y `age_range_id`
 * reales: sin el plan en `plans`, las columnas Plan, Cobertura y Rango de Edad
 * de «Plan(es) Afiliado(s)» salían vacías.
 *
 * Todo lo que escribe va dentro de una transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();

    $this->generador = planGeneradoPublicable();
});

afterEach(function (): void {
    DB::rollBack();
});

function planGeneradoPublicable(): PlanGenerator
{
    $plan = PlanGenerator::query()->create([
        'name' => 'PLAN CATALOGO PEST',
        'control_number' => 'PEST-CAT-'.uniqid(),
        'client_data' => 'CLIENTE CATALOGO',
        'issued_at' => now(),
        'agent_name' => 'PEST',
        'population_summary' => '30',
        'status' => 'PRE-APROBADO',
    ]);

    PlanGeneratorPersistence::syncFromFormState($plan, [
        'columns' => [
            ['column_key' => 'col-a', 'header_label' => 'PLAN I 5K'],
            ['column_key' => 'col-b', 'header_label' => 'IDEAL 10K'],
        ],
        'rows' => [
            'b1' => [
                'benefit_label' => 'TRASLADOS EN AMBULANCIA PEST',
                'cells' => [
                    // Incluido con tope en una cobertura, sin tope en la otra.
                    'col-a' => ['is_selected' => true, 'coverage_amount' => 5000.0],
                    'col-b' => ['is_selected' => true, 'coverage_amount' => null],
                ],
            ],
            'b2' => [
                'benefit_label' => 'CONSULTA ESPECIALISTA PEST',
                'cells' => [
                    // No incluido en la primera cobertura.
                    'col-a' => ['is_selected' => false, 'coverage_amount' => null],
                    'col-b' => ['is_selected' => true, 'coverage_amount' => 1200.0],
                ],
            ],
        ],
        'rate_rows' => [
            'r1' => [
                'age_range_label' => '00 - 45',
                'population' => 10,
                'cells' => [
                    'col-a' => ['rate_amount' => 120.0],
                    'col-b' => ['rate_amount' => 255.0],
                ],
            ],
            'r2' => [
                'age_range_label' => '46 - 74',
                'population' => 20,
                'cells' => [
                    'col-a' => ['rate_amount' => 144.0],
                    'col-b' => ['rate_amount' => 310.0],
                ],
            ],
        ],
        'quotation_pages' => [],
    ]);

    return $plan->fresh();
}

it('lee el monto de la cobertura del encabezado de la columna', function (): void {
    expect(PlanGeneratorCatalogPublisher::parseAbbreviatedAmount('2k'))->toBe(2000.0)
        ->and(PlanGeneratorCatalogPublisher::parseAbbreviatedAmount('PLAN I 5K'))->toBe(5000.0)
        ->and(PlanGeneratorCatalogPublisher::parseAbbreviatedAmount('IDEAL 1.5K'))->toBe(1500.0)
        ->and(PlanGeneratorCatalogPublisher::parseAbbreviatedAmount('AP 500'))->toBe(500.0)
        // Sin monto en el nombre no se inventa ninguno.
        ->and(PlanGeneratorCatalogPublisher::parseAbbreviatedAmount('GRUPO EMPRESARIAL'))->toBeNull();
});

it('cae al mayor tope de cobertura cuando el nombre no trae monto', function (): void {
    $rows = [
        'b1' => ['cells' => ['col-a' => ['is_selected' => true, 'coverage_amount' => 3000.0]]],
        'b2' => ['cells' => ['col-a' => ['is_selected' => true, 'coverage_amount' => 7500.0]]],
        // Una celda no tildada no cuenta.
        'b3' => ['cells' => ['col-a' => ['is_selected' => false, 'coverage_amount' => 99000.0]]],
    ];

    expect(PlanGeneratorCatalogPublisher::coveragePriceForColumn(
        ['column_key' => 'col-a', 'header_label' => 'GRUPO EMPRESARIAL'],
        $rows,
    ))->toBe(7500.0);

    // Sin nombre con monto y sin topes: cero, que acá significa «sin tope».
    expect(PlanGeneratorCatalogPublisher::coveragePriceForColumn(
        ['column_key' => 'col-z', 'header_label' => 'SIN MONTO'],
        $rows,
    ))->toBe(0.0);
});

it('lee los límites de un rango etario escrito a mano', function (): void {
    expect(PlanGeneratorCatalogPublisher::ageRangeBounds('18 - 64'))->toBe([18, 64])
        ->and(PlanGeneratorCatalogPublisher::ageRangeBounds('00 - 45'))->toBe([0, 45])
        ->and(PlanGeneratorCatalogPublisher::ageRangeBounds('Rango etario 0 a 30'))->toBe([0, 30])
        ->and(PlanGeneratorCatalogPublisher::ageRangeBounds('SIN NUMEROS'))->toBe([null, null]);
});

it('publica el plan como DRESS-TAILOR con toda su estructura', function (): void {
    $resultado = PlanGeneratorCatalogPublisher::publish($this->generador, 'pest');
    $plan = $resultado['plan'];

    expect($plan)->toBeInstanceOf(Plan::class)
        ->and($plan->type)->toBe('DRESS-TAILOR')
        ->and($plan->description)->toBe('PLAN CATALOGO PEST')
        // `pricing_mode` viene casteado al enum en el modelo.
        ->and($plan->pricing_mode)->toBe(PlanPricingMode::Coberturas)
        ->and((bool) $plan->is_quotable)->toBeFalse()
        ->and($plan->status)->toBe('ACTIVO')
        ->and($this->generador->fresh()->catalog_plan_id)->toBe($plan->getKey());

    // Una cobertura por columna, con el monto leído del encabezado.
    $coberturas = Coverage::query()->where('plan_id', $plan->getKey())->orderBy('price')->get();

    expect($coberturas)->toHaveCount(2)
        ->and($coberturas->pluck('price')->map(fn ($p): float => (float) $p)->all())->toBe([5000.0, 10000.0]);

    // Un rango de edad por fila de tarifa, con sus límites parseados.
    $rangos = AgeRange::query()->where('plan_id', $plan->getKey())->orderBy('age_init')->get();

    expect($rangos)->toHaveCount(2)
        ->and($rangos->first()->range)->toBe('00 - 45')
        ->and((int) $rangos->first()->age_init)->toBe(0)
        ->and((int) $rangos->first()->age_end)->toBe(45);

    // Una tarifa por (rango, cobertura): 2 x 2.
    expect(Fee::query()->where('plan_id', $plan->getKey())->count())->toBe(4);

    $coberturaA = $coberturas->firstWhere('price', '5000.00');
    $tarifa = Fee::query()
        ->where('plan_id', $plan->getKey())
        ->where('coverage_id', $coberturaA->getKey())
        ->where('age_range_id', $rangos->first()->getKey())
        ->firstOrFail();

    expect((float) $tarifa->price)->toBe(120.0);

    // Los dos beneficios quedan en el plan.
    expect(BenefitPlan::query()->where('plan_id', $plan->getKey())->count())->toBe(2);
});

it('solo declara el tope de las coberturas donde el beneficio está tildado', function (): void {
    $resultado = PlanGeneratorCatalogPublisher::publish($this->generador, 'pest');
    $plan = $resultado['plan'];

    $coberturaA = Coverage::query()->where('plan_id', $plan->getKey())->where('price', 5000.00)->firstOrFail();
    $coberturaB = Coverage::query()->where('plan_id', $plan->getKey())->where('price', 10000.00)->firstOrFail();

    $porBeneficio = BenefitCoverage::query()
        ->where('plan_id', $plan->getKey())
        ->get()
        ->groupBy('benefit_description');

    // TRASLADOS está en las dos: con tope en la primera, sin tope en la segunda.
    $traslados = $porBeneficio['TRASLADOS EN AMBULANCIA PEST'];

    expect($traslados)->toHaveCount(2)
        ->and((float) $traslados->firstWhere('coverage_id', $coberturaA->getKey())->limit)->toBe(5000.0)
        ->and($traslados->firstWhere('coverage_id', $coberturaB->getKey())->limit)->toBeNull();

    // CONSULTA no está tildado en la primera cobertura: no hay fila para ella.
    $consulta = $porBeneficio['CONSULTA ESPECIALISTA PEST'];

    expect($consulta)->toHaveCount(1)
        ->and($consulta->first()->coverage_id)->toBe($coberturaB->getKey())
        ->and((float) $consulta->first()->limit)->toBe(1200.0);
});

it('no repite el código de plan si el que toca ya está tomado', function (): void {
    // `PlanCodeGenerator::next()` es max(id)+1 y `plans.code` no tiene índice
    // único: dos publicaciones simultáneas pedirían el mismo código.
    $tomado = \App\Support\Plans\PlanCodeGenerator::next();

    Plan::query()->create([
        'code' => $tomado,
        'description' => 'PLAN QUE OCUPA EL CODIGO PEST',
        'business_unit_id' => 1,
        'type' => 'DRESS-TAILOR',
        'status' => 'ACTIVO',
        'created_by' => 'pest',
        'pricing_mode' => PlanPricingMode::Coberturas->value,
    ]);

    $publicado = PlanGeneratorCatalogPublisher::publish($this->generador, 'pest');

    expect($publicado['plan']->code)->not->toBe($tomado)
        ->and(Plan::query()->where('code', $publicado['plan']->code)->count())->toBe(1);
});

it('publicar de nuevo reutiliza el mismo plan y no duplica coberturas', function (): void {
    $primera = PlanGeneratorCatalogPublisher::publish($this->generador, 'pest');
    $planId = $primera['plan']->getKey();
    $coberturaIds = array_values($primera['coverage_ids']);

    $segunda = PlanGeneratorCatalogPublisher::publish($this->generador->fresh(), 'pest');

    expect($segunda['plan']->getKey())->toBe($planId)
        ->and(array_values($segunda['coverage_ids']))->toBe($coberturaIds)
        ->and(Coverage::query()->where('plan_id', $planId)->count())->toBe(2)
        ->and(AgeRange::query()->where('plan_id', $planId)->count())->toBe(2)
        ->and(Fee::query()->where('plan_id', $planId)->count())->toBe(4);
});

it('guardar la matriz de nuevo conserva el enlace con el catálogo', function (): void {
    $publicado = PlanGeneratorCatalogPublisher::publish($this->generador, 'pest');
    $coberturaA = $publicado['coverage_ids']['col-a'];

    // `syncFromFormState` borra y recrea columnas y rangos en cada guardado.
    $estado = PlanGeneratorPersistence::formStateFromModel($this->generador->fresh());
    PlanGeneratorPersistence::syncFromFormState($this->generador->fresh(), [
        ...$estado,
        'quotation_pages' => [],
    ]);

    $columna = $this->generador->fresh()->columns->firstWhere('column_key', 'col-a');
    $rango = $this->generador->fresh()->rateRows->firstWhere('age_range_label', '00 - 45');

    expect($columna->coverage_id)->toBe($coberturaA)
        ->and($rango->age_range_id)->toBe($publicado['age_range_ids']['00 - 45']);

    // Y republicar sigue apuntando al mismo plan.
    expect(PlanGeneratorCatalogPublisher::publish($this->generador->fresh(), 'pest')['plan']->getKey())
        ->toBe($publicado['plan']->getKey());
});

it('abre cada cobertura elegida en una fila por rango etario para la afiliación corporativa', function (): void {
    $publicado = PlanGeneratorCatalogPublisher::publish($this->generador, 'pest');

    $filas = \App\Support\PlanGenerators\PlanGeneratorPreAffiliationOptions::corporatePlanRowsForColumn(
        $publicado['plan'],
        [
            'plan_generator_id' => $this->generador->getKey(),
            'column_key' => 'col-a',
        ],
        $publicado,
    );

    expect($filas)->toHaveCount(2);

    // 120 x 10 personas y 144 x 20 personas, cada una con su rango real.
    expect($filas[0]['fee'])->toBe(120.0)
        ->and($filas[0]['total_persons'])->toBe(10)
        ->and($filas[0]['subtotal_anual'])->toBe(1200.0)
        ->and($filas[0]['age_range_id'])->toBe($publicado['age_range_ids']['00 - 45'])
        ->and($filas[0]['coverage_id'])->toBe($publicado['coverage_ids']['col-a'])
        ->and($filas[0]['plan_id'])->toBe($publicado['plan']->getKey())
        ->and($filas[1]['fee'])->toBe(144.0)
        ->and($filas[1]['total_persons'])->toBe(20)
        ->and($filas[1]['subtotal_anual'])->toBe(2880.0);

    // La suma coincide con el total grupal de la columna: 1.200 + 2.880 = 4.080.
    expect(array_sum(array_column($filas, 'subtotal_anual')))->toBe(4080.0);
});

it('los dos flujos de afiliación publican el plan antes de crear', function (): void {
    $individual = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Pages/CreateAffiliation.php');
    $corporativo = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Pages/CreateAffiliationCorporate.php');

    expect($individual)
        ->toContain('PlanGeneratorCatalogPublisher::publish')
        ->toContain('$data = $this->publishPlanGeneratorCatalogPlan($data);')
        // La afiliación individual sí guarda plan y cobertura en su propia fila.
        ->toContain("\$data['plan_id'] = \$published['plan']->getKey();")
        ->toContain("\$data['coverage_id'] = \$published['coverage_ids'][\$columnKey];");

    expect($corporativo)
        ->toContain('PlanGeneratorCatalogPublisher::publish')
        ->toContain('$data = $this->publishPlanGeneratorCatalogPlan($data);')
        ->toContain('corporatePlanRowsForColumn')
        // `affiliation_corporates` no tiene esas columnas: los FKs van en
        // `afilliation_corporate_plans`.
        ->not->toContain("\$data['plan_id'] =");
});
