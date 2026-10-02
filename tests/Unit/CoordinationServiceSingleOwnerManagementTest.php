<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\OperationCoordinationServices\OperationCoordinationServiceResource;
use App\Models\ObservationCase;
use App\Models\OperationCoordinationService;
use App\Models\User;
use App\Support\Operations\CoordinationServiceAccess;
use App\Support\Operations\CoordinationServiceItemsManager;
use App\Support\Operations\TakeCoordinationServiceManagementByTdg;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

function atenmediAnalyst(): User
{
    $user = User::query()->where('supplier_id', 15)->where('is_proveedor_amd', true)->first();

    if ($user === null) {
        test()->markTestSkipped('No hay analistas de ATENMEDI.');
    }

    return $user;
}

function tdgAnalyst(): User
{
    $user = User::query()
        ->whereNull('supplier_id')
        ->where(fn ($query) => $query->whereNull('is_proveedor_amd')->orWhere('is_proveedor_amd', false))
        ->where('email', 'like', '%@tudrencasa.com')
        ->first();

    if ($user === null) {
        test()->markTestSkipped('No hay analistas TDG.');
    }

    return $user;
}

/**
 * @return array<string, array<string, mixed>>
 */
function itemsByKey(OperationCoordinationService $service): array
{
    return CoordinationServiceItemsManager::associatedServiceItemsForManagement($service->fresh())
        ->keyBy('key')
        ->all();
}

function service1519(): OperationCoordinationService
{
    $service = OperationCoordinationService::query()->find(1519);

    if ($service === null || (int) $service->supplier_id !== 15) {
        test()->markTestSkipped('El servicio 1519 de ATENMEDI no existe en esta base.');
    }

    DB::table('telemedicine_patient_medications')->where('operation_coordination_service_id', 1519)->update(['status' => 'PENDIENTE']);
    DB::table('operation_coordination_services')->where('id', 1519)->update(['managed_by' => 'CORPORACION VMC, C.A. (ATENMEDI)', 'assigned_to_supplier_by_tdg' => false, 'status' => 'PENDIENTE']);

    return $service->fresh();
}

it('el analista de ATENMEDI puede gestionar el medicamento cubierto del caso 78206-0805, no el no cubierto', function (): void {
    $service = service1519();
    $this->actingAs(atenmediAnalyst());

    $items = itemsByKey($service);

    expect($items['medication:1478']['selectable'])->toBeTrue()
        ->and($items['medication:1479']['selectable'])->toBeFalse();
});

it('el analista TDG no gestiona el cubierto del proveedor pero sí el no cubierto', function (): void {
    $service = service1519();
    $this->actingAs(tdgAnalyst());

    $items = itemsByKey($service);

    expect($items['medication:1478']['selectable'])->toBeFalse()
        ->and($items['medication:1479']['selectable'])->toBeTrue();
});

it('TDG toma la gestión: todos los ítems pasan a TDG y queda en observaciones y bitácora', function (): void {
    $service = service1519();
    $tdg = tdgAnalyst();
    $this->actingAs($tdg);
    $observationsBefore = ObservationCase::query()->where('telemedicine_case_id', $service->telemedicine_case_id)->count();

    expect(TakeCoordinationServiceManagementByTdg::canBeTakenOver($service))->toBeTrue();

    $result = TakeCoordinationServiceManagementByTdg::execute($service, 'El proveedor no respondió en el plazo acordado', $tdg);

    expect($result->managed_by)->toBe('TDG')
        ->and($result->assigned_to_supplier_by_tdg)->toBeFalse()
        ->and((string) $result->observations)->toContain(TakeCoordinationServiceManagementByTdg::OBSERVATION_PREFIX)->toContain('Gestionaba: ATENMEDI')
        ->and(ObservationCase::query()->where('telemedicine_case_id', $service->telemedicine_case_id)->count())->toBe($observationsBefore + 1)
        ->and(TakeCoordinationServiceManagementByTdg::canBeTakenOver($result))->toBeFalse();

    expect(collect(itemsByKey($result))->only(['medication:1478', 'medication:1479'])->pluck('selectable')->all())->toBe([true, true]);

    $this->actingAs(atenmediAnalyst());
    expect(collect(itemsByKey($result))->only(['medication:1478', 'medication:1479'])->pluck('selectable')->all())->toBe([false, false]);
});

