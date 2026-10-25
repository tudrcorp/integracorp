<?php

declare(strict_types=1);

use App\Models\ObservationCase;
use App\Models\OperationCoordinationService;
use App\Models\TelemedicineCase;
use App\Models\TelemedicinePatient;
use App\Services\TelemedicineCaseLogicalDeletionService;
use App\Support\Operations\CoordinationServiceCaseDeletion;
use App\Support\Telemedicine\Scopes\HideDeletedTelemedicineCasesScope;
use App\Support\Telemedicine\TelemedicineCaseDeletion;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

/**
 * Escribe en base de datos: todo ocurre dentro de una transacción que siempre
 * se revierte, para no dejar rastro en la base de desarrollo.
 */
beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

/**
 * @return array{case: TelemedicineCase, service: OperationCoordinationService}
 */
function crearCasoConServicio(string $status = 'EN SEGUIMIENTO'): array
{
    $patient = TelemedicinePatient::query()->create([
        'full_name' => 'PACIENTE PRUEBA ELIMINACION',
        'managed_by' => 'TDG',
    ]);

    $case = TelemedicineCase::query()->create([
        'telemedicine_patient_id' => $patient->id,
        'code' => 'TEST-'.uniqid(),
        'status' => $status,
    ]);

    $service = crearServicioDeCoordinacion($case);

    return ['case' => $case, 'service' => $service];
}

function crearServicioDeCoordinacion(TelemedicineCase $case): OperationCoordinationService
{
    return OperationCoordinationService::query()->create([
        'telemedicine_patient_id' => $case->telemedicine_patient_id,
        'telemedicine_case_id' => $case->id,
        'status' => 'PENDIENTE',
        'ci_patient' => 'V-00000000',
        'contractor' => 'TDG',
        'relationship_patient' => 'TITULAR',
        'symptoms_diagnosis' => 'PRUEBA',
        'created_by' => 'TEST',
    ]);
}

it('oculta el caso y sus trazas sin borrar una sola fila', function (): void {
    ['case' => $case, 'service' => $service] = crearCasoConServicio();

    app(TelemedicineCaseLogicalDeletionService::class)
        ->delete($case, 'Caso duplicado registrado por error en la carga inicial');

    expect(TelemedicineCase::query()->whereKey($case->id)->exists())->toBeFalse()
        ->and(OperationCoordinationService::query()->whereKey($service->id)->exists())->toBeFalse();

    $persistido = TelemedicineCase::query()
        ->withoutGlobalScope(HideDeletedTelemedicineCasesScope::class)
        ->find($case->id);

    expect($persistido)->not->toBeNull()
        ->and($persistido->status)->toBe(TelemedicineCaseDeletion::STATUS)
        ->and($persistido->deletion_status_before)->toBe('EN SEGUIMIENTO')
        ->and($persistido->deletion_reason)->toBe('Caso duplicado registrado por error en la carga inicial')
        ->and($persistido->logically_deleted_at)->not->toBeNull()
        ->and(
            OperationCoordinationService::query()
                ->withoutGlobalScopes()
                ->whereKey($service->id)
                ->exists()
        )->toBeTrue();
});

it('devuelve el caso a su estatus anterior al restaurarlo', function (): void {
    ['case' => $case, 'service' => $service] = crearCasoConServicio('ALTA MEDICA');

    $deletion = app(TelemedicineCaseLogicalDeletionService::class);
    $deletion->delete($case, 'Eliminado para probar la restauración del estatus');

    $eliminado = TelemedicineCaseLogicalDeletionService::findIncludingDeleted((int) $case->id);
    $deletion->restore($eliminado, 'Restaurado porque la eliminación fue un error');

    $restaurado = TelemedicineCase::query()->find($case->id);

    expect($restaurado)->not->toBeNull()
        ->and($restaurado->status)->toBe('ALTA MEDICA')
        ->and($restaurado->deletion_reason)->toBeNull()
        ->and($restaurado->logically_deleted_at)->toBeNull()
        ->and(OperationCoordinationService::query()->whereKey($service->id)->exists())->toBeTrue();
});

