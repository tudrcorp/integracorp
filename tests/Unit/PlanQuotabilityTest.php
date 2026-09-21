<?php

declare(strict_types=1);

use App\Enums\PlanQuotableScope;
use App\Models\Plan;
use App\Models\User;
use App\Support\Plans\PlanQuotability;
use App\Support\Quotes\QuoteAgeRangeSelection;
use Tests\TestCase;

uses(TestCase::class);

it('reconoce planes dress tylor y deja los basicos fuera del filtro', function (): void {
    expect(PlanQuotability::isDressTylor('DRESS-TAILOR'))->toBeTrue()
        ->and(PlanQuotability::isDressTylor('dress-tailor'))->toBeTrue()
        ->and(PlanQuotability::isDressTylor('BASICO'))->toBeFalse();
});

it('los planes basicos activos siempre se pueden cotizar', function (): void {
    $plan = new Plan([
        'type' => 'BASICO',
        'status' => 'ACTIVO',
        'is_quotable' => false,
        'quotable_in' => null,
    ]);

    expect(PlanQuotability::isQuotableIn($plan, PlanQuotability::CHANNEL_INDIVIDUAL))->toBeTrue()
        ->and(PlanQuotability::isQuotableIn($plan, PlanQuotability::CHANNEL_CORPORATE))->toBeTrue();
});

it('un dress tylor inactivo o no habilitado no es cotizable', function (): void {
    $apagado = new Plan([
        'type' => 'DRESS-TAILOR',
        'status' => 'ACTIVO',
        'is_quotable' => false,
        'quotable_in' => PlanQuotableScope::Both,
    ]);

    $inactivo = new Plan([
        'type' => 'DRESS-TAILOR',
        'status' => 'INACTIVO',
        'is_quotable' => true,
        'quotable_in' => PlanQuotableScope::Both,
    ]);

    expect(PlanQuotability::isQuotableIn($apagado, PlanQuotability::CHANNEL_INDIVIDUAL))->toBeFalse()
        ->and(PlanQuotability::isQuotableIn($inactivo, PlanQuotability::CHANNEL_CORPORATE))->toBeFalse();
});

it('respeta el canal habilitado por el superadmin', function (string $scope, bool $individual, bool $corporate): void {
    $plan = new Plan([
        'type' => 'DRESS-TAILOR',
        'status' => 'ACTIVO',
        'is_quotable' => true,
        'quotable_in' => $scope,
    ]);

    expect(PlanQuotability::isQuotableIn($plan, PlanQuotability::CHANNEL_INDIVIDUAL))->toBe($individual)
        ->and(PlanQuotability::isQuotableIn($plan, PlanQuotability::CHANNEL_CORPORATE))->toBe($corporate);
})->with([
    'solo individual' => [PlanQuotableScope::Individual->value, true, false],
    'solo corporativo' => [PlanQuotableScope::Corporate->value, false, true],
    'ambos' => [PlanQuotableScope::Both->value, true, true],
]);

it('prepara atributos de formulario y limpia el alcance si no es cotizable', function (): void {
    expect(PlanQuotability::attributesFromForm([
        'is_quotable' => true,
        'quotable_in' => PlanQuotableScope::Individual->value,
    ]))->toBe([
        'is_quotable' => true,
        'quotable_in' => PlanQuotableScope::Individual->value,
    ]);

    expect(PlanQuotability::attributesFromForm([
        'is_quotable' => false,
        'quotable_in' => PlanQuotableScope::Both->value,
    ]))->toBe([
        'is_quotable' => false,
        'quotable_in' => null,
    ]);
});

it('etiqueta dress tylor en el listado de cotización', function (): void {
    $plan = new Plan([
        'id' => 99,
        'description' => 'Plan a medida',
        'type' => 'DRESS-TAILOR',
    ]);

    expect(PlanQuotability::optionLabel($plan))->toBe('Plan a medida (Dress Tylor)')
        ->and(PlanQuotability::tableLabel($plan))->toBe('No cotizable');
});

it('solo el superadmin puede configurar la cotizabilidad', function (): void {
    $super = User::factory()->make([
        'departament' => ['SUPERADMIN'],
    ]);
    $analista = User::factory()->make([
        'departament' => ['NEGOCIOS'],
    ]);

    expect(PlanQuotability::canConfigure($super))->toBeTrue()
        ->and(PlanQuotability::canConfigure($analista))->toBeFalse()
        ->and(PlanQuotability::canConfigure(null))->toBeFalse();
});

