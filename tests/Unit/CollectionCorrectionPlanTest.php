<?php

declare(strict_types=1);

use App\Models\Collection;
use App\Models\CollectionAdjustment;
use App\Support\Collections\CollectionCorrectionPlan;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

/**
 * Plan mínimo sobre una cuota real: cambia su RIF y lo vuelve a dejar al revertir.
 *
 * @return array{0: Collection, 1: array<string, mixed>}
 */
function correctionPlanFixture(): array
{
    $collection = Collection::query()->whereNotNull('affiliation_code')->whereNotNull('collection_invoice_number')->latest('id')->first();

    if ($collection === null) {
        test()->markTestSkipped('No hay cuotas en la base.');
    }

    return [$collection, [
        'affiliation_code' => (string) $collection->affiliation_code,
        'reason' => 'Prueba del plan de corrección de cuotas',
        'collections' => [[
            'id' => $collection->id,
            'invoice' => (string) $collection->collection_invoice_number,
            'expect' => ['affiliate_ci_rif' => (string) $collection->affiliate_ci_rif],
            'set' => ['affiliate_ci_rif' => 'J-PRUEBA'],
        ]],
    ]];
}

it('simula sin escribir, aplica con traza y reconoce lo ya aplicado', function (): void {
    [$collection, $plan] = correctionPlanFixture();
    $before = (string) $collection->affiliate_ci_rif;

    expect(CollectionCorrectionPlan::inspect($plan)['status'])->toBe('pending')
        ->and($collection->fresh()->affiliate_ci_rif)->toBe($before);

    $result = CollectionCorrectionPlan::apply($plan, 'Prueba');

    expect($collection->fresh()->affiliate_ci_rif)->toBe('J-PRUEBA')
        ->and(CollectionCorrectionPlan::inspect($plan)['status'])->toBe('applied');

    $adjustment = CollectionAdjustment::query()->where('batch_uuid', $result['batch_uuid'])->sole();

    expect($adjustment->mode)->toBe(CollectionCorrectionPlan::MODE)
        ->and($adjustment->changes)->toBe(['affiliate_ci_rif' => ['before' => $before, 'after' => 'J-PRUEBA']]);

    expect(fn () => CollectionCorrectionPlan::apply($plan, 'Prueba'))->toThrow(RuntimeException::class, 'ya está aplicado');
});

it('no escribe nada si un valor actual no coincide con lo esperado', function (): void {
    [$collection, $plan] = correctionPlanFixture();
    $plan['collections'][0]['expect']['affiliate_ci_rif'] = 'OTRO-VALOR';

    expect(CollectionCorrectionPlan::inspect($plan)['status'])->toBe('mismatch')
        ->and(fn () => CollectionCorrectionPlan::apply($plan, 'Prueba'))->toThrow(RuntimeException::class, 'no coinciden');

    expect($collection->fresh()->affiliate_ci_rif)->not->toBe('J-PRUEBA');
});

it('rechaza una cuota de otra afiliación', function (): void {
    [, $plan] = correctionPlanFixture();
    $plan['affiliation_code'] = 'TDEC-COR-NO-EXISTE';

    expect(CollectionCorrectionPlan::inspect($plan)['status'])->toBe('mismatch');
});

it('revierte el plan dejando los valores originales', function (): void {
    [$collection, $plan] = correctionPlanFixture();
    $before = (string) $collection->affiliate_ci_rif;

    CollectionCorrectionPlan::apply($plan, 'Prueba');
    CollectionCorrectionPlan::apply($plan, 'Prueba', revert: true);

    expect($collection->fresh()->affiliate_ci_rif)->toBe($before)
        ->and(CollectionAdjustment::query()->where('collection_id', $collection->id)->where('reason', 'like', 'REVERSO:%')->exists())->toBeTrue();
});

it('rechaza columnas no permitidas, motivos cortos y planes vacíos', function (array $plan): void {
    expect(fn () => CollectionCorrectionPlan::inspect($plan))->toThrow(InvalidArgumentException::class);
})->with([
    'columna no permitida' => [['affiliation_code' => 'X', 'reason' => 'Motivo suficientemente largo', 'collections' => [['id' => 1, 'invoice' => 'x', 'expect' => ['status' => 'POR PAGAR'], 'set' => ['status' => 'PAGADO']]]]],
    'motivo corto' => [['affiliation_code' => 'X', 'reason' => 'corto', 'collections' => [['id' => 1, 'invoice' => 'x', 'expect' => ['persons' => '1'], 'set' => ['persons' => '2']]]]],
    'sin cambios' => [['affiliation_code' => 'X', 'reason' => 'Motivo suficientemente largo']],
    'expect incompleto' => [['affiliation_code' => 'X', 'reason' => 'Motivo suficientemente largo', 'collections' => [['id' => 1, 'invoice' => 'x', 'expect' => [], 'set' => ['persons' => '2']]]]],
]);

it('el plan de Casalbeach cuadra con lo aprobado', function (): void {
    $plan = json_decode((string) file_get_contents(base_path('database/corrections/2026_11_03_casalbeach_poblacion.json')), true);
    $pending = collect($plan['collections'])->reject(fn (array $item): bool => $item['id'] === 1102);

    expect($pending)->toHaveCount(10)
        ->and(round($pending->sum(fn (array $item): float => (float) $item['set']['total_amount']), 2))->toBe(7826.03)
        ->and(collect($plan['collections'])->pluck('set.affiliate_ci_rif')->unique()->all())->toBe(['298343427'])
        ->and($plan['affiliation_corporate']['set'])->toBe(['fee_anual' => '11049', 'total_amount' => '920.75']);
});
