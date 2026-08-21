<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Plans\Schemas\PlanWizardForm;
use App\Models\Plan;
use App\Models\User;
use Filament\Schemas\Schema;

uses(Tests\TestCase::class);

function fuenteDelAsistenteDePlanes(): string
{
    return (string) file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Business/Resources/Plans/Schemas/PlanWizardForm.php',
    );
}

it('configura el asistente sin errores', function (): void {
    $this->actingAs(User::factory()->make());

    expect(PlanWizardForm::configure(Schema::make()))->toBeInstanceOf(Schema::class);
});

it('ordena los pasos con las coberturas antes de los límites y las tarifas', function (): void {
    $fuente = fuenteDelAsistenteDePlanes();

    $posiciones = [
        'identityStep' => strpos($fuente, 'self::identityStep()'),
        'coveragesStep' => strpos($fuente, 'self::coveragesStep()'),
        'benefitsStep' => strpos($fuente, 'self::benefitsStep()'),
        'ageRangesStep' => strpos($fuente, 'self::ageRangesStep()'),
    ];

    expect($posiciones['identityStep'])->toBeLessThan($posiciones['coveragesStep'])
        ->and($posiciones['coveragesStep'])->toBeLessThan($posiciones['benefitsStep'])
        ->and($posiciones['benefitsStep'])->toBeLessThan($posiciones['ageRangesStep']);
});

it('sincroniza las matrices al salir del paso de coberturas', function (): void {
    expect(fuenteDelAsistenteDePlanes())
        ->toContain('->afterValidation(function (Get $get, Set $set): void {')
        ->toContain('self::syncMatrices($get, $set)')
        ->toContain("\$set('plan_benefits', PlanStructureMatrix::syncRows(")
        ->toContain("\$set('plan_age_ranges', PlanStructureMatrix::syncRows(");
});

it('mantiene las celdas de las matrices bajo control del asistente', function (): void {
    // Las filas de las matrices se generan solas, una por cobertura: si el
    // analista pudiera agregarlas o borrarlas a mano, dejarían de coincidir con
    // las columnas del plan.
    $fuente = fuenteDelAsistenteDePlanes();

    expect(substr_count($fuente, '->addable(false)'))->toBe(2)
        ->and(substr_count($fuente, '->deletable(false)'))->toBe(2);
});

it('permite dejar un costo límite vacío pero exige la tarifa', function (): void {
    $fuente = fuenteDelAsistenteDePlanes();

    expect($fuente)
        ->toContain("->placeholder('Sin límite')")
        ->toContain('->nullable(),');

    $limite = strpos($fuente, "TextInput::make('limit')");
    $tarifa = strpos($fuente, "TextInput::make('rate')");

    expect($limite)->toBeLessThan($tarifa);
});

it('oculta las coberturas y la matriz de límites en un paquete de beneficios', function (): void {
    expect(fuenteDelAsistenteDePlanes())
        ->toContain('->visible(fn (Get $get): bool => self::usesCoverages($get))')
        ->toContain('->visible(fn (Get $get): bool => ! self::usesCoverages($get))')
        ->toContain("CheckboxList::make('package_benefit_ids')")
        ->toContain("Repeater::make('package_age_ranges')");
});

it('marca los planes creados con el asistente para no reescribir los históricos', function (): void {
    expect(fuenteDelAsistenteDePlanes())
        ->toContain("Hidden::make('structure_version')->default(Plan::STRUCTURE_VERSION_WIZARD)");

    $edit = (string) file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Business/Resources/Plans/Pages/EditPlan.php',
    );

    expect($edit)
        ->toContain('PlanWizardForm::configure($schema)')
        ->toContain('PlanForm::configure($schema)')
        ->toContain('$record->usesStructureWizard()');
});

it('considera históricos los planes sin versión de estructura', function (): void {
    $historico = new Plan;
    $historico->structure_version = 1;

    $nuevo = new Plan;
    $nuevo->structure_version = Plan::STRUCTURE_VERSION_WIZARD;

    expect($historico->usesStructureWizard())->toBeFalse()
        ->and($nuevo->usesStructureWizard())->toBeTrue()
        ->and((new Plan)->usesStructureWizard())->toBeFalse();
});
