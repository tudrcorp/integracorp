<?php

declare(strict_types=1);

use App\Filament\Business\Resources\PlanGenerators\Pages\CreatePlanGenerator;
use App\Models\Coverage;
use App\Models\Plan;
use App\Models\User;
use App\Support\PlanGenerators\PlanGeneratorStructureImporter;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * El punto del cambio es que el analista elija un plan y no tenga que teclear la
 * matriz. Estos tests montan la página de verdad y disparan la acción, que es lo
 * único que prueba que el volcado llega al estado del formulario.
 *
 * De solo lectura: se monta y se dispara la acción, nunca se guarda.
 */
beforeEach(function (): void {
    Filament::setCurrentPanel('business');

    $analista = User::query()
        ->where('email', 'like', '%@tudrencasa.com')
        ->where('status', 'ACTIVO')
        ->get()
        ->first(fn (User $user): bool => in_array('NEGOCIOS', (array) ($user->departament ?? []), true)
            || in_array('SUPERADMIN', (array) ($user->departament ?? []), true));

    if ($analista === null) {
        $this->markTestSkipped('No hay un analista de Negocios activo en la base para montar el panel.');
    }

    $this->analista = $analista;

    $this->planConCoberturas = Plan::query()->get()->first(
        fn (Plan $plan): bool => $plan->pricingMode()->usesCoverages()
            && $plan->coverages()->exists()
            && \App\Models\Fee::query()->where('plan_id', $plan->getKey())->whereNotNull('coverage_id')->exists(),
    );

    if ($this->planConCoberturas === null) {
        $this->markTestSkipped('No hay un plan con coberturas y tarifas en la base.');
    }
});

it('muestra el selector de plan del catálogo', function (): void {
    Livewire::actingAs($this->analista)
        ->test(CreatePlanGenerator::class)
        ->assertOk()
        ->assertSee('Estructura desde un plan del catálogo')
        ->assertSee('Cargar estructura del plan');
});

it('vuelca columnas, beneficios y tarifas del plan en el formulario', function (): void {
    $plan = $this->planConCoberturas;
    $coberturas = Coverage::query()->where('plan_id', $plan->getKey())->pluck('id')->all();
    $esperado = PlanGeneratorStructureImporter::build($plan);

    $componente = Livewire::actingAs($this->analista)
        ->test(CreatePlanGenerator::class)
        ->set('data.plan_id', $plan->getKey())
        ->callAction(TestAction::make('import_plan_structure')->schemaComponent('plan_id'), [
            'coverage_ids' => array_map(fn (mixed $id): int => (int) $id, $coberturas),
        ]);

    $columnas = $componente->get('data.columns');
    $filas = $componente->get('data.rows');
    $tarifas = $componente->get('data.rate_rows');

    expect($columnas)->toHaveCount(count($esperado['columns']))
        ->and(array_column($columnas, 'header_label'))->toBe(array_column($esperado['columns'], 'header_label'))
        ->and($filas)->toHaveCount($esperado['summary']['benefits'])
        ->and($tarifas)->toHaveCount($esperado['summary']['age_ranges']);

    // Cada fila queda con una celda por columna: si no, la matriz se desalinea.
    $clavesDeColumna = array_column($columnas, 'column_key');

    foreach ($filas as $fila) {
        expect(array_keys($fila['cells']))->toBe($clavesDeColumna);
    }

    foreach ($tarifas as $fila) {
        expect(array_keys($fila['cells']))->toBe($clavesDeColumna)
            // La población es del cliente, nunca viene del plan.
            ->and($fila['population'])->toBeNull();
    }
});

