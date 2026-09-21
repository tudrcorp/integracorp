<?php

declare(strict_types=1);

use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\AfilliationCorporatePlan;
use App\Models\AgeRange;
use App\Support\AffiliationCorporates\CorporateAffiliatePlanSynchronizer;

uses(Tests\TestCase::class);

function planRow(int $planId, int $ageInit, int $ageEnd, ?int $coverageId, float $fee): AfilliationCorporatePlan
{
    $row = new AfilliationCorporatePlan([
        'plan_id' => $planId,
        'coverage_id' => $coverageId,
        'fee' => $fee,
    ]);
    $row->setRelation('ageRange', new AgeRange([
        'plan_id' => $planId,
        'age_init' => $ageInit,
        'age_end' => $ageEnd,
        'range' => $ageInit.'-'.$ageEnd,
    ]));

    return $row;
}

function affiliate(mixed $age, ?int $planId = null, ?int $coverageId = null, float $fee = 0.0): AffiliateCorporate
{
    return new AffiliateCorporate([
        'first_name' => 'Victor',
        'last_name' => 'Hernandez',
        'age' => $age,
        'plan_id' => $planId,
        'coverage_id' => $coverageId,
        'fee' => $fee,
    ]);
}

it('resuelve el plan del afiliado por el rango de edad contratado', function (): void {
    $owner = new AffiliationCorporate(['payment_frequency' => 'ANUAL']);
    $rows = collect([
        planRow(5, 0, 25, 3, 120.0),
        planRow(5, 26, 40, 3, 180.0),
        planRow(5, 41, 60, 3, 240.0),
    ]);

    $resolution = CorporateAffiliatePlanSynchronizer::resolvePlanRowForAffiliate($owner, affiliate('34'), $rows);

    expect($resolution['reason'])->toBeNull()
        ->and((float) $resolution['row']->fee)->toBe(180.0);
});

it('incluye los bordes del rango de edad', function (int $age, float $expectedFee): void {
    $owner = new AffiliationCorporate(['payment_frequency' => 'ANUAL']);
    $rows = collect([
        planRow(5, 0, 25, 3, 120.0),
        planRow(5, 26, 40, 3, 180.0),
    ]);

    $resolution = CorporateAffiliatePlanSynchronizer::resolvePlanRowForAffiliate($owner, affiliate((string) $age), $rows);

    expect((float) $resolution['row']->fee)->toBe($expectedFee);
})->with([
    'límite inferior' => [0, 120.0],
    'último del primer rango' => [25, 120.0],
    'primero del segundo' => [26, 180.0],
    'límite superior' => [40, 180.0],
]);

it('no sincroniza a quien no tiene edad registrada', function (mixed $age): void {
    $owner = new AffiliationCorporate(['payment_frequency' => 'ANUAL']);
    $rows = collect([planRow(5, 0, 99, 3, 120.0)]);

    $resolution = CorporateAffiliatePlanSynchronizer::resolvePlanRowForAffiliate($owner, affiliate($age), $rows);

    expect($resolution['row'])->toBeNull()
        ->and($resolution['reason'])->toBe(CorporateAffiliatePlanSynchronizer::REASON_NO_AGE);
})->with([
    'nulo' => [null],
    'vacío' => [''],
    'no numérico' => ['s/d'],
]);

it('avisa cuando la afiliación no contrató un plan para esa edad', function (): void {
    $owner = new AffiliationCorporate(['payment_frequency' => 'ANUAL']);
    $rows = collect([planRow(5, 0, 25, 3, 120.0)]);

    $resolution = CorporateAffiliatePlanSynchronizer::resolvePlanRowForAffiliate($owner, affiliate('64'), $rows);

    expect($resolution['row'])->toBeNull()
        ->and($resolution['reason'])->toBe(CorporateAffiliatePlanSynchronizer::REASON_NO_PLAN_ROW);
});

it('respeta el plan que el afiliado ya tiene cuando varios cubren su edad', function (): void {
    $owner = new AffiliationCorporate(['payment_frequency' => 'ANUAL']);
    $rows = collect([
        planRow(5, 20, 40, 3, 180.0),
        planRow(9, 20, 40, 7, 350.0),
    ]);

    $resolution = CorporateAffiliatePlanSynchronizer::resolvePlanRowForAffiliate($owner, affiliate('30', planId: 9), $rows);

    expect($resolution['reason'])->toBeNull()
        ->and((int) $resolution['row']->plan_id)->toBe(9);
});

