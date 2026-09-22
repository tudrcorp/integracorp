<?php

declare(strict_types=1);

use App\Enums\ClinicalQuotaScope;
use App\Enums\ClinicalServiceChannel;
use App\Support\ClinicalEntitlements\ClinicalEntitlement;
use App\Support\ClinicalEntitlements\ClinicalEntitlementSnapshot;
use App\Support\ClinicalEntitlements\TelemedicineConsultationClinicalUi;
use App\Support\Telemedicine\ConsultationClinicalSelections;

$basePath = dirname(__DIR__, 2);

function clinicalChannelEntitlementForTest(
    ClinicalServiceChannel $channel,
    int $quota = 2,
    int $used = 0,
): ClinicalEntitlement {
    return new ClinicalEntitlement(
        benefitId: 1,
        benefitLabel: 'BENEFICIO DE PRUEBA',
        channel: $channel,
        telemedicineServiceListId: null,
        telemedicineServiceListName: null,
        quotaScope: ClinicalQuotaScope::DistinctCases,
        quota: $quota,
        used: $used,
        remaining: max(0, $quota - $used),
        exhausted: $used >= $quota,
    );
}

/**
 * @param  list<ClinicalEntitlement>  $entitlements
 */
function clinicalSnapshotForTest(array $entitlements, bool $hasPlan = true, bool $isComplete = true): ClinicalEntitlementSnapshot
{
    return new ClinicalEntitlementSnapshot(
        hasPlan: $hasPlan,
        isComplete: $isComplete,
        missingBenefitLabels: [],
        entitlements: $entitlements,
        blockingMessage: '',
    );
}

it('un canal fuera del uso clinico del plan no esta contemplado', function (): void {
    /** Es el caso de «Repsol 2026-2027»: tiene laboratorio e imagenología, no especialista. */
    $snapshot = clinicalSnapshotForTest([
        clinicalChannelEntitlementForTest(ClinicalServiceChannel::Laboratory),
        clinicalChannelEntitlementForTest(ClinicalServiceChannel::Imaging),
    ]);

    expect(TelemedicineConsultationClinicalUi::channelIsContemplatedIn($snapshot, ClinicalServiceChannel::Laboratory))->toBeTrue()
        ->and(TelemedicineConsultationClinicalUi::channelIsContemplatedIn($snapshot, ClinicalServiceChannel::Imaging))->toBeTrue()
        ->and(TelemedicineConsultationClinicalUi::channelIsContemplatedIn($snapshot, ClinicalServiceChannel::Specialist))->toBeFalse()
        ->and(TelemedicineConsultationClinicalUi::specialistIsContemplatedIn($snapshot))->toBeFalse();
});

it('el cupo agotado no oculta el canal: sigue contemplado', function (): void {
    $snapshot = clinicalSnapshotForTest([
        clinicalChannelEntitlementForTest(ClinicalServiceChannel::Specialist, quota: 2, used: 2),
    ]);

    expect(TelemedicineConsultationClinicalUi::specialistIsContemplatedIn($snapshot))->toBeTrue();
});

it('sin plan se contemplan todos los canales y con plan incompleto ninguno', function (): void {
    $sinPlan = clinicalSnapshotForTest([], hasPlan: false);
    $incompleto = clinicalSnapshotForTest([], isComplete: false);

    foreach (ClinicalServiceChannel::cases() as $channel) {
        expect(TelemedicineConsultationClinicalUi::channelIsContemplatedIn($sinPlan, $channel))->toBeTrue()
            ->and(TelemedicineConsultationClinicalUi::channelIsContemplatedIn($incompleto, $channel))->toBeFalse();
    }

    expect(TelemedicineConsultationClinicalUi::channelIsContemplatedIn(null, ClinicalServiceChannel::Specialist))->toBeTrue();
});

it('al guardar descarta los cubiertos del canal no contemplado y conserva los no cubiertos', function (): void {
    $selections = ConsultationClinicalSelections::fromFormData([
        'medications' => [['medicines' => 'PARACETAMOL']],
        'labs' => ['HEMATOLOGIA COMPLETA'],
        'other_labs' => ['PERFIL HEPATICO'],
        'studies' => ['RX TORAX'],
        'other_studies' => ['ECO ABDOMINAL'],
        'consult_specialist' => ['CARDIOLOGIA'],
        'other_specialist' => ['MEDICINA INTERNA'],
        'feedbackOne' => false,
    ]);

    $filtradas = $selections->withoutUncontemplatedCovered(
        static fn (ClinicalServiceChannel $channel): bool => $channel !== ClinicalServiceChannel::Specialist,
    );

    expect($filtradas->consultSpecialist)->toBe([])
        ->and($filtradas->otherSpecialist)->toBe(['MEDICINA INTERNA'])
        ->and($filtradas->mergedSpecialists())->toBe(['MEDICINA INTERNA'])
        ->and($filtradas->labs)->toBe(['HEMATOLOGIA COMPLETA'])
        ->and($filtradas->studies)->toBe(['RX TORAX'])
        ->and($filtradas->medications)->toHaveCount(1)
        ->and($filtradas->discharge)->toBeFalse();
});

it('descarta tambien laboratorios y estudios cubiertos cuando su canal no aplica', function (): void {
    $selections = ConsultationClinicalSelections::fromFormData([
        'labs' => ['HEMATOLOGIA COMPLETA'],
        'other_labs' => ['PERFIL HEPATICO'],
        'studies' => ['RX TORAX'],
        'other_studies' => ['ECO ABDOMINAL'],
        'consult_specialist' => ['CARDIOLOGIA'],
    ]);

    $filtradas = $selections->withoutUncontemplatedCovered(
        static fn (ClinicalServiceChannel $channel): bool => $channel === ClinicalServiceChannel::Specialist,
    );

    expect($filtradas->labs)->toBe([])
        ->and($filtradas->studies)->toBe([])
        ->and($filtradas->otherLabs)->toBe(['PERFIL HEPATICO'])
        ->and($filtradas->otherStudies)->toBe(['ECO ABDOMINAL'])
        ->and($filtradas->consultSpecialist)->toBe(['CARDIOLOGIA']);
});

it('el formulario oculta los selects de cubiertos segun el uso clinico del plan', function () use ($basePath): void {
    $form = file_get_contents($basePath.'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientForm.php');

    expect($form)
        ->toContain('->visible(fn (): bool => TelemedicineConsultationClinicalUi::channelIsContemplated(ClinicalServiceChannel::Laboratory))')
        ->toContain('->visible(fn (): bool => TelemedicineConsultationClinicalUi::channelIsContemplated(ClinicalServiceChannel::Imaging))')
        ->toContain('->visible(fn (): bool => TelemedicineConsultationClinicalUi::specialistIsContemplated())')
        ->toContain("->label('Otros Especialistas')");
});

it('la pagina de crear consulta aplica el filtro de canales al guardar', function () use ($basePath): void {
    $create = file_get_contents($basePath.'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/CreateTelemedicineConsultationPatient.php');

    expect($create)->toContain('->withoutUncontemplatedCovered(');
});

it('el aviso del paso explica que solo se pueden indicar especialistas no cubiertos', function (): void {
    expect(TelemedicineConsultationClinicalUi::SPECIALIST_NOT_CONTEMPLATED_MESSAGE)
        ->toContain('no está contemplada en el uso clínico de este plan')
        ->toContain('especialistas no cubiertos por el plan')
        ->toContain('no consume cupo');
});
