<?php

declare(strict_types=1);

use App\Filament\Business\Resources\PlanGenerators\Pages\EditPlanGenerator;
use App\Filament\Business\Resources\PlanGenerators\Pages\ViewPlanGenerator;
use App\Models\PlanGenerator;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Solo monta las pantallas (lectura): el usuario es `make()` y no se guarda nada.
 */
it('editar y ver una cotización abren con la sección de upgrades', function (): void {
    if (! Schema::hasTable('plan_generator_upgrades')) {
        $this->markTestSkipped('La tabla plan_generator_upgrades no existe en esta conexión.');
    }

    $plan = PlanGenerator::query()->whereHas('rateRows')->orderByDesc('id')->first();

    if ($plan === null) {
        $this->markTestSkipped('No hay cotizaciones del generador para montar.');
    }

    Filament::setCurrentPanel('business');
    $this->actingAs(User::factory()->make([
        'id' => 999_778,
        'name' => 'Superadmin Pest',
        'email' => 'superadmin.pest@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'NEGOCIOS'],
    ]));

    Livewire::test(EditPlanGenerator::class, ['record' => $plan->getKey()])
        ->assertSuccessful()
        ->assertSee('Upgrades opcionales')
        ->assertSet('data.upgrades', []);

    Livewire::test(ViewPlanGenerator::class, ['record' => $plan->getKey()])
        ->assertSuccessful();
});

it('al elegir un upgrade guía al analista: población por rango, incluir o excluir y recálculo', function (): void {
    if (! Schema::hasTable('plan_generator_upgrades') || ! Schema::hasColumn('plan_generator_upgrades', 'excluded_ranges')) {
        $this->markTestSkipped('Faltan las tablas de upgrades en esta conexión.');
    }

    $plan = PlanGenerator::query()
        ->whereHas('rateRows', fn ($query) => $query->where('population', '>', 0))
        ->whereDoesntHave('upgrades')
        ->orderByDesc('id')
        ->first();
    $coverageId = App\Models\UpgradeCoverage::query()
        ->where('status', 'ACTIVO')
        ->whereHas('upgrade', fn ($query) => $query->where('status', 'ACTIVO'))
        ->whereHas('activeAgeRanges')
        ->value('id');

    if ($plan === null || $coverageId === null) {
        $this->markTestSkipped('No hay cotización o upgrade activo para probar.');
    }

    $label = (string) $plan->rateRows()->where('population', '>', 0)->value('age_range_label');

    Filament::setCurrentPanel('business');
    $this->actingAs(User::factory()->make([
        'id' => 999_779,
        'name' => 'Superadmin Pest',
        'email' => 'superadmin.pest@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'NEGOCIOS'],
    ]));

    $component = Livewire::test(EditPlanGenerator::class, ['record' => $plan->getKey()])
        ->set('data.upgrades', ['fila-prueba' => ['line_key' => 'linea-prueba', 'upgrade_coverage_id' => $coverageId, 'excluded_ranges' => []]])
        ->assertSee('Paso 2 · ¿A quién se le agrega?')
        ->assertSee('Paso 3 · Recálculo del total grupal')
        ->assertSee('Total anual con upgrades')
        ->call('togglePlanGeneratorUpgradeRange', 'data.upgrades.fila-prueba', $label)
        ->assertSet('data.upgrades.fila-prueba.excluded_ranges', [App\Support\PlanGenerators\PlanGeneratorUpgrades::rangeKey($label)])
        ->call('togglePlanGeneratorUpgradeRange', 'data.columns', $label)
        ->call('includeAllPlanGeneratorUpgradeRanges', 'data.upgrades.fila-prueba')
        ->assertSet('data.upgrades.fila-prueba.excluded_ranges', []);

    expect($component->get('data.columns'))->toBeArray();
});

it('incluir a mano un rango cubierto en parte pide cuántas personas y una nota, con el precio fijo del upgrade', function (): void {
    if (! Schema::hasTable('plan_generator_upgrades') || ! Schema::hasColumn('plan_generator_upgrades', 'manual_ranges')) {
        $this->markTestSkipped('Faltan las tablas de upgrades en esta conexión.');
    }

    $candidate = null;

    foreach (App\Models\UpgradeCoverage::query()->where('status', 'ACTIVO')->whereHas('upgrade', fn ($query) => $query->where('status', 'ACTIVO'))->get() as $coverage) {
        $line = App\Support\PlanGenerators\PlanGeneratorUpgrades::resolveLines(null, [['line_key' => 'x', 'upgrade_coverage_id' => $coverage->id]])['lines'][0] ?? null;

        if ($line === null) {
            continue;
        }

        foreach (PlanGenerator::query()->whereHas('rateRows')->whereDoesntHave('upgrades')->orderByDesc('id')->limit(20)->get() as $plan) {
            $rows = App\Support\PlanGenerators\PlanGeneratorUpgrades::quote([$line], App\Support\PlanGenerators\PlanGeneratorPreviewBuilder::rateRows($plan))['upgrades'][0]['rows'];
            $partial = collect($rows)->first(fn (array $row): bool => $row['status'] === App\Support\PlanGenerators\PlanGeneratorUpgrades::STATUS_PARTIAL && $row['population'] > 0);

            if ($partial !== null) {
                $candidate = [$plan, $coverage->id, $partial];

                break 2;
            }
        }
    }

    if ($candidate === null) {
        $this->markTestSkipped('No hay un upgrade que cubra en parte algún rango de las cotizaciones de prueba.');
    }

    [$plan, $coverageId, $row] = $candidate;

    Filament::setCurrentPanel('business');
    $this->actingAs(User::factory()->make([
        'id' => 999_780,
        'name' => 'Superadmin Pest',
        'email' => 'superadmin.pest@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'NEGOCIOS'],
    ]));

    $arguments = ['path' => 'data.upgrades.fila-prueba', 'label' => $row['label']];

    Livewire::test(EditPlanGenerator::class, ['record' => $plan->getKey()])
        ->set('data.upgrades', ['fila-prueba' => ['line_key' => 'linea-prueba', 'upgrade_coverage_id' => $coverageId, 'excluded_ranges' => [], 'manual_ranges' => []]])
        ->assertSee('Incluir personas de '.$row['covered'])
        ->mountAction('includeUpgradeRangeManually', $arguments)
        ->set('mountedActions.0.data.persons', $row['population'] + 1)
        ->set('mountedActions.0.data.note', 'corta')
        ->callMountedAction()
        ->assertHasActionErrors(['persons', 'note'])
        ->set('mountedActions.0.data.persons', 1)
        ->set('mountedActions.0.data.note', 'Según el padrón del cliente, una persona tiene la edad')
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertSet('data.upgrades.fila-prueba.manual_ranges.0.persons', 1)
        ->assertSet('data.upgrades.fila-prueba.manual_ranges.0.by', 'Superadmin Pest')
        ->assertSee('Incluido a mano')
        ->call('removePlanGeneratorUpgradeManualRange', 'data.upgrades.fila-prueba', $row['label'])
        ->assertSet('data.upgrades.fila-prueba.manual_ranges', []);
});
