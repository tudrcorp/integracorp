<?php

declare(strict_types=1);

use App\Support\IndividualQuotePdfLayout;
use App\Support\PlanCreationPersistence;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Los tests marcados `integration-db` escriben en la base. Corren dentro de una
 * transacción que siempre se revierte: sin esto, cada corrida dejaba un plan
 * «Plan test asociacion rango» con su cobertura, su rango y su tarifa en la
 * base de desarrollo, porque tests/Unit no usa RefreshDatabase.
 */
beforeEach(function (): void {
    DB::beginTransaction();
});

afterEach(function (): void {
    DB::rollBack();
});

it('genera codigo de plan con formato esperado', function (): void {
    expect(PlanCreationPersistence::generatePlanCode())->toMatch('/^TDEC-PL-\d{4}$/');
});

it('normaliza categorias de plan al tipo de base de datos', function (): void {
    expect(PlanCreationPersistence::normalizePlanType('DRESS-TYLOR'))->toBe('DRESS-TAILOR');
    expect(PlanCreationPersistence::normalizePlanType('BASICO'))->toBe('BASICO');
});

it('prepara atributos del plan removiendo campos del formulario', function (): void {
    $prepared = PlanCreationPersistence::preparePlanAttributes([
        'description' => 'Plan prueba',
        'category' => 'BASICO',
        'is_package' => true,
        'package_benefit_ids' => [1],
        'general_coverages' => [],
        'benefits' => [],
    ]);

    expect($prepared)->toHaveKeys(['description', 'type', 'code', 'status', 'business_unit_id']);
    expect($prepared)->not->toHaveKeys(['is_package', 'package_benefit_ids', 'general_coverages', 'benefits', 'category']);
    expect($prepared['type'])->toBe('BASICO');
});

it('persiste tarifa de paquete en fees con cobertura nula sobre el rango elegido', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Support/PlanCreationPersistence.php');

    expect($source)
        ->toContain('syncPackageQuoteFees')
        ->toContain('->whereNull(\'coverage_id\')')
        ->toContain('createFees: false')
        ->not->toContain("PACKAGE_QUOTE_AGE_RANGE = '1 a 50'")
        ->not->toContain('ensurePackageAgeRange');
});

it('asocia el rango de edad del catalogo al plan en modo paquete', function (): void {
    $user = \App\Models\User::query()->first();

    if ($user === null) {
        $this->markTestSkipped('No hay usuarios en la base de datos.');
    }

    $this->actingAs($user);

    $benefit = \App\Models\Benefit::query()->first();

    if ($benefit === null) {
        $this->markTestSkipped('No hay beneficios en la base de datos.');
    }

    $coverage = \App\Models\Coverage::query()->create([
        'price' => 1000,
        'plan_id' => null,
        'status' => 'ACTIVO',
        'created_by' => $user->name,
    ]);

    $ageRange = \App\Models\AgeRange::query()->create([
        'range' => '0 A 99',
        'age_init' => 0,
        'age_end' => 99,
        'plan_id' => 0,
        'status' => 'ACTIVO',
        'created_by' => $user->name,
    ]);

    $plan = \App\Models\Plan::query()->create([
        'code' => PlanCreationPersistence::generatePlanCode(),
        'description' => 'Plan test asociacion rango',
        'type' => 'BASICO',
        'business_unit_id' => 1,
        'status' => 'ACTIVO',
        'created_by' => $user->name,
    ]);

    PlanCreationPersistence::persistRelations($plan, [
        'is_package' => true,
        'package_benefit_ids' => [$benefit->id],
        'general_coverages' => [
            [
                'coverage_id' => $coverage->id,
                'age_rates' => [
                    [
                        'age_range_id' => $ageRange->id,
                        'rate' => 180,
                    ],
                ],
            ],
        ],
    ]);

    $ageRange->refresh();
    $coverage->refresh();

    expect((int) $ageRange->plan_id)->toBe((int) $plan->id)
        ->and((string) $ageRange->fee)->toBe('180')
        ->and($ageRange->range)->toBe('0 A 99')
        ->and((int) $coverage->plan_id)->toBe((int) $plan->id);

    expect(\App\Models\AgeRange::query()
        ->where('plan_id', $plan->id)
        ->where('range', '1 a 50')
        ->exists())->toBeFalse();

    expect(\App\Models\Fee::query()
        ->where('age_range_id', $ageRange->id)
        ->whereNull('coverage_id')
        ->where('price', 180)
        ->exists())->toBeTrue();
})->group('integration-db');