it('deja el motivo de la eliminación y de la restauración en la bitácora del caso', function (): void {
    ['case' => $case] = crearCasoConServicio();

    $deletion = app(TelemedicineCaseLogicalDeletionService::class);
    $deletion->delete($case, 'Paciente equivocado al crear el caso');
    $deletion->restore(
        TelemedicineCaseLogicalDeletionService::findIncludingDeleted((int) $case->id),
        'El paciente sí correspondía a este caso'
    );

    $bitacora = ObservationCase::query()
        ->withoutGlobalScopes()
        ->where('telemedicine_case_id', $case->id)
        ->orderBy('id')
        ->pluck('description')
        ->all();

    expect($bitacora)->toHaveCount(2)
        ->and($bitacora[0])->toContain('CASO ELIMINADO')
        ->and($bitacora[0])->toContain('Paciente equivocado al crear el caso')
        ->and($bitacora[1])->toContain('CASO RESTAURADO')
        ->and($bitacora[1])->toContain('El paciente sí correspondía a este caso');
});

it('rechaza un motivo demasiado corto y no toca el caso', function (): void {
    ['case' => $case] = crearCasoConServicio();

    expect(fn () => app(TelemedicineCaseLogicalDeletionService::class)->delete($case, 'corto'))
        ->toThrow(InvalidArgumentException::class);

    expect(TelemedicineCase::query()->whereKey($case->id)->value('status'))->toBe('EN SEGUIMIENTO');
});

it('no elimina dos veces el mismo caso', function (): void {
    ['case' => $case] = crearCasoConServicio();

    $deletion = app(TelemedicineCaseLogicalDeletionService::class);
    $deletion->delete($case, 'Primera eliminación del caso de prueba');

    $eliminado = TelemedicineCaseLogicalDeletionService::findIncludingDeleted((int) $case->id);

    expect(fn () => $deletion->delete($eliminado, 'Segunda eliminación del mismo caso'))
        ->toThrow(InvalidArgumentException::class);
});

it('agrupa por caso los servicios seleccionados y encuentra los eliminados', function (): void {
    ['case' => $case, 'service' => $service] = crearCasoConServicio();

    $segundoServicio = crearServicioDeCoordinacion($case);

    $casos = CoordinationServiceCaseDeletion::casesFromRecords(
        collect([$service, $segundoServicio])
    );

    expect($casos)->toHaveCount(1)
        ->and((int) $casos->first()->id)->toBe((int) $case->id);

    app(TelemedicineCaseLogicalDeletionService::class)
        ->delete($case, 'Eliminado para comprobar la pestaña de eliminados');

    $enPestanaEliminados = CoordinationServiceCaseDeletion::applyDeletedCasesScope(
        OperationCoordinationService::query()
    )->pluck('id')->all();

    expect($enPestanaEliminados)
        ->toContain($service->id)
        ->toContain($segundoServicio->id);
});

/**
 * Consumo de cupo clínico de prueba para el caso.
 */
function crearConsumoDeCupo(TelemedicineCase $case, string $status = 'CONSUMED'): App\Models\AffiliateClinicalServiceUsage
{
    return App\Models\AffiliateClinicalServiceUsage::query()->create([
        'telemedicine_patient_id' => $case->telemedicine_patient_id,
        'nro_identificacion' => 'V-00000000',
        'benefit_id' => 1,
        'channel' => 'MEDICATION',
        'telemedicine_case_id' => $case->id,
        'status' => $status,
        'is_override' => false,
        'window_starts_at' => now()->subMonth(),
        'window_ends_at' => now()->addMonths(11),
    ]);
}

it('al restaurar vuelve a descontar exactamente los cupos que devolvió la eliminación', function (): void {
    ['case' => $case] = crearCasoConServicio();
    $consumo1 = crearConsumoDeCupo($case);
    $consumo2 = crearConsumoDeCupo($case);
    $revertidoAntes = crearConsumoDeCupo($case, 'REVERSED');

    $deletion = app(TelemedicineCaseLogicalDeletionService::class);
    $deletion->delete($case, 'Caso duplicado para probar el punto de restauración de cupos');

    $eliminado = TelemedicineCaseLogicalDeletionService::findIncludingDeleted((int) $case->id);

    expect($eliminado->deletion_reversed_usage_ids)->toEqualCanonicalizing([$consumo1->id, $consumo2->id])
        ->and($consumo1->fresh()->status)->toBe('REVERSED')
        ->and($consumo2->fresh()->status)->toBe('REVERSED');

    $resultado = $deletion->restore($eliminado, 'La eliminación fue un error, el caso es real');

    expect($resultado['reconsumed_usages'])->toBe(2)
        ->and($resultado['quota_warnings'])->toBe([])
        ->and($consumo1->fresh()->status)->toBe('CONSUMED')
        ->and($consumo2->fresh()->status)->toBe('CONSUMED')
        ->and($revertidoAntes->fresh()->status)->toBe('REVERSED')
        ->and(TelemedicineCase::query()->find($case->id)->deletion_reversed_usage_ids)->toBeNull();
});

