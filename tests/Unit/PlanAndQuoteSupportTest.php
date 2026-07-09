<?php

declare(strict_types=1);

use App\Support\IndividualQuotePdfLayout;
use App\Support\PlanCreationPersistence;
use Tests\TestCase;

uses(TestCase::class);

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

it('persiste tarifa de paquete en fees con cobertura nula y rango 1 a 50', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Support/PlanCreationPersistence.php');

    expect($source)
        ->toContain('syncPackageQuoteFees')
        ->toContain("PACKAGE_QUOTE_AGE_RANGE = '1 a 50'")
        ->toContain('->whereNull(\'coverage_id\')')
        ->toContain('createFees: false');
});

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