it('hidrata datos del formulario de edicion para planes en modo paquete', function (): void {
    $plan = \App\Models\Plan::query()
        ->whereHas('benefitPlans')
        ->whereHas('coverages')
        ->first();

    if ($plan === null) {
        $this->markTestSkipped('No hay planes con beneficios y coberturas en la base de datos.');
    }

    $hydrated = PlanCreationPersistence::hydrateFormData($plan);

    expect($hydrated['is_package'])->toBeTrue();
    expect($hydrated['package_benefit_ids'])->not->toBeEmpty();
    expect($hydrated['general_coverages'])->not->toBeEmpty();
    expect($hydrated['general_coverages'][0])->toHaveKeys(['coverage_id', 'age_rates']);
})->group('integration-db');

it('hidrata datos del formulario de edicion para planes solo con beneficios', function (): void {
    $plan = \App\Models\Plan::query()
        ->whereHas('benefitPlans')
        ->whereDoesntHave('coverages')
        ->first();

    if ($plan === null) {
        $this->markTestSkipped('No hay planes con beneficios sin coberturas en la base de datos.');
    }

    $hydrated = PlanCreationPersistence::hydrateFormData($plan);

    expect($hydrated['is_package'])->toBeTrue();
    expect($hydrated['package_benefit_ids'])->not->toBeEmpty();
    expect($hydrated['general_coverages'])->toBe([]);
})->group('integration-db');

it('resuelve plantillas pdf legacy y por estructura', function (): void {
    expect(IndividualQuotePdfLayout::resolve(1))->toBe(IndividualQuotePdfLayout::Inicial);
    expect(IndividualQuotePdfLayout::resolve(2))->toBe(IndividualQuotePdfLayout::Ideal);
    expect(IndividualQuotePdfLayout::resolve(3))->toBe(IndividualQuotePdfLayout::Especial);
    expect(IndividualQuotePdfLayout::usesCoverageBreakdown(IndividualQuotePdfLayout::Ideal))->toBeTrue();
    expect(IndividualQuotePdfLayout::usesCoverageBreakdown(IndividualQuotePdfLayout::Inicial))->toBeFalse();
});

it('puede regenerar pdf de cotizacion individual existente con detalle', function (): void {
    $quote = \App\Models\IndividualQuote::query()
        ->whereHas('detailsQuote')
        ->latest('id')
        ->first();

    if ($quote === null) {
        $this->markTestSkipped('No hay cotizaciones individuales con detalle en la base de datos.');
    }

    $user = \App\Models\User::query()->first();
    $this->actingAs($user);

    $path = public_path('storage/quotes/'.$quote->code.'.pdf');
    if (file_exists($path)) {
        unlink($path);
    }

    expect(\App\Support\IndividualQuotePdfGenerator::regenerateIfMissing($quote))->toBeTrue();
    expect(file_exists($path))->toBeTrue();
    expect(filesize($path))->toBeGreaterThan(0);
})->group('integration-db');

it('puede regenerar pdf de cotizacion corporativa existente con detalle', function (): void {
    $quote = \App\Models\CorporateQuote::query()
        ->whereHas('detailCoporateQuotes')
        ->latest('id')
        ->first();

    if ($quote === null) {
        $this->markTestSkipped('No hay cotizaciones corporativas con detalle en la base de datos.');
    }

    $user = \App\Models\User::query()->first();
    $this->actingAs($user);

    $path = public_path('storage/quotes/'.$quote->code.'.pdf');
    if (file_exists($path)) {
        unlink($path);
    }

    expect(\App\Support\CorporateQuotePdfGenerator::regenerateIfMissing($quote))->toBeTrue();
    expect(file_exists($path))->toBeTrue();
    expect(filesize($path))->toBeGreaterThan(0);
})->group('integration-db');
