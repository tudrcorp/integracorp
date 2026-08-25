<?php

declare(strict_types=1);

use App\Models\AffiliationCorporate;
use App\Models\AfilliationCorporatePlan;
use App\Models\Benefit;
use App\Models\Plan;
use App\Support\AffiliationCorporates\CorporateAffiliationContractedPlan;

function corporateAffiliationContractedPlanRow(int $planId, Plan $plan): AfilliationCorporatePlan
{
    $row = new AfilliationCorporatePlan(['plan_id' => $planId]);
    $row->setRelation('plan', $plan);

    return $row;
}

function corporateAffiliationPlanWithId(int $id, string $description): Plan
{
    $plan = new Plan(['description' => $description]);
    $plan->id = $id;
    $plan->setRelation('benefitPlans', collect());

    return $plan;
}

it('toma el plan unico de afilliation_corporate_plans aunque la cabecera tenga otro', function (): void {
    $affiliation = new AffiliationCorporate(['code' => 'TDEC-COR-00055']);
    $affiliation->setRelation('plan', corporateAffiliationPlanWithId(1, 'PLAN ESTÁNDAR'));

    $especial = corporateAffiliationPlanWithId(3, 'PLAN ESPECIAL');
    $affiliation->setRelation('affiliationCorporatePlans', collect([
        corporateAffiliationContractedPlanRow(3, $especial),
        corporateAffiliationContractedPlanRow(3, $especial),
        corporateAffiliationContractedPlanRow(3, $especial),
    ]));

    expect(CorporateAffiliationContractedPlan::planId($affiliation))->toBe(3)
        ->and(CorporateAffiliationContractedPlan::certificateFields($affiliation))
        ->toBe([
            'plan' => 'PLAN ESPECIAL',
            'plan_id' => 3,
        ]);
});

it('usa el plan predominante si las filas contratadas no coinciden', function (): void {
    $affiliation = new AffiliationCorporate(['code' => 'TDEC-COR-00056']);
    $inicial = corporateAffiliationPlanWithId(1, 'PLAN INICIAL');
    $especial = corporateAffiliationPlanWithId(3, 'PLAN ESPECIAL');

    $affiliation->setRelation('affiliationCorporatePlans', collect([
        corporateAffiliationContractedPlanRow(3, $especial),
        corporateAffiliationContractedPlanRow(3, $especial),
        corporateAffiliationContractedPlanRow(1, $inicial),
    ]));

    expect(CorporateAffiliationContractedPlan::certificateFields($affiliation))
        ->toBe([
            'plan' => 'PLAN ESPECIAL',
            'plan_id' => 3,
        ]);
});

it('no inventa PLAN ESTANDAR cuando no hay filas contratadas', function (): void {
    $affiliation = new AffiliationCorporate(['code' => 'TDEC-COR-00057']);
    $affiliation->setRelation('plan', corporateAffiliationPlanWithId(1, 'PLAN ESTÁNDAR'));
    $affiliation->setRelation('affiliationCorporatePlans', collect());

    expect(CorporateAffiliationContractedPlan::planId($affiliation))->toBeNull()
        ->and(CorporateAffiliationContractedPlan::certificateFields($affiliation))
        ->toBe([
            'plan' => '',
            'plan_id' => null,
        ]);
});

it('expone los beneficios del plan contratado', function (): void {
    $affiliation = new AffiliationCorporate(['code' => 'TDEC-COR-00058']);
    $plan = corporateAffiliationPlanWithId(2, 'PLAN IDEAL');
    $plan->setRelation('benefitPlans', collect([
        new Benefit(['description' => 'Telemedicina']),
        new Benefit(['description' => 'Orientación médica']),
    ]));
    $affiliation->setRelation('affiliationCorporatePlans', collect([
        corporateAffiliationContractedPlanRow(2, $plan),
    ]));

    expect(CorporateAffiliationContractedPlan::benefitDescriptions($affiliation))
        ->toBe(['Telemedicina', 'Orientación médica']);
});

it('carga las filas contratadas al generar el certificado corporativo', function (): void {
    $job = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/GenerateCorporateCertificateJob.php');

    expect($job)->toContain('affiliationCorporatePlans.plan.benefitPlans');
});