it('no adivina cuando varios planes distintos aplican y el afiliado no tiene plan', function (): void {
    $owner = new AffiliationCorporate(['payment_frequency' => 'ANUAL']);
    $rows = collect([
        planRow(5, 20, 40, 3, 180.0),
        planRow(9, 20, 40, 7, 350.0),
    ]);

    $resolution = CorporateAffiliatePlanSynchronizer::resolvePlanRowForAffiliate($owner, affiliate('30'), $rows);

    expect($resolution['row'])->toBeNull()
        ->and($resolution['reason'])->toBe(CorporateAffiliatePlanSynchronizer::REASON_AMBIGUOUS);
});

it('usa la primera fila cuando las candidatas son equivalentes', function (): void {
    $owner = new AffiliationCorporate(['payment_frequency' => 'ANUAL']);
    $rows = collect([
        planRow(5, 20, 40, 3, 180.0),
        planRow(5, 25, 45, 3, 180.0),
    ]);

    $resolution = CorporateAffiliatePlanSynchronizer::resolvePlanRowForAffiliate($owner, affiliate('30'), $rows);

    expect($resolution['reason'])->toBeNull()
        ->and((float) $resolution['row']->fee)->toBe(180.0);
});

it('marca como no sincronizado si la unidad especifica no coincide', function (): void {
    $owner = new AffiliationCorporate([
        'payment_frequency' => 'ANUAL',
        'business_unit_id' => 2,
        'business_line_id' => 4,
        'specific_business_unit' => 'Banco X',
    ]);
    $row = planRow(5, 20, 40, 3, 180.0);

    $alDia = affiliate('30', planId: 5, coverageId: 3, fee: 180.0);
    $alDia->business_unit_id = 2;
    $alDia->business_line_id = 4;
    $alDia->specific_business_unit = 'Banco X';

    $otraUnidad = affiliate('30', planId: 5, coverageId: 3, fee: 180.0);
    $otraUnidad->business_unit_id = 2;
    $otraUnidad->business_line_id = 4;
    $otraUnidad->specific_business_unit = 'Convenio Y';

    expect(CorporateAffiliatePlanSynchronizer::isSynced($owner, $alDia, $row))->toBeTrue()
        ->and(CorporateAffiliatePlanSynchronizer::isSynced($owner, $otraUnidad, $row))->toBeFalse();
});

it('marca como sincronizado solo si coinciden plan, cobertura, tarifa, unidad y linea', function (): void {
    $owner = new AffiliationCorporate([
        'payment_frequency' => 'ANUAL',
        'business_unit_id' => 2,
        'business_line_id' => 4,
    ]);
    $row = planRow(5, 20, 40, 3, 180.0);

    $alDia = affiliate('30', planId: 5, coverageId: 3, fee: 180.0);
    $alDia->business_unit_id = 2;
    $alDia->business_line_id = 4;

    $tarifaVieja = affiliate('30', planId: 5, coverageId: 3, fee: 150.0);
    $tarifaVieja->business_unit_id = 2;
    $tarifaVieja->business_line_id = 4;

    $sinUnidad = affiliate('30', planId: 5, coverageId: 3, fee: 180.0);

    expect(CorporateAffiliatePlanSynchronizer::isSynced($owner, $alDia, $row))->toBeTrue()
        ->and(CorporateAffiliatePlanSynchronizer::isSynced($owner, $tarifaVieja, $row))->toBeFalse()
        ->and(CorporateAffiliatePlanSynchronizer::isSynced($owner, $sinUnidad, $row))->toBeFalse();
});

it('no toca el estatus del afiliado al sincronizar', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Support/AffiliationCorporates/CorporateAffiliatePlanSynchronizer.php');

    expect($source)
        ->toContain('CorporateAffiliatePlanSyncService::syncPlanRowTotalsFromAffiliates($owner, self::COUNTABLE_STATUSES)')
        ->toContain('CorporateAffiliatePlanSyncService::syncOwnerTotalsFromAffiliates($owner, self::COUNTABLE_STATUSES)')
        ->toContain('TelemedicinePatientPlanBridge::syncFromAffiliateCorporate($affiliate)')
        ->toContain('DB::transaction')
        ->not->toContain("'status' => 'ACTIVO'");
});
