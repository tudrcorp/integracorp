<?php

declare(strict_types=1);

use App\Enums\PlanPricingMode;
use App\Models\Plan;
use App\Support\IndividualQuotePdfLayout;
use App\Support\QuotePdfPlanStructure;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

/**
 * La página del plan de una propuesta económica era una imagen de página
 * completa: traía horneados el título, la matriz de beneficios y las
 * condiciones. Los planes que no son Inicial, Ideal ni Especial caían en la
 * imagen del Ideal, así que se imprimían con el título «Plan Accidentes» y
 * columnas IDEAL US$ 1K…10K que no eran suyas.
 *
 * Lo que más importa proteger acá es que esos tres planes no se muevan.
 */
it('mantiene la plantilla histórica de los planes inicial, ideal y especial', function (): void {
    expect(IndividualQuotePdfLayout::resolve(1))->toBe(IndividualQuotePdfLayout::Inicial)
        ->and(IndividualQuotePdfLayout::resolve(2))->toBe(IndividualQuotePdfLayout::Ideal)
        ->and(IndividualQuotePdfLayout::resolve(3))->toBe(IndividualQuotePdfLayout::Especial);

    foreach (IndividualQuotePdfLayout::legacyLayouts() as $layout) {
        expect(IndividualQuotePdfLayout::usesPlanStructure($layout))->toBeFalse();
    }
});

it('conserva el desglose por cobertura de ideal y especial y no lo aplica al inicial', function (): void {
    expect(IndividualQuotePdfLayout::usesCoverageBreakdown(IndividualQuotePdfLayout::Ideal))->toBeTrue()
        ->and(IndividualQuotePdfLayout::usesCoverageBreakdown(IndividualQuotePdfLayout::Especial))->toBeTrue()
        ->and(IndividualQuotePdfLayout::usesCoverageBreakdown(IndividualQuotePdfLayout::Inicial))->toBeFalse();
});

it('no une coberturas en un paquete de beneficios, cuyas tarifas no la tienen', function (): void {
    // El JOIN contra `coverages` dejaría la consulta sin filas.
    expect(IndividualQuotePdfLayout::usesCoverageBreakdown(IndividualQuotePdfLayout::EstructuraPaquete))->toBeFalse()
        ->and(IndividualQuotePdfLayout::usesCoverageBreakdown(IndividualQuotePdfLayout::Estructura))->toBeTrue();
});

it('resuelve un layout compuesto según cómo cobra el plan', function (): void {
    DB::beginTransaction();

    try {
        $conCoberturas = Plan::query()->create([
            'code' => 'PEST-LAYOUT-COB',
            'description' => 'PLAN LAYOUT COBERTURAS',
            'business_unit_id' => 1,
            'type' => 'BASICO',
            'status' => 'ACTIVO',
            'created_by' => 'pest',
            'pricing_mode' => PlanPricingMode::Coberturas->value,
            'structure_version' => Plan::STRUCTURE_VERSION_WIZARD,
        ]);

        $paquete = Plan::query()->create([
            'code' => 'PEST-LAYOUT-PAQ',
            'description' => 'PLAN LAYOUT PAQUETE',
            'business_unit_id' => 1,
            'type' => 'BASICO',
            'status' => 'ACTIVO',
            'created_by' => 'pest',
            'pricing_mode' => PlanPricingMode::Paquete->value,
            'structure_version' => Plan::STRUCTURE_VERSION_WIZARD,
        ]);

        expect(IndividualQuotePdfLayout::resolve((int) $conCoberturas->id))->toBe(IndividualQuotePdfLayout::Estructura)
            ->and(IndividualQuotePdfLayout::resolve((int) $paquete->id))->toBe(IndividualQuotePdfLayout::EstructuraPaquete)
            ->and(IndividualQuotePdfLayout::usesPlanStructure(IndividualQuotePdfLayout::resolve((int) $paquete->id)))->toBeTrue();
    } finally {
        DB::rollBack();
    }
});