it('TDG no puede tomar la gestión si el proveedor ya tiene ítems EN GESTION', function (): void {
    $service = service1519();
    $tdg = tdgAnalyst();
    DB::table('telemedicine_patient_medications')->where('id', 1478)->update(['status' => 'EN GESTION']);

    expect(fn () => TakeCoordinationServiceManagementByTdg::execute($service, 'El proveedor no respondió en el plazo acordado', $tdg))
        ->toThrow(InvalidArgumentException::class, 'El proveedor tiene 1 ítem EN GESTION');

    expect($service->fresh()->managed_by)->toBe('CORPORACION VMC, C.A. (ATENMEDI)');
});

it('rechaza tomar la gestión sin ser analista TDG, con motivo corto, dos veces o en servicios cerrados', function (): void {
    $service = service1519();
    $tdg = tdgAnalyst();

    expect(fn () => TakeCoordinationServiceManagementByTdg::execute($service, 'Motivo suficientemente largo', atenmediAnalyst()))
        ->toThrow(InvalidArgumentException::class, 'Solo un analista TDG')
        ->and(fn () => TakeCoordinationServiceManagementByTdg::execute($service, '  corto   ', $tdg))
        ->toThrow(InvalidArgumentException::class, 'al menos 10 caracteres');

    TakeCoordinationServiceManagementByTdg::execute($service, 'Motivo suficientemente largo', $tdg);

    expect(fn () => TakeCoordinationServiceManagementByTdg::execute($service, 'Motivo suficientemente largo', $tdg))
        ->toThrow(InvalidArgumentException::class, 'ya la gestiona TDG');

    DB::table('operation_coordination_services')->where('id', 1519)->update(['managed_by' => 'CORPORACION VMC, C.A. (ATENMEDI)', 'status' => 'FINALIZADO']);

    expect(TakeCoordinationServiceManagementByTdg::canBeTakenOver($service->fresh()))->toBeFalse()
        ->and(fn () => TakeCoordinationServiceManagementByTdg::execute($service, 'Motivo suficientemente largo', $tdg))
        ->toThrow(InvalidArgumentException::class, 'No se puede tomar la gestión de una coordinación FINALIZADO');
});

it('tomar la gestión también retira la asignación al proveedor de un servicio TDG', function (): void {
    $service = service1519();
    DB::table('operation_coordination_services')->where('id', 1519)->update(['managed_by' => 'TDG', 'assigned_to_supplier_by_tdg' => true]);

    $result = TakeCoordinationServiceManagementByTdg::execute($service, 'Se retira la delegación al proveedor', tdgAnalyst());

    expect($result->assigned_to_supplier_by_tdg)->toBeFalse()
        ->and(CoordinationServiceAccess::itemOwner($result, false))->toBe(CoordinationServiceAccess::OWNER_TDG)
        ->and((string) $result->observations)->toContain('(asignado por TDG)');
});

it('un analista de proveedor no abre por URL un servicio que no ve en el cuadro', function (): void {
    $foreign = OperationCoordinationService::query()->whereNull('supplier_id')->value('id');

    if ($foreign === null) {
        $this->markTestSkipped('No hay servicios de TDG.');
    }

    $this->actingAs(atenmediAnalyst());
    expect(OperationCoordinationServiceResource::resolveRecordRouteBinding($foreign))->toBeNull();

    service1519();
    expect(OperationCoordinationServiceResource::resolveRecordRouteBinding(1519)?->getKey())->toBe(1519);

    $this->actingAs(tdgAnalyst());
    expect(OperationCoordinationServiceResource::resolveRecordRouteBinding($foreign)?->getKey())->toBe((int) $foreign);
});

it('la gestión de ítems bloquea el servicio antes de validar y la tabla ofrece «Tomar gestión TDG» solo a TDG', function (): void {
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Pages/ManageCoordinationServiceItems.php');
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Tables/OperationCoordinationServicesTable.php');

    $lock = strpos($page, '->lockForUpdate()->first();');
    $state = strpos($page, '$data = $this->form->getState();');

    expect($lock)->not->toBeFalse()
        ->and($lock)->toBeLessThan($state)
        ->and($page)->toContain('$this->getRecord()->refresh();')
        ->and($table)
        ->toContain("Action::make('takeCoordinationManagementByTdg')")
        ->toContain('TakeCoordinationServiceManagementByTdg::canBeTakenOver($record)')
        ->toContain('$takeCoordinationManagementByTdgAction,');
});
