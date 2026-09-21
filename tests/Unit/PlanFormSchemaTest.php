<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Plans\Schemas\PlanForm;
use App\Models\User;
use Filament\Schemas\Schema;
use Tests\TestCase;

uses(TestCase::class);

it('configura el schema del plan incluyendo coberturas generales', function (): void {
    // make() y no create(): el schema solo necesita un usuario autenticado, y
    // este test corre contra la base de desarrollo (tests/Unit no usa
    // RefreshDatabase), así que persistirlo dejaba un usuario huérfano por corrida.
    $this->actingAs(User::factory()->make());

    $schema = Schema::make();
    $configured = PlanForm::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('asigna al select el rango o cobertura creados en modo paquete', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Plans/Schemas/PlanForm.php');

    expect($source)
        ->toContain("\$set('coverage_id', \$coverage->id)")
        ->toContain("\$set('age_range_id', \$ageRange->id)")
        ->toContain("'plan_id' => 0")
        ->toContain('create_general_plan_age_range')
        ->toContain('create_general_plan_coverage');
});

it('incluye la cotizabilidad dress tylor controlada por el superadmin', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Plans/Schemas/PlanForm.php');

    expect($source)
        ->toContain('PlanQuotabilityFormSchema::section()')
        ->toContain('PlanQuotabilityFormSchema::syncTypeChange');
});