it('carga solo el subconjunto de coberturas elegido', function (): void {
    $plan = $this->planConCoberturas;
    $primera = Coverage::query()->where('plan_id', $plan->getKey())->orderBy('price')->firstOrFail();

    $componente = Livewire::actingAs($this->analista)
        ->test(CreatePlanGenerator::class)
        ->set('data.plan_id', $plan->getKey())
        ->mountAction(TestAction::make('import_plan_structure')->schemaComponent('plan_id'))
        // El modal viene con todas las coberturas marcadas; acá se simula que
        // el analista deja solo una.
        ->set('mountedActions.0.data.coverage_ids', [(string) $primera->id])
        ->callMountedAction();

    expect($componente->get('data.columns'))->toHaveCount(1)
        ->and($componente->get('data.columns')[0]['header_label'])
        ->toContain(App\Support\PlanGenerators\PlanGeneratorStructureImporter::abbreviateAmount((float) $primera->price));
});

it('reemplaza la matriz cargada a mano en vez de mezclarla', function (): void {
    $plan = $this->planConCoberturas;
    $coberturas = Coverage::query()->where('plan_id', $plan->getKey())->pluck('id')->all();

    $componente = Livewire::actingAs($this->analista)
        ->test(CreatePlanGenerator::class)
        ->call('addMatrixRow')
        ->call('addRateRow')
        ->set('data.plan_id', $plan->getKey())
        ->callAction(TestAction::make('import_plan_structure')->schemaComponent('plan_id'), [
            'coverage_ids' => array_map(fn (mixed $id): int => (int) $id, $coberturas),
        ]);

    $esperado = PlanGeneratorStructureImporter::build($plan);

    // Si mezclara, quedarían las filas vacías agregadas a mano.
    expect($componente->get('data.rows'))->toHaveCount($esperado['summary']['benefits'])
        ->and($componente->get('data.rate_rows'))->toHaveCount($esperado['summary']['age_ranges']);
});

it('no toca las páginas de la cotización al cargar la estructura', function (): void {
    $plan = $this->planConCoberturas;
    $coberturas = Coverage::query()->where('plan_id', $plan->getKey())->pluck('id')->all();

    $componente = Livewire::actingAs($this->analista)
        ->test(CreatePlanGenerator::class)
        ->set('data.client_data', 'CLIENTE DE PRUEBA')
        ->set('data.plan_id', $plan->getKey())
        ->callAction(TestAction::make('import_plan_structure')->schemaComponent('plan_id'), [
            'coverage_ids' => array_map(fn (mixed $id): int => (int) $id, $coberturas),
        ]);

    expect($componente->get('data.client_data'))->toBe('CLIENTE DE PRUEBA');
});

it('propone todas las coberturas del plan al abrir el modal', function (): void {
    $plan = $this->planConCoberturas;
    $esperadas = Coverage::query()
        ->where('plan_id', $plan->getKey())
        ->orderBy('price')
        ->pluck('id')
        ->map(fn (mixed $id): string => (string) $id)
        ->all();

    $componente = Livewire::actingAs($this->analista)
        ->test(CreatePlanGenerator::class)
        ->set('data.plan_id', $plan->getKey())
        ->mountAction(TestAction::make('import_plan_structure')->schemaComponent('plan_id'));

    $datos = (array) $componente->get('mountedActions')[0]['data'];

    expect((int) $datos['plan_id'])->toBe((int) $plan->getKey())
        ->and(array_map(fn (mixed $id): string => (string) $id, (array) $datos['coverage_ids']))
        ->toEqualCanonicalizing($esperadas);
});

it('no importa nada si el analista deselecciona todas las coberturas', function (): void {
    $plan = $this->planConCoberturas;

    $componente = Livewire::actingAs($this->analista)
        ->test(CreatePlanGenerator::class)
        ->set('data.plan_id', $plan->getKey())
        ->mountAction(TestAction::make('import_plan_structure')->schemaComponent('plan_id'))
        ->set('mountedActions.0.data.coverage_ids', [])
        ->callMountedAction();

    // Vacío no puede significar "todas": sería volcar el plan completo sin que
    // el analista lo haya pedido.
    expect((array) $componente->get('data.columns'))->toBe([]);
});
