<?php

declare(strict_types=1);

use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\Plan;
use App\Models\TelemedicinePatient;
use App\Support\ClinicalEntitlements\AffiliateClinicalEntitlementResolver;
use App\Support\ClinicalEntitlements\OperationsAffiliatePlanBenefitsCard;
use App\Support\Telemedicine\TelemedicinePatientPlanBridge;

it('hereda el plan del afiliado corporativo cuando el paciente no tiene plan_id', function (): void {
    $plan = new Plan(['description' => 'Repsol 2026-2027']);
    $plan->id = 26;

    $affiliate = new AffiliateCorporate([
        'affiliation_corporate_id' => 54,
        'nro_identificacion' => '6216255',
        'plan_id' => 26,
        'coverage_id' => 8,
        'status' => 'ACTIVO',
    ]);
    $affiliate->setRelation('plan', $plan);

    $patient = new TelemedicinePatient([
        'plan_id' => null,
        'coverage_id' => null,
        'afilliation_corporate_id' => 54,
        'nro_identificacion' => '6216255',
        'full_name' => 'BELKYS COROMOTO',
    ]);
    $patient->setRelation('plan', null);
    $patient->setRelation(TelemedicinePatientPlanBridge::RELATION_CORPORATE_AFFILIATE, $affiliate);

    expect(TelemedicinePatientPlanBridge::plan($patient)?->id)->toBe(26)
        ->and(TelemedicinePatientPlanBridge::planId($patient))->toBe(26)
        ->and(TelemedicinePatientPlanBridge::pendingAttributes($patient))->toBe([
            'plan_id' => 26,
            'coverage_id' => 8,
        ]);
});

it('no pisa un plan_id ya guardado en el paciente si solo se rellenan faltantes', function (): void {
    $plan = new Plan(['description' => 'Otro plan']);
    $plan->id = 10;

    $affiliate = new AffiliateCorporate([
        'affiliation_corporate_id' => 54,
        'nro_identificacion' => '6216255',
        'plan_id' => 26,
        'coverage_id' => 8,
        'status' => 'ACTIVO',
    ]);
    $affiliate->setRelation('plan', $plan);

    $patient = new TelemedicinePatient([
        'plan_id' => 10,
        'coverage_id' => 3,
        'afilliation_corporate_id' => 54,
        'nro_identificacion' => '6216255',
    ]);
    $patient->setRelation('plan', $plan);
    $patient->setRelation(TelemedicinePatientPlanBridge::RELATION_CORPORATE_AFFILIATE, $affiliate);

    expect(TelemedicinePatientPlanBridge::pendingAttributes($patient, onlyMissing: true))->toBeNull()
        ->and(TelemedicinePatientPlanBridge::pendingAttributes($patient, onlyMissing: false))->toBe([
            'plan_id' => 26,
            'coverage_id' => 8,
        ]);
});

it('hereda el plan del afiliado individual cuando el paciente no tiene plan_id', function (): void {
    $plan = new Plan(['description' => 'Plan individual']);
    $plan->id = 4;

    $affiliate = new Affiliate([
        'affiliation_id' => 9,
        'nro_identificacion' => '12345678',
        'plan_id' => 4,
        'coverage_id' => 2,
        'status' => 'ACTIVO',
    ]);
    $affiliate->setRelation('plan', $plan);

    $patient = new TelemedicinePatient([
        'plan_id' => null,
        'afilliation_id' => 9,
        'nro_identificacion' => '12345678',
    ]);
    $patient->setRelation('plan', null);
    $patient->setRelation(TelemedicinePatientPlanBridge::RELATION_INDIVIDUAL_AFFILIATE, $affiliate);

    expect(TelemedicinePatientPlanBridge::plan($patient)?->id)->toBe(4)
        ->and(TelemedicinePatientPlanBridge::pendingAttributes($patient)['plan_id'] ?? null)->toBe(4);
});

it('el resolver y la ficha del paciente usan el puente de plan', function (): void {
    $resolver = file_get_contents(dirname(__DIR__, 2).'/app/Support/ClinicalEntitlements/AffiliateClinicalEntitlementResolver.php');
    $card = file_get_contents(dirname(__DIR__, 2).'/app/Support/ClinicalEntitlements/OperationsAffiliatePlanBenefitsCard.php');
    $otp = file_get_contents(dirname(__DIR__, 2).'/app/Support/ClinicalEntitlements/ClinicalServiceOverrideOtp.php');
    $infolist = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicinePatients/Schemas/TelemedicinePatientInfolist.php');

    expect($resolver)
        ->toContain('TelemedicinePatientPlanBridge::hydrate($patient)')
        ->and($card)->toContain('TelemedicinePatientPlanBridge::hydrate($record)')
        ->and($otp)->toContain('TelemedicinePatientPlanBridge::planId($patient)')
        ->and($infolist)->toContain('TelemedicinePatientPlanBridge::plan($record) === null')
        ->and($infolist)->not->toContain('$record->plan_id === null');
});

it('sincroniza el plan al afiliado corporativo y al comando de backfill', function (): void {
    $synchronizer = file_get_contents(dirname(__DIR__, 2).'/app/Support/AffiliationCorporates/CorporateAffiliatePlanSynchronizer.php');
    $reassign = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/RelationManagers/CorporateAffiliatesRelationManager.php');
    $individual = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/RelationManagers/AffiliatesRelationManager.php');
    $associate = file_get_contents(dirname(__DIR__, 2).'/app/Services/AssociateAffiliateCorporateWithTelemedicinePatientService.php');
    $command = file_get_contents(dirname(__DIR__, 2).'/app/Console/Commands/Telemedicine/SyncTelemedicinePatientPlansCommand.php');

    expect($synchronizer)
        ->toContain('TelemedicinePatientPlanBridge::syncFromAffiliateCorporate($affiliate)')
        ->and($reassign)->toContain('TelemedicinePatientPlanBridge::syncFromAffiliateCorporate($record)')
        ->and($individual)->toContain('TelemedicinePatientPlanBridge::syncFromAffiliate($record)')
        ->and($associate)->toContain("'plan_id' => \$member->plan_id")
        ->and($command)
        ->toContain('telemedicine:sync-patient-plans')
        ->toContain('{--apply')
        ->toContain('TelemedicinePatientPlanBridge::backfillMissing');
});

it('la ficha de paciente vacía sigue avisando que no hay paciente', function (): void {
    $empty = OperationsAffiliatePlanBenefitsCard::viewDataForPatient(null);

    expect($empty['tone'])->toBe('muted')
        ->and($empty['message'])->toContain('No hay paciente');

    AffiliateClinicalEntitlementResolver::flush();
});