it('no descuenta dos veces un cupo que ya se volvió a consumir por otra vía', function (): void {
    ['case' => $case] = crearCasoConServicio();
    $consumo1 = crearConsumoDeCupo($case);
    $consumo2 = crearConsumoDeCupo($case);

    $deletion = app(TelemedicineCaseLogicalDeletionService::class);
    $deletion->delete($case, 'Eliminado para probar la doble restauración');
    App\Models\AffiliateClinicalServiceUsage::query()->whereKey($consumo1->id)->update(['status' => 'CONSUMED']);

    $resultado = $deletion->restore(
        TelemedicineCaseLogicalDeletionService::findIncludingDeleted((int) $case->id),
        'Restaurado para comprobar que no se duplica',
    );

    expect($resultado['reconsumed_usages'])->toBe(1)
        ->and($consumo2->fresh()->status)->toBe('CONSUMED');
});

it('un caso eliminado antes del punto de restauración de cupos se restaura con aviso', function (): void {
    ['case' => $case] = crearCasoConServicio();
    $consumo = crearConsumoDeCupo($case, 'REVERSED');

    $case->forceFill([
        'deletion_status_before' => 'EN SEGUIMIENTO',
        'status' => TelemedicineCaseDeletion::STATUS,
        'deletion_reason' => 'Eliminado con la versión anterior',
        'deleted_by_name' => 'QA',
        'logically_deleted_at' => now(),
        'deletion_reversed_usage_ids' => null,
    ])->save();

    $resultado = app(TelemedicineCaseLogicalDeletionService::class)->restore(
        TelemedicineCaseLogicalDeletionService::findIncludingDeleted((int) $case->id),
        'Restaurado desde la versión anterior',
    );

    expect($resultado['reconsumed_usages'])->toBe(0)
        ->and($resultado['quota_warnings'][0])->toContain('no se volvieron a descontar')
        ->and($consumo->fresh()->status)->toBe('REVERSED')
        ->and(TelemedicineCase::query()->find($case->id)?->status)->toBe('EN SEGUIMIENTO');
});

it('Gestión de casos entrega casos directamente y su vista de eliminados los encuentra', function (): void {
    ['case' => $case] = crearCasoConServicio();
    ['case' => $otro] = crearCasoConServicio();

    $casos = CoordinationServiceCaseDeletion::casesFromRecords(collect([$case, $otro, $case, 'basura']));

    expect($casos->pluck('id')->map(fn ($id) => (int) $id)->all())->toEqualCanonicalizing([(int) $case->id, (int) $otro->id]);

    app(TelemedicineCaseLogicalDeletionService::class)->delete($case, 'Eliminado para la vista de casos eliminados');

    $eliminados = CoordinationServiceCaseDeletion::applyDeletedCaseRowsScope(TelemedicineCase::query())->pluck('id')->all();

    expect($eliminados)->toContain($case->id)
        ->not->toContain($otro->id)
        ->and(CoordinationServiceCaseDeletion::casesFromRecords(collect([$case]))->first()?->status)->toBe(TelemedicineCaseDeletion::STATUS);
});

it('avisa qué beneficio quedó por encima del tope al restaurar', function (): void {
    $entitlement = fn (int $benefitId, ?int $quota, int $used): App\Support\ClinicalEntitlements\ClinicalEntitlement => new App\Support\ClinicalEntitlements\ClinicalEntitlement(
        benefitId: $benefitId,
        benefitLabel: 'BENEFICIO '.$benefitId,
        channel: App\Enums\ClinicalServiceChannel::Medication,
        telemedicineServiceListId: null,
        telemedicineServiceListName: null,
        quotaScope: $quota === null ? App\Enums\ClinicalQuotaScope::Unlimited : App\Enums\ClinicalQuotaScope::PerAffiliationYear,
        quota: $quota,
        used: $used,
        remaining: $quota === null ? null : max(0, $quota - $used),
        exhausted: $quota !== null && $used >= $quota,
    );

    $snapshot = new App\Support\ClinicalEntitlements\ClinicalEntitlementSnapshot(
        hasPlan: true,
        isComplete: true,
        missingBenefitLabels: [],
        entitlements: [$entitlement(1, 4, 5), $entitlement(2, 4, 4), $entitlement(3, null, 9)],
        blockingMessage: '',
    );

    expect(TelemedicineCaseLogicalDeletionService::exceededQuotaMessages($snapshot, [1, 2, 3, 99, 1], 'QA-1'))
        ->toBe(['Caso QA-1 · BENEFICIO 1: quedó con 5 de 4 usos (1 por encima del tope).']);
});