it('un usuario que no es superadmin no puede persistir la cotizabilidad', function (): void {
    $this->actingAs(User::factory()->make([
        'departament' => ['NEGOCIOS'],
    ]));

    $plan = new Plan([
        'type' => 'DRESS-TAILOR',
        'is_quotable' => true,
        'quotable_in' => PlanQuotableScope::Both,
    ]);

    PlanQuotability::normalizeOnSave($plan);

    expect($plan->is_quotable)->toBeFalse()
        ->and($plan->quotable_in)->toBeNull();
});

it('el superadmin puede persistir la cotizabilidad de un dress tylor', function (): void {
    $this->actingAs(User::factory()->make([
        'departament' => ['SUPERADMIN'],
    ]));

    $plan = new Plan([
        'type' => 'DRESS-TAILOR',
        'is_quotable' => true,
        'quotable_in' => PlanQuotableScope::Individual,
    ]);

    PlanQuotability::normalizeOnSave($plan);

    expect($plan->is_quotable)->toBeTrue()
        ->and($plan->quotable_in)->toBe(PlanQuotableScope::Individual);
});

it('un plan basico nunca guarda flags de cotizabilidad dress tylor', function (): void {
    $plan = new Plan([
        'type' => 'BASICO',
        'is_quotable' => true,
        'quotable_in' => PlanQuotableScope::Both,
    ]);

    PlanQuotability::normalizeOnSave($plan);

    expect($plan->is_quotable)->toBeFalse()
        ->and($plan->quotable_in)->toBeNull();
});

it('consulta individual incluye basicos y dress tylor habilitados en ese canal', function (): void {
    $query = PlanQuotability::queryForIndividual();

    expect($query->toSql())
        ->toContain('is_quotable')
        ->toContain('quotable_in');

    expect($query->getBindings())
        ->toContain('ACTIVO')
        ->toContain('BASICO')
        ->toContain('DRESS-TAILOR')
        ->toContain(PlanQuotableScope::Individual->value)
        ->toContain(PlanQuotableScope::Both->value);
});

it('consulta corporativa de dress tylor exige el flag del superadmin', function (): void {
    $query = PlanQuotability::queryForCorporateType('DRESS-TAILOR');

    expect($query->toSql())
        ->toContain('is_quotable')
        ->toContain('quotable_in');

    expect($query->getBindings())
        ->toContain('DRESS-TAILOR')
        ->toContain(PlanQuotableScope::Corporate->value)
        ->toContain(PlanQuotableScope::Both->value)
        ->not->toContain('BASICO');
});

it('los planes dress tylor corporativos tienen filas de rango de edad', function (): void {
    $plan = Plan::query()
        ->where('type', 'DRESS-TAILOR')
        ->where('description', 'like', '%ESCOLAR AP 1K%')
        ->first();

    if ($plan === null) {
        $this->markTestSkipped('No hay un plan Dress Tylor ESCOLAR AP 1K en la base.');
    }

    $count = QuoteAgeRangeSelection::countForPlan((int) $plan->id);

    expect($count)->toBeGreaterThan(0)
        ->and(QuoteAgeRangeSelection::emptyRowsForPlan((int) $plan->id))->toHaveCount($count)
        ->and(QuoteAgeRangeSelection::optionsForPlan((int) $plan->id))->not->toBeEmpty();
});

it('el asistente de planes expone la sección solo para el superadmin', function (): void {
    $fuente = (string) file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Business/Resources/Plans/Schemas/PlanWizardForm.php',
    );

    expect($fuente)
        ->toContain('PlanQuotabilityFormSchema::section()')
        ->toContain('PlanQuotabilityFormSchema::syncTypeChange');
});

it('el cotizador individual de negocios usa la regla de cotizabilidad', function (): void {
    $fuente = (string) file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Business/Resources/IndividualQuotes/Schemas/IndividualQuoteForm.php',
    );

    expect($fuente)
        ->toContain('PlanQuotability::optionsForIndividual')
        ->toContain('PlanQuotability::descriptionsForIndividual')
        ->not->toContain("where('type', 'BASICO')");
});
