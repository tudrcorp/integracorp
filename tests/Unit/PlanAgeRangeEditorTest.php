<?php

declare(strict_types=1);

use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\AfilliationCorporatePlan;
use App\Models\AgeRange;
use App\Models\Fee;
use App\Models\Plan;
use App\Models\User;
use App\Support\AffiliationCorporates\CorporateAffiliatePlanSynchronizer;
use App\Support\Plans\PlanAgeRangeEditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(Tests\TestCase::class);

/**
 * Escriben en la base: transacción revertida, correo y cola simulados.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Mail::fake();
    Queue::fake();
});

afterEach(function (): void {
    DB::rollBack();
});

function areActor(): User
{
    return User::factory()->make(['id' => 987920, 'name' => 'Analista Rangos Pest']);
}

/**
 * Plan paquete con un rango propio 1–99 (US$ 163,20) usado por una afiliación
 * corporativa con afiliados de 0, 50 y 70 años.
 *
 * @return array{plan: Plan, range: AgeRange, fee: Fee, owner: AffiliationCorporate, row: AfilliationCorporatePlan, baby: AffiliateCorporate, senior: AffiliateCorporate}
 */
function areFixture(): array
{
    $plan = Plan::query()->findOrFail(26)->replicate();
    $plan->forceFill(['code' => 'PEST-PL-'.Str::upper(Str::random(6)), 'description' => 'PLAN RANGOS PEST'])->saveQuietly();

    $range = new AgeRange;
    $range->forceFill(['plan_id' => $plan->id, 'coverage_id' => null, 'range' => '1 a 99', 'age_init' => 1, 'age_end' => 99, 'status' => 'ACTIVO', 'created_by' => 'pest'])->save();

    $fee = Fee::query()->findOrFail(67)->replicate();
    $fee->forceFill(['plan_id' => $plan->id, 'age_range_id' => $range->id, 'range' => '1 a 99', 'price' => 163.20, 'coverage_id' => null, 'status' => 'ACTIVO'])->saveQuietly();
    DB::table('fee_plans')->insert(['fee_id' => $fee->id, 'plan_id' => $plan->id, 'range' => '1 a 99', 'price' => 163.20, 'created_at' => now(), 'updated_at' => now()]);

    $sourceRow = AfilliationCorporatePlan::query()->firstOrFail();
    $owner = AffiliationCorporate::query()->findOrFail($sourceRow->affiliation_corporate_id)->replicate();
    $owner->forceFill(['code' => 'PEST-COR-'.Str::upper(Str::random(6)), 'name_corporate' => 'EMPRESA RANGOS PEST'])->save();

    $row = $sourceRow->replicate();
    $row->forceFill(['affiliation_corporate_id' => $owner->id, 'plan_id' => $plan->id, 'coverage_id' => null, 'age_range_id' => $range->id, 'fee' => 163.20, 'total_persons' => 3])->save();

    $make = function (string $first, int $age) use ($owner, $plan): AffiliateCorporate {
        $affiliate = new AffiliateCorporate;
        $affiliate->forceFill([
            'affiliation_corporate_id' => $owner->id, 'affiliation_type' => 'ESTANDARD', 'first_name' => $first, 'last_name' => 'RANGOPEST ZUBIRI',
            'nro_identificacion' => (string) random_int(80000000, 89999999), 'age' => $age, 'condition_medical' => 'SANO',
            'plan_id' => $plan->id, 'coverage_id' => null, 'fee' => 163.20, 'subtotal_anual' => 163.20, 'payment_frequency' => 'ANUAL', 'status' => 'ACTIVO', 'created_by' => 1,
        ])->save();

        return $affiliate;
    };

    $baby = $make('BEBE', 0);
    $make('ADULTO', 50);
    $senior = $make('MAYOR', 70);

    return compact('plan', 'range', 'fee', 'owner', 'row', 'baby', 'senior');
}

it('amplía el rango en todo el sistema: rango, textos copiados y resolución del plan por edad', function (): void {
    $f = areFixture();
    $before = CorporateAffiliatePlanSynchronizer::resolvePlanRowForAffiliate($f['owner'], $f['baby']);
    expect($before['row'])->toBeNull();

    $impact = PlanAgeRangeEditor::impact($f['plan'], $f['range'], 0, 99);
    expect($impact['blocking'])->toBe([])
        ->and($impact['newly_covered'])->toBe(1)
        ->and(collect($impact['usage'])->firstWhere('label', 'Afiliaciones corporativas')['detail'])->toContain($f['owner']->code);

    $result = PlanAgeRangeEditor::apply($f['plan'], $f['range'], 0, 99, 'El contrato cubre desde los 0 años; hay recién nacidos.', areActor());

    expect($result['after']['range'])->toBe('0 a 99')
        ->and($f['range']->fresh()->age_init)->toBe(0)
        ->and($f['range']->fresh()->range)->toBe('0 a 99')
        ->and($f['fee']->fresh()->range)->toBe('0 a 99')
        ->and((float) $f['fee']->fresh()->price)->toBe(163.20)
        ->and(DB::table('fee_plans')->where('fee_id', $f['fee']->id)->value('range'))->toBe('0 a 99')
        ->and(CorporateAffiliatePlanSynchronizer::resolvePlanRowForAffiliate($f['owner'], $f['baby'])['row']?->getKey())->toBe($f['row']->getKey())
        ->and(DB::table('logs')->where('action', 'AUDIT_PLAN_AGE_RANGE_EDITED')->where('response', 'like', '%"age_range_id":'.$f['range']->id.'%')->exists())->toBeTrue();
});

