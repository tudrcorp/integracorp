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
