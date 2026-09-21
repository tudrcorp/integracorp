<?php

declare(strict_types=1);

use App\Enums\PlanPricingMode;
use App\Filament\Business\Resources\Plans\Pages\CreatePlan;
use App\Models\Plan;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Montar la página de verdad es lo único que delata errores de API de Filament
 * que un test de esquema no ve: un icono inexistente, un paso que no acepta
 * `visible()` o un `descriptions()` mal armado.
 *
 * Es de solo lectura: montar el asistente y validar el primer paso no escribe
 * nada. No va en tests/Feature porque ese suite no puede construir el esquema
 * desde cero (hay tablas sin migración de creación).
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

it('monta el asistente de creación de planes', function (): void {
    Livewire::actingAs($this->analista)
        ->test(CreatePlan::class)
        ->assertOk()
        ->assertSee('Coberturas del plan')
        ->assertSee('Beneficios y costos límite')
        ->assertSee('Rangos de edad y tarifas');
});

it('arranca en modo con coberturas y marcado como plan del asistente', function (): void {
    Livewire::actingAs($this->analista)
        ->test(CreatePlan::class)
        ->assertSchemaStateSet([
            'pricing_mode' => PlanPricingMode::Coberturas->value,
            'structure_version' => Plan::STRUCTURE_VERSION_WIZARD,
        ]);
});

it('exige el nombre del plan antes de avanzar de paso', function (): void {
    Livewire::actingAs($this->analista)
        ->test(CreatePlan::class)
        ->fillForm(['description' => null])
        ->goToNextWizardStep()
        ->assertHasFormErrors(['description']);
});

it('cambia a paquete de beneficios y oculta el paso de coberturas', function (): void {
    Livewire::actingAs($this->analista)
        ->test(CreatePlan::class)
        ->set('data.pricing_mode', PlanPricingMode::Paquete->value)
        ->assertOk()
        ->assertDontSee('Coberturas del plan')
        ->assertSee('Beneficios incluidos en el paquete');
});
