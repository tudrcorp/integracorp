<?php

declare(strict_types=1);

use App\Enums\ClinicalServiceChannel;
use App\Models\TelemedicinePatient;
use App\Models\TelemedicineServiceList;
use App\Support\ClinicalEntitlements\AffiliateClinicalEntitlementResolver;
use App\Support\ClinicalEntitlements\ClinicalConsultationConsumption;
use App\Support\ClinicalEntitlements\TelemedicineConsultationClinicalUi;
use Illuminate\Validation\ValidationException;

uses(Tests\TestCase::class);

/**
 * «CONSULTA PRESENCIAL» se ofrece siempre en Tipo de Servicio, aunque el plan no la
 * incluya, y solo registra el tipo de consulta: no consume cupo ni la bloquea el plan.
 * Solo lee: no escribe en la base.
 */
beforeEach(function (): void {
    TelemedicineServiceList::flushStandardType1Options();
    TelemedicineConsultationClinicalUi::flush();
});

afterEach(function (): void {
    session()->forget('patient');
    TelemedicineConsultationClinicalUi::flush();
});

function standardServiceId(): int
{
    $id = array_key_first(TelemedicineServiceList::standardType1Options());

    if ($id === null) {
        test()->markTestSkipped('Falta aplicar la migración de CONSULTA PRESENCIAL.');
    }

    return (int) $id;
}

/**
 * @return array{0: TelemedicinePatient, 1: \App\Support\ClinicalEntitlements\ClinicalEntitlementSnapshot}
 */
function patientWhosePlanLacksStandardService(int $standardId): array
{
    foreach (TelemedicinePatient::query()->latest('id')->limit(300)->get() as $patient) {
        $snapshot = AffiliateClinicalEntitlementResolver::forPatient($patient);

        if ($snapshot->hasPlan && $snapshot->isComplete && $snapshot->type1Options() !== []
            && $snapshot->forType1($standardId) === null) {
            return [$patient, $snapshot];
        }
    }

    test()->markTestSkipped('No hay pacientes con plan completo para probar.');
}

it('el catálogo tiene CONSULTA PRESENCIAL como servicio de nivel 1', function (): void {
    $id = standardServiceId();

    expect(TelemedicineServiceList::query()->whereKey($id)->value('name'))->toBe('CONSULTA PRESENCIAL')
        ->and(TelemedicineServiceList::isStandardType1($id))->toBeTrue()
        ->and(TelemedicineServiceList::isStandardType1(TelemedicineServiceList::CONSULTA_GENERAL_ID))->toBeFalse()
        ->and(TelemedicineServiceList::isStandardType1(null))->toBeFalse();
});

it('aparece en Tipo de Servicio y en Deriva aunque el plan no la incluya, sin perder las del plan', function (): void {
    $id = standardServiceId();
    [$patient, $snapshot] = patientWhosePlanLacksStandardService($id);
    session(['patient' => $patient]);

    $options = TelemedicineConsultationClinicalUi::type1Options();

    expect($options)->toHaveKey($id)
        ->and(array_diff_key($snapshot->type1Options(), $options))->toBe([])
        ->and(TelemedicineConsultationClinicalUi::type1DriftOptions())->toHaveKey($id);
});

it('se puede guardar sin que el plan la incluya y no consume cupo', function (): void {
    $id = standardServiceId();
    [$patient] = patientWhosePlanLacksStandardService($id);

    ClinicalConsultationConsumption::assertCanSave($patient, ['telemedicine_service_list_id' => $id], null);

    expect(ClinicalConsultationConsumption::requestedChannels(['telemedicine_service_list_id' => $id]))
        ->toBe([ClinicalServiceChannel::Type1->value => $id]);
});

it('un servicio que el plan no incluye y no es estándar sigue bloqueado', function (): void {
    $id = standardServiceId();
    [$patient, $snapshot] = patientWhosePlanLacksStandardService($id);
    $outside = TelemedicineServiceList::query()
        ->where('level', 1)
        ->whereKeyNot($id)
        ->whereNotIn('id', array_keys($snapshot->type1Options()))
        ->value('id');

    if ($outside === null) {
        $this->markTestSkipped('El plan incluye todos los servicios de nivel 1.');
    }

    expect(fn () => ClinicalConsultationConsumption::assertCanSave($patient, ['telemedicine_service_list_id' => (int) $outside], null))
        ->toThrow(ValidationException::class, 'no está incluido');
});
