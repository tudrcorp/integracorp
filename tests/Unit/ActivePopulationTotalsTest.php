<?php

declare(strict_types=1);

use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\AfilliationCorporatePlan;
use App\Models\AgeRange;
use App\Models\User;
use App\Support\AffiliationCorporates\ActivePopulationTotals;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('Requiere el esquema MySQL de desarrollo (transacción revertida).');
    }

    DB::beginTransaction();
    Mail::fake();
    Queue::fake();
});

afterEach(function (): void {
    DB::rollBack();
});

function aptActor(): User
{
    return User::factory()->make(['id' => 987950, 'name' => 'Analista Totales Pest']);
}

/**
 * Afiliación trimestral con totales guardados desfasados (5 pers., US$ 999) y una fila de plan
 * 0–99 de US$ 100. Activos: 2 ACTIVO con tarifa 100 y 1 PRE-APROBADA sin tarifa; 1 INACTIVO
 * y 1 eliminado que no cuentan.
 *
 * @return array{owner: AffiliationCorporate, row: AfilliationCorporatePlan}
 */
function aptFixture(): array
{
    $sourceRow = AfilliationCorporatePlan::query()->firstOrFail();
    $owner = AffiliationCorporate::query()->findOrFail($sourceRow->affiliation_corporate_id)->replicate();
    $owner->forceFill(['code' => 'PEST-COR-'.Str::upper(Str::random(6)), 'name_corporate' => 'EMPRESA TOTALES PEST', 'poblation' => 5, 'fee_anual' => 999, 'total_amount' => 249.75, 'payment_frequency' => 'TRIMESTRAL', 'status' => 'ACTIVA'])->save();

    $range = new AgeRange;
    $range->forceFill(['plan_id' => 0, 'range' => '0 a 99', 'age_init' => 0, 'age_end' => 99, 'status' => 'ACTIVO', 'created_by' => 'pest'])->save();

    $row = $sourceRow->replicate();
    $row->forceFill(['affiliation_corporate_id' => $owner->id, 'age_range_id' => $range->id, 'fee' => 100, 'total_persons' => 5])->save();

    $make = function (string $first, string $status, float $fee) use ($owner, $row): AffiliateCorporate {
        $affiliate = new AffiliateCorporate;
        $affiliate->forceFill([
            'affiliation_corporate_id' => $owner->id, 'affiliation_type' => 'ESTANDARD', 'first_name' => $first, 'last_name' => 'TOTALPEST QUIROGA',
            'nro_identificacion' => (string) random_int(80000000, 89999999), 'age' => 30, 'condition_medical' => 'SANO',
            'plan_id' => $row->plan_id, 'coverage_id' => $row->coverage_id, 'fee' => $fee, 'subtotal_anual' => $fee, 'payment_frequency' => 'TRIMESTRAL', 'status' => $status, 'created_by' => 1,
        ])->save();

        return $affiliate;
    };

    $make('UNO', 'ACTIVO', 100);
    $make('DOS', 'ACTIVO', 100);
    $make('TRES', 'PRE-APROBADA', 0);
    $make('CUATRO', 'INACTIVO', 100);
    $make('CINCO', 'ACTIVO', 100)->delete();

    return ['owner' => $owner, 'row' => $row];
}

it('calcula población, tarifa y cuota con los afiliados activos, igual en la tabla y en la consulta directa', function (): void {
    $f = aptFixture();

    $fromTable = ActivePopulationTotals::forRecord(ActivePopulationTotals::applyToQuery(AffiliationCorporate::query())->findOrFail($f['owner']->id));
    $direct = ActivePopulationTotals::compute($f['owner']);

    expect($fromTable['persons'])->toBe(3)
        ->and($fromTable['fee_anual'])->toBe(200.0)
        ->and($fromTable['total_amount'])->toBe(50.0)
        ->and($fromTable['without_fee'])->toBe(1)
        ->and($fromTable['differs'])->toBeTrue()
        ->and($fromTable['stored'])->toBe(['persons' => 5, 'fee_anual' => 999.0, 'total_amount' => 249.75])
        ->and(array_diff_key($direct, ['stored' => 1]))->toBe(array_diff_key($fromTable, ['stored' => 1]));
});

