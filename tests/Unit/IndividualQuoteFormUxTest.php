<?php

declare(strict_types=1);

use App\Filament\Business\Resources\IndividualQuotes\Pages\CreateIndividualQuote;
use App\Models\User;
use App\Support\Plans\PlanQuotability;
use App\Support\Quotes\QuoteAgeRangeSelection;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Pestañas «Planes a cotizar» y «Rango de edad» del cotizador individual: una fila por rango
 * del plan, ya elegida, y botones de 0 a 10 personas. Usa el Plan Especial real (id 3).
 */
beforeEach(function (): void {
    DB::beginTransaction();

    if (! DB::table('age_ranges')->where('plan_id', 3)->exists()) {
        $this->markTestSkipped('Esta base no tiene el Plan Especial de referencia.');
    }

    $this->actingAs(User::factory()->create([
        'email' => 'qa.cotizador.ux.'.uniqid().'@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'NEGOCIOS'],
    ]));
    Filament::setCurrentPanel('business');
});

afterEach(function (): void {
    DB::rollBack();
});

it('prepara una fila por rango del plan, en orden de edad, y conserva las personas ya indicadas', function (): void {
    $rows = QuoteAgeRangeSelection::presetRowsForPlan(3);

    expect(array_column($rows, 'range_label'))->toBe(['0 A 30', '31 a 65', '66 a 74', '75 a 85'])
        ->and(array_column($rows, 'age_range_id'))->toBe([5, 6, 7, 8])
        ->and(array_unique(array_column($rows, 'total_persons')))->toBe([null]);

    $kept = QuoteAgeRangeSelection::presetRowsForPlan(3, [['age_range_id' => 6, 'total_persons' => 2]]);

    expect($kept[1]['total_persons'])->toBe(2)
        ->and(QuoteAgeRangeSelection::presetRowsIfMissing('3', $rows))->toBeNull()
        ->and(QuoteAgeRangeSelection::presetRowsIfMissing('3', [['age_range_id' => null, 'total_persons' => null]]))->toHaveCount(4)
        ->and(QuoteAgeRangeSelection::presetRowsIfMissing('CM', $rows))->toBe([]);
});

it('cada plan muestra sus rangos de edad y la cotización múltiple explica para qué sirve', function (): void {
    $descriptions = PlanQuotability::descriptionsForIndividual();

    expect($descriptions[3] ?? null)->toBe('Edades: 0 A 30 · 31 a 65 · 66 a 74 · 75 a 85 años.')
        ->and($descriptions['CM'])->toBe('Combine dos o más planes en una misma propuesta.');
});

it('al elegir el plan muestra sus rangos listos, los botones de personas y el resumen en vivo', function (): void {
    $page = Livewire::test(CreateIndividualQuote::class)
        ->assertSee('¿Qué plan vas a cotizar?')
        ->set('data.plan', '3')
        ->assertSee('Personas por rango de edad')
        ->assertSee('Siguiente: en «Rango de edad» marque cuántas personas hay en cada rango.')
        ->assertSee('Todavía no hay personas.');

    $rows = array_values($page->get('data.details_quote'));

    expect(array_column($rows, 'range_label'))->toBe(['0 A 30', '31 a 65', '66 a 74', '75 a 85']);

    $keys = array_keys($page->get('data.details_quote'));
    $page->set('data.details_quote.'.$keys[0].'.total_persons', 2)
        ->set('data.details_quote.'.$keys[2].'.total_persons', 1)
        ->assertSee('3 personas en 2 rangos de edad.');
});

it('no deja crear sin personas en ningún rango', function (): void {
    Livewire::test(CreateIndividualQuote::class)
        ->set('data.full_name', 'PRUEBA UX')
        ->set('data.plan', '3')
        ->call('create')
        ->assertHasErrors(['data.details_quote']);
});