it('no achica el rango si deja afiliados vigentes sin rango, y los muestra', function (): void {
    $f = areFixture();

    $impact = PlanAgeRangeEditor::impact($f['plan'], $f['range'], 1, 65);

    expect($impact['left_out_total'])->toBe(1)
        ->and($impact['left_out'][0])->toContain('MAYOR RANGOPEST ZUBIRI', '70 años')
        ->and(fn () => PlanAgeRangeEditor::apply($f['plan'], $f['range'], 1, 65, 'Se reduce la edad máxima del contrato.', areActor()))
        ->toThrow(RuntimeException::class, 'quedarían sin rango');

    expect($f['range']->fresh()->age_end)->toBe(99)
        ->and($f['fee']->fresh()->range)->toBe('1 a 99');
});

it('se puede achicar cuando nadie vigente queda fuera', function (): void {
    $f = areFixture();
    $f['senior']->forceFill(['status' => 'INACTIVO'])->save();

    PlanAgeRangeEditor::apply($f['plan'], $f['range'], 1, 65, 'Se reduce la edad máxima del contrato.', areActor());

    expect($f['range']->fresh()->age_end)->toBe(65)
        ->and($f['fee']->fresh()->range)->toBe('1 a 65');
});

it('no permite cruzarse con otro rango del plan, rangos de otros planes, edades imposibles ni motivo corto', function (): void {
    $f = areFixture();

    $second = new AgeRange;
    $second->forceFill(['plan_id' => $f['plan']->id, 'range' => '100 a 110', 'age_init' => 100, 'age_end' => 110, 'status' => 'ACTIVO', 'created_by' => 'pest'])->save();
    $secondFee = $f['fee']->replicate();
    $secondFee->forceFill(['age_range_id' => $second->id, 'range' => '100 a 110'])->saveQuietly();

    expect(PlanAgeRangeEditor::impact($f['plan'], $f['range'], 1, 105)['blocking'][0])->toContain('Se cruzaría con el rango «100 a 110»')
        ->and(PlanAgeRangeEditor::impact($f['plan'], $f['range'], 50, 10)['blocking'][0])->toContain('edad inicial no puede ser mayor')
        ->and(fn () => PlanAgeRangeEditor::apply($f['plan'], $f['range'], 0, 99, 'corto', areActor()))->toThrow(RuntimeException::class, 'mínimo');

    $otherPlan = $f['plan']->replicate();
    $otherPlan->forceFill(['code' => 'PEST-PL-OTRO'.Str::upper(Str::random(3)), 'description' => 'OTRO PLAN PEST'])->saveQuietly();
    $foreignFee = $f['fee']->replicate();
    $foreignFee->forceFill(['plan_id' => $otherPlan->id])->saveQuietly();

    expect(PlanAgeRangeEditor::impact($f['plan'], $f['range'], 0, 99)['blocking'][0])->toContain('también lo usan otros planes', 'OTRO PLAN PEST');
});

it('un rango en uso no se puede borrar desde el catálogo', function (): void {
    $f = areFixture();
    $free = new AgeRange;
    $free->forceFill(['plan_id' => 0, 'range' => '5 a 6', 'age_init' => 5, 'age_end' => 6, 'status' => 'ACTIVO', 'created_by' => 'pest'])->save();

    expect(PlanAgeRangeEditor::isReferenced($f['range']))->toBeTrue()
        ->and(PlanAgeRangeEditor::isReferenced($free))->toBeFalse();
});

it('la ficha del plan ofrece «Editar rango de edad» y guarda en todo el sistema', function (): void {
    Filament\Facades\Filament::setCurrentPanel('business');
    $f = areFixture();
    $user = User::factory()->make(['id' => 987921, 'name' => 'Usuario Rangos Pest', 'email' => 'rangos-pest-'.uniqid().'@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => ['SUPERADMIN', 'NEGOCIOS']]);

    $component = Livewire\Livewire::actingAs($user)
        ->test(App\Filament\Business\Resources\Plans\Pages\ViewPlan::class, ['record' => $f['plan']->id])
        ->assertOk()
        ->assertActionVisible('editAgeRange')
        ->mountAction('editAgeRange');

    expect((int) $component->get('mountedActions.0.data.age_range_id'))->toBe((int) $f['range']->id)
        ->and((int) $component->get('mountedActions.0.data.age_init'))->toBe(1);

    $component
        ->set('mountedActions.0.data.age_init', 0)
        ->call('callMountedAction')
        ->assertHasErrors(['mountedActions.0.data.reason'])
        ->set('mountedActions.0.data.reason', 'El contrato cubre desde los 0 años; hay recién nacidos.')
        ->call('callMountedAction')
        ->assertHasNoErrors()
        ->assertNotified('Rango de edad actualizado');

    expect($f['range']->fresh()->range)->toBe('0 a 99');
});