it('recalcula subtotales de plan aunque la población de la fila ya sea la correcta', function (): void {
    $f = aptFixture();
    $f['row']->forceFill([
        'total_persons' => 3,
        'subtotal_anual' => 999,
        'subtotal_quarterly' => 249.75,
        'subtotal_biannual' => 499.5,
    ])->save();

    expect(ActivePopulationTotals::preview($f['owner'])['plan_rows_amounts_differ'])->toBeTrue();

    ActivePopulationTotals::recalculate($f['owner'], aptActor(), 'Los subtotales de plan no cuadraban con las tarifas.');

    $row = $f['row']->fresh();
    expect((int) $row->total_persons)->toBe(3)
        ->and((float) $row->subtotal_anual)->toBe(200.0)
        ->and((float) $row->subtotal_quarterly)->toBe(50.0);
});

it('recalcula la afiliación y sus filas de plan, deja traza y no repite si ya cuadra', function (): void {
    $f = aptFixture();

    $preview = ActivePopulationTotals::preview($f['owner']);
    expect($preview['rows_updatable'])->toBeTrue()
        ->and($preview['rows'][0]['after'])->toBe(3)
        ->and($preview['blocking'])->toBeNull();

    expect(fn () => ActivePopulationTotals::recalculate($f['owner'], aptActor(), 'corto'))->toThrow(RuntimeException::class, 'mínimo');

    $result = ActivePopulationTotals::recalculate($f['owner'], aptActor(), 'Los totales no cuadraban tras bajas manuales.');
    $owner = $f['owner']->fresh();
    $row = $f['row']->fresh();

    expect($result['rows_updated'])->toBeTrue()
        ->and((int) $owner->poblation)->toBe(3)
        ->and((float) $owner->fee_anual)->toBe(200.0)
        ->and((float) $owner->total_amount)->toBe(50.0)
        ->and((int) $row->total_persons)->toBe(3)
        ->and((float) $row->subtotal_anual)->toBe(300.0)
        ->and(DB::table('logs')->where('action', 'AUDIT_CORPORATE_TOTALS_RECALCULATED')->where('response', 'like', '%tras bajas manuales%')->exists())->toBeTrue()
        ->and(fn () => ActivePopulationTotals::recalculate($owner, aptActor(), 'Segundo intento de recalcular igual.'))->toThrow(RuntimeException::class, 'ya cuadran');
});

it('no recalcula si los activos no tienen tarifa o hay una carga de población aplicándose', function (): void {
    $f = aptFixture();
    AffiliateCorporate::query()->where('affiliation_corporate_id', $f['owner']->id)->update(['fee' => 0]);

    expect(ActivePopulationTotals::preview($f['owner'])['blocking'])->toContain('dejaría la afiliación en cero');

    AffiliateCorporate::query()->where('affiliation_corporate_id', $f['owner']->id)->update(['fee' => 100]);
    App\Models\CorporatePopulationUpdate::query()->create([
        'public_id' => (string) Str::uuid(), 'affiliation_corporate_id' => $f['owner']->id, 'status' => App\Models\CorporatePopulationUpdate::STATUS_APPLYING,
        'mode' => 'FULL', 'file_disk' => 'local', 'file_path' => 'x', 'file_name' => 'x.csv', 'file_sha1' => sha1('x'), 'file_format' => 'csv', 'mapping' => [],
    ]);

    expect(fn () => ActivePopulationTotals::recalculate($f['owner'], aptActor(), 'Recalculo mientras se aplica una carga.'))
        ->toThrow(RuntimeException::class, 'aplicándose o revirtiéndose');
    expect((int) $f['owner']->fresh()->poblation)->toBe(5);
});

it('la tabla muestra los totales activos y la acción recalcula desde la fila', function (): void {
    Filament\Facades\Filament::setCurrentPanel('business');
    $f = aptFixture();
    $user = User::factory()->make(['id' => 987951, 'name' => 'Usuario Totales Pest', 'email' => 'totales-pest-'.uniqid().'@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => ['SUPERADMIN', 'NEGOCIOS']]);

    Livewire\Livewire::actingAs($user)
        ->test(App\Filament\Business\Resources\AffiliationCorporates\Pages\ListAffiliationCorporates::class)
        ->call('loadTable')
        ->searchTable($f['owner']->code)
        ->assertCanSeeTableRecords([$f['owner']])
        ->assertTableColumnStateSet('poblation', 3, $f['owner'])
        ->assertTableColumnStateSet('fee_anual', 200.0, $f['owner'])
        ->assertTableActionVisible('recalculateTotals', $f['owner'])
        ->mountTableAction('recalculateTotals', $f['owner'])
        ->set('mountedActions.0.data.reason', 'Corrección de totales desde la tabla de afiliaciones.')
        ->call('callMountedAction')
        ->assertHasNoErrors()
        ->assertNotified('Totales recalculados');

    expect((int) $f['owner']->fresh()->poblation)->toBe(3);
});
