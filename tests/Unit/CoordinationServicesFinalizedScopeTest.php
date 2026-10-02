<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\OperationCoordinationServices\Tables\OperationCoordinationServicesTable;
use App\Models\OperationCoordinationService;
use App\Models\TelemedicinePatientMedications;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

/**
 * @return list<int>
 */
function openServiceIds(): array
{
    return OperationCoordinationServicesTable::applyHideFullyFinalizedScope(OperationCoordinationService::query())
        ->pluck('id')->map(fn ($id): int => (int) $id)->all();
}

/**
 * @return list<int>
 */
function finalizedServiceIds(): array
{
    return OperationCoordinationServicesTable::applyFullyFinalizedScope(OperationCoordinationService::query())
        ->pluck('id')->map(fn ($id): int => (int) $id)->all();
}

it('cada servicio está en las pestañas de trabajo o en FINALIZADO, nunca en ambas ni en ninguna', function (): void {
    $open = openServiceIds();
    $finalized = finalizedServiceIds();
    $notReassigned = OperationCoordinationServicesTable::applyHideReassignedToTdgScope(OperationCoordinationService::query())
        ->pluck('id')->map(fn ($id): int => (int) $id)->all();

    if ($notReassigned === []) {
        $this->markTestSkipped('No hay servicios.');
    }

    expect(array_values(array_intersect($open, $finalized)))->toBe([])
        ->and(count($open) + count($finalized))->toBe(count($notReassigned))
        ->and(array_values(array_diff($notReassigned, $open, $finalized)))->toBe([]);
});

it('un servicio con todos sus ítems finalizados o cancelados sale de las pestañas de trabajo y entra en FINALIZADO', function (): void {
    $medication = TelemedicinePatientMedications::query()
        ->whereNotNull('operation_coordination_service_id')
        ->whereRaw('UPPER(TRIM(status)) IN (?, ?)', ['PENDIENTE', 'EN GESTION'])
        ->first();

    if ($medication === null) {
        $this->markTestSkipped('No hay medicamentos abiertos vinculados a un servicio.');
    }

    $serviceId = (int) $medication->operation_coordination_service_id;
    DB::table('operation_coordination_services')->where('id', $serviceId)->update(['status' => 'PENDIENTE']);

    expect(openServiceIds())->toContain($serviceId)
        ->and(finalizedServiceIds())->not->toContain($serviceId);

    foreach (['telemedicine_patient_medications', 'telemedicine_patient_labs', 'telemedicine_patient_studies', 'telemedicine_patient_specialties'] as $table) {
        DB::table($table)->where('operation_coordination_service_id', $serviceId)->update(['status' => 'FINALIZADO']);
    }
    DB::table('telemedicine_patient_medications')->where('id', $medication->id)->update(['status' => 'CANCELADA']);

    expect(openServiceIds())->not->toContain($serviceId)
        ->and(finalizedServiceIds())->toContain($serviceId);

    DB::table('telemedicine_patient_medications')->where('id', $medication->id)->update(['status' => ' en gestion ']);

    expect(openServiceIds())->toContain($serviceId)
        ->and(finalizedServiceIds())->not->toContain($serviceId);
});

it('los servicios sin ítems siguen en las pestañas de trabajo y los reasignados a TDG en ninguna', function (): void {
    $withoutItems = OperationCoordinationService::query()
        ->whereDoesntHave('telemedicinePatientMedications')
        ->whereDoesntHave('telemedicinePatientLabs')
        ->whereDoesntHave('telemedicinePatientStudies')
        ->whereDoesntHave('telemedicinePatientSpecialties')
        ->value('id');

    if ($withoutItems === null) {
        $this->markTestSkipped('No hay servicios sin ítems.');
    }

    DB::table('operation_coordination_services')->where('id', $withoutItems)->update(['status' => 'PENDIENTE']);

    expect(openServiceIds())->toContain((int) $withoutItems)
        ->and(finalizedServiceIds())->not->toContain((int) $withoutItems);

    DB::table('operation_coordination_services')->where('id', $withoutItems)->update(['status' => 'REASIGNADO A TDG']);

    expect(openServiceIds())->not->toContain((int) $withoutItems)
        ->and(finalizedServiceIds())->not->toContain((int) $withoutItems);
});

it('todas las pestañas de trabajo excluyen los finalizados y FINALIZADO usa el alcance por ítems', function (): void {
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Pages/ListOperationCoordinationServices.php');

    expect(substr_count($page, '->modifyQueryUsing(fn (Builder $query): Builder => self::openServices($query)'))->toBe(6)
        ->and($page)->toContain('->modifyQueryUsing(fn (Builder $query): Builder => OperationCoordinationServicesTable::applyFullyFinalizedScope($query)),')
        ->not->toContain("\$query->where('status', 'FINALIZADO')")
        ->toContain("'finalizado' => OperationCoordinationServicesTable::applyFullyFinalizedScope(");
});
