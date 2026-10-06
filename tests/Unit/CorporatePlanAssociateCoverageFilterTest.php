<?php

declare(strict_types=1);

use App\Models\AffiliateCorporate;
use App\Models\AfilliationCorporatePlan;
use App\Models\Coverage;
use App\Services\AssociateAffiliatesWithCorporatePlanService as Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(Tests\TestCase::class);

/**
 * «Asociar y recalcular»: solo entran afiliados con edad dentro del rango de la
 * fila y con su misma cobertura o sin cobertura asignada. Los de otra cobertura
 * no se listan ni se aceptan al guardar.
 * Todo lo que escribe va dentro de una transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();

    $this->fila = AfilliationCorporatePlan::query()
        ->whereNotNull('coverage_id')
        ->where('coverage_id', '<>', 0)
        ->whereHas('ageRange', fn ($query) => $query->whereColumn('age_end', '>', 'age_init'))
        ->with(['ageRange', 'AffiliationCorporate'])
        ->get()
        ->first(fn (AfilliationCorporatePlan $fila): bool => AffiliateCorporate::query()->where('affiliation_corporate_id', $fila->affiliation_corporate_id)->count() >= 3);

    $this->otraCobertura = Coverage::query()->whereKeyNot($this->fila?->coverage_id)->first();

    if ($this->fila === null || $this->otraCobertura === null) {
        $this->markTestSkipped('No hay una fila de plan con cobertura y población suficiente.');
    }

    $this->duena = $this->fila->AffiliationCorporate;
    $edad = (int) $this->fila->ageRange->age_init;

    [$conLaMisma, $sinCobertura, $conOtra] = AffiliateCorporate::query()
        ->where('affiliation_corporate_id', $this->duena->id)
        ->orderBy('id')
        ->limit(3)
        ->get()
        ->all();

    // Se fuerza la edad y la cobertura de tres afiliados reales (se revierte).
    $conLaMisma->forceFill(['age' => $edad, 'coverage_id' => $this->fila->coverage_id, 'first_name' => 'ZZ MISMA'])->save();
    $sinCobertura->forceFill(['age' => $edad, 'coverage_id' => null, 'first_name' => 'AA SIN'])->save();
    $conOtra->forceFill(['age' => $edad, 'coverage_id' => $this->otraCobertura->id, 'first_name' => 'MM OTRA'])->save();

    $this->conLaMisma = $conLaMisma;
    $this->sinCobertura = $sinCobertura;
    $this->conOtra = $conOtra;
});

afterEach(function (): void {
    DB::rollBack();
});

it('lista a los de la misma cobertura y a los sin cobertura, primero los de la misma', function (): void {
    $ids = Service::idsForAffiliatesMatchingPlanRowAgeRange($this->duena, $this->fila);

    expect($ids)->toContain($this->conLaMisma->id, $this->sinCobertura->id)
        ->not->toContain($this->conOtra->id)
        // «ZZ MISMA» va antes que «AA SIN» aunque alfabéticamente sea después.
        ->and(array_search($this->conLaMisma->id, $ids, true))->toBeLessThan(array_search($this->sinCobertura->id, $ids, true));
});

it('no lista a quien está fuera del rango de edad aunque tenga la cobertura', function (): void {
    $this->conLaMisma->forceFill(['age' => (int) $this->fila->ageRange->age_end + 1])->save();

    expect(Service::idsForAffiliatesMatchingPlanRowAgeRange($this->duena, $this->fila))->not->toContain($this->conLaMisma->id);
});

it('rechaza al guardar un afiliado con otra cobertura y no toca a nadie', function (): void {
    // Hay filas antiguas cuya tarifa ya no está en `fees`; marcarla negociada aísla lo que se prueba: la cobertura.
    $this->fila->forceFill(['fee_source' => 'NEGOCIADA'])->save();

    $datos = ['plan_id' => $this->fila->plan_id, 'age_range_id' => $this->fila->age_range_id, 'coverage_id' => $this->fila->coverage_id, 'fee' => $this->fila->fee];

    try {
        Service::run($this->duena, $this->fila, [$this->sinCobertura->id, $this->conOtra->id], $datos);
        $errores = [];
    } catch (ValidationException $exception) {
        $errores = $exception->errors();
    }

    expect($errores)->toHaveKey('coverage')
        ->and(implode(' ', $errores['coverage'] ?? []))->toContain('MM OTRA')
        ->and($this->sinCobertura->fresh()->coverage_id)->toBeNull();
});

it('decide la compatibilidad de cobertura', function (mixed $delAfiliado, mixed $deLaFila, bool $esperado): void {
    expect(Service::coverageIsCompatible($delAfiliado, $deLaFila))->toBe($esperado);
})->with([
    'misma cobertura' => [5, 5, true],
    'afiliado sin cobertura, fila con cobertura' => [null, 5, true],
    'afiliado con cero, fila con cobertura' => [0, 5, true],
    'otra cobertura' => [7, 5, false],
    'fila sin cobertura, afiliado sin cobertura' => [null, null, true],
    'fila sin cobertura, afiliado con cobertura' => [5, null, false],
    'fila con cero, afiliado con cobertura' => [5, 0, false],
]);

it('la modal de Negocios usa el filtro de cobertura y explica la lista', function (): void {
    $relationManager = (string) file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/RelationManagers/AffiliationCorporatePlansRelationManager.php');

    expect($relationManager)
        ->toContain('associateModalAffiliateOptions(')
        ->toContain('Sin cobertura asignada: se le asignará la de esta fila.')
        ->toContain("Placeholder::make('no_eligible_affiliates')")
        ->toContain('Los que tienen otra cobertura no aparecen.');
});

it('con afiliados de la misma cobertura o sin cobertura asocia y les asigna la de la fila', function (): void {
    $this->fila->forceFill(['fee_source' => 'NEGOCIADA'])->save();

    Service::run($this->duena, $this->fila, [$this->conLaMisma->id, $this->sinCobertura->id], [
        'plan_id' => $this->fila->plan_id,
        'age_range_id' => $this->fila->age_range_id,
        'coverage_id' => $this->fila->coverage_id,
        'fee' => $this->fila->fee,
    ]);

    expect($this->sinCobertura->fresh()->coverage_id)->toBe($this->fila->coverage_id)
        ->and($this->conLaMisma->fresh()->coverage_id)->toBe($this->fila->coverage_id)
        ->and($this->conOtra->fresh()->coverage_id)->toBe($this->otraCobertura->id);
});