it('cae en el layout ideal si el plan no existe', function (): void {
    expect(IndividualQuotePdfLayout::resolve(999999))->toBe(IndividualQuotePdfLayout::Ideal);
});

it('titula la propuesta con el nombre real del plan', function (): void {
    $plan = Plan::query()->whereNotNull('description')->first();

    expect(QuotePdfPlanStructure::planTitle($plan?->id))
        ->toBe('Propuesta Económica - '.$plan->description)
        ->and(QuotePdfPlanStructure::planTitle(null))->toBe('Propuesta Económica')
        ->and(QuotePdfPlanStructure::planTitle(999999))->toBe('Propuesta Económica');
});

it('arma la matriz de beneficios de un plan con coberturas', function (): void {
    $plan = Plan::query()->get()->first(
        fn (Plan $p): bool => $p->pricingMode()->usesCoverages() && $p->coverages()->exists(),
    );

    if ($plan === null) {
        $this->markTestSkipped('No hay un plan con coberturas en la base.');
    }

    $matriz = QuotePdfPlanStructure::benefitsMatrix((int) $plan->id);

    expect($matriz['columns'])->not->toBeEmpty();

    $claves = array_column($matriz['columns'], 'column_key');

    foreach ($matriz['rows'] as $fila) {
        expect(array_keys($fila['cells']))->toBe($claves);
    }
});

it('devuelve una matriz vacía en vez de fallar si el plan no existe', function (): void {
    expect(QuotePdfPlanStructure::benefitsMatrix(999999))
        ->toBe(['columns' => [], 'rows' => [], 'isDense' => false])
        ->and(QuotePdfPlanStructure::benefitsMatrix(null)['columns'])->toBe([]);
});

it('arma las filas de precio plano recorriendo la colección agrupada', function (): void {
    // Castear una Collection a array devuelve sus propiedades internas, no sus
    // elementos: por eso se recorre el iterable tal cual.
    $agrupado = collect([
        '0 a 40' => collect([(object) [
            'total_persons' => 3,
            'subtotal_anual' => 480,
            'subtotal_biannual' => 260,
            'subtotal_quarterly' => 140,
        ]]),
    ]);

    expect(QuotePdfPlanStructure::flatRateRows($agrupado))->toBe([[
        'age_range' => '0 a 40',
        'total_persons' => 3,
        'annual' => 480.0,
        'biannual' => 260.0,
        'quarterly' => 140.0,
    ]])
        ->and(QuotePdfPlanStructure::flatRateRows(null))->toBe([]);
});

it('transcribe las condiciones y la nota que hoy viven dentro de la imagen', function (): void {
    $condiciones = QuotePdfPlanStructure::conditions();

    expect($condiciones)->not->toBeEmpty()
        ->and(implode(' ', $condiciones))->toContain('período de espera')
        ->and(implode(' ', $condiciones))->toContain('Zona de cobertura Venezuela')
        ->and(QuotePdfPlanStructure::acuteIllnessNote()['title'])->toBe('Beneficios para enfermedades agudas')
        ->and(QuotePdfPlanStructure::validityNote())->toContain('30 días');
});

it('deja la ruta a las plantillas históricas intacta en el documento', function (): void {
    $documento = (string) file_get_contents(
        dirname(__DIR__, 2).'/resources/views/documents/propuesta-economica.blade.php',
    );

    expect($documento)
        ->toContain("@if(\$layout === 'inicial')")
        ->toContain("@if(\$layout === 'ideal')")
        ->toContain("@if(\$layout === 'especial')")
        ->toContain('planes-cotizacion-individual-ideal')
        ->toContain('planes-cotizacion-individual-especial')
        // Y la rama nueva no se solapa con ellas.
        ->toContain('usesPlanStructure($layout)')
        ->toContain('planes-cotizacion-estructura');
});
