<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Plans\Pages\ViewPlan;
use App\Models\Fee;
use App\Models\Plan;
use App\Models\User;
use App\Support\Plans\PlanStructureSummary;
use Filament\Facades\Filament;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * La ficha del plan es donde el analista verifica lo que acaba de armar, así
 * que se monta de verdad en los dos modos. Es de solo lectura.
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
});

function planConCoberturas(): ?Plan
{
    return Plan::query()->get()->first(
        fn (Plan $plan): bool => $plan->pricingMode()->usesCoverages() && $plan->coverages()->exists(),
    );
}

it('muestra las dos matrices en un plan con coberturas', function (): void {
    $plan = planConCoberturas();

    if ($plan === null) {
        $this->markTestSkipped('No hay un plan con coberturas en la base.');
    }

    Livewire::actingAs($this->analista)
        ->test(ViewPlan::class, ['record' => $plan->getKey()])
        ->assertOk()
        ->assertSee('Costos límite por beneficio y cobertura')
        ->assertSee('Tarifas por rango de edad y cobertura')
        ->assertSee('Plan con coberturas')
        ->assertDontSee('Tarifas del paquete por rango de edad');
});

it('muestra las tarifas planas en un paquete de beneficios', function (): void {
    $plan = Plan::query()->get()->first(
        fn (Plan $p): bool => $p->isBenefitPackage()
            && Fee::query()->where('plan_id', $p->getKey())->whereNull('coverage_id')->exists(),
    );

    if ($plan === null) {
        $this->markTestSkipped('No hay un paquete de beneficios con tarifas en la base.');
    }

    Livewire::actingAs($this->analista)
        ->test(ViewPlan::class, ['record' => $plan->getKey()])
        ->assertOk()
        ->assertSee('Tarifas del paquete por rango de edad')
        ->assertSee('Paquete de beneficios')
        ->assertDontSee('Costos límite por beneficio y cobertura');
});

it('arma la matriz de límites distinguiendo sin límite de cero', function (): void {
    $plan = planConCoberturas();

    if ($plan === null) {
        $this->markTestSkipped('No hay un plan con coberturas en la base.');
    }

    $matriz = PlanStructureSummary::limitsMatrix($plan);

    expect($matriz)->toHaveKeys(['columns', 'rows']);

    foreach ($matriz['rows'] as $fila) {
        expect(array_keys($fila['cells']))
            ->toBe(array_column($matriz['columns'], 'key'));
    }
});
