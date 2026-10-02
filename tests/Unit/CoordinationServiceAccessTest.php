<?php

declare(strict_types=1);

use App\Models\OperationCoordinationService;
use App\Models\User;
use App\Support\Filament\Operations\OperationsSupplierScope;
use App\Support\Operations\AssignCoordinationServiceToSupplier;
use App\Support\Operations\CoordinationServiceAccess;
use App\Support\Operations\CoordinationServiceItemsManager;
use Illuminate\Support\Facades\Auth;

uses(Tests\TestCase::class);

function makeCoordinationAccessUser(?int $supplierId = null, bool $isProveedorAmd = false): User
{
    $user = new User;
    $user->forceFill([
        'id' => fake()->unique()->randomNumber(5),
        'name' => 'Access User',
        'email' => 'access-'.fake()->unique()->numerify('####').'@example.com',
        'supplier_id' => $supplierId,
        'is_proveedor_amd' => $isProveedorAmd,
        'departament' => ['OPERACIONES'],
        'status' => 'ACTIVO',
    ]);

    return $user;
}

afterEach(function (): void {
    Auth::logout();
});

it('detecta categorías de medicamento y laboratorio cubiertos', function (): void {
    expect(CoordinationServiceAccess::isCoveredMedicationOrLabCategory('Medicamento'))->toBeTrue()
        ->and(CoordinationServiceAccess::isCoveredMedicationOrLabCategory('Laboratorio'))->toBeTrue()
        ->and(CoordinationServiceAccess::isCoveredMedicationOrLabCategory('Estudio'))->toBeFalse()
        ->and(CoordinationServiceAccess::isCoveredMedicationOrLabCategory('Especialista'))->toBeFalse();
});

it('el proveedor ve coordinaciones propias con asignación TDG aunque no haya med/lab cubierto', function (): void {
    $assigned = new OperationCoordinationService([
        'supplier_id' => 10,
        'assigned_to_supplier_by_tdg' => true,
    ]);
    $otherSupplier = new OperationCoordinationService([
        'supplier_id' => 11,
        'assigned_to_supplier_by_tdg' => true,
    ]);

    expect(CoordinationServiceAccess::providerCanSeeCoordination($assigned, 10))->toBeTrue()
        ->and(CoordinationServiceAccess::providerCanSeeCoordination($otherSupplier, 10))->toBeFalse();
});

it('el cubierto sin inventario lo gestiona quien gestiona el servicio, no TDG por defecto', function (): void {
    Auth::login(makeCoordinationAccessUser(null));

    $noTdg = new OperationCoordinationService(['supplier_id' => 22, 'managed_by' => 'ATENMEDI', 'assigned_to_supplier_by_tdg' => false]);
    $tdg = new OperationCoordinationService(['supplier_id' => 22, 'managed_by' => 'TDG', 'assigned_to_supplier_by_tdg' => false]);

    expect(CoordinationServiceAccess::itemIsManageableByUser($noTdg, 'Medicamento', true))->toBeFalse()
        ->and(CoordinationServiceAccess::itemIsManageableByUser($noTdg, 'Medicamento', true, isCoveredWithoutInventory: true))->toBeFalse()
        ->and(CoordinationServiceAccess::itemIsManageableByUser($tdg, 'Medicamento', true, isCoveredWithoutInventory: true))->toBeTrue();
});

it('proveedor gestiona el cubierto sin inventario de su servicio', function (): void {
    Auth::login(makeCoordinationAccessUser(22));

    $noTdg = new OperationCoordinationService([
        'supplier_id' => 22,
        'managed_by' => 'ATENMEDI',
        'assigned_to_supplier_by_tdg' => false,
    ]);

    expect(CoordinationServiceAccess::itemIsManageableByUser($noTdg, 'Medicamento', true))->toBeTrue()
        ->and(CoordinationServiceAccess::itemIsManageableByUser($noTdg, 'Medicamento', true, isCoveredWithoutInventory: true))->toBeTrue();
});

it('TDG gestiona ítems cubiertos solo si managed_by es TDG', function (): void {
    Auth::login(makeCoordinationAccessUser(null));

    $noTdg = new OperationCoordinationService(['supplier_id' => 22, 'managed_by' => 'ATENMEDI', 'assigned_to_supplier_by_tdg' => false]);
    $tdg = new OperationCoordinationService(['supplier_id' => 22, 'managed_by' => 'TDG', 'assigned_to_supplier_by_tdg' => false]);

    expect(OperationsSupplierScope::authenticatedUserIsTdgAnalyst())->toBeTrue()
        ->and(CoordinationServiceAccess::itemIsManageableByUser($noTdg, 'Medicamento', true))->toBeFalse()
        ->and(CoordinationServiceAccess::itemIsManageableByUser($noTdg, 'Laboratorio', true))->toBeFalse()
        ->and(CoordinationServiceAccess::itemIsManageableByUser($noTdg, 'Estudio', true))->toBeFalse()
        ->and(CoordinationServiceAccess::itemIsManageableByUser($noTdg, 'Especialista', true))->toBeFalse()
        ->and(CoordinationServiceAccess::itemIsManageableByUser($tdg, 'Medicamento', true))->toBeTrue()
        ->and(CoordinationServiceAccess::itemIsManageableByUser($tdg, 'Laboratorio', true))->toBeTrue()
        ->and(CoordinationServiceAccess::itemIsManageableByUser($tdg, 'Estudio', true))->toBeTrue()
        ->and(CoordinationServiceAccess::itemIsManageableByUser($noTdg, 'Medicamento', false))->toBeTrue()
        ->and(CoordinationServiceAccess::itemIsManageableByUser($noTdg, 'Estudio', false))->toBeTrue();
});

it('proveedor gestiona med/lab cubiertos si managed_by no es TDG', function (): void {
    Auth::login(makeCoordinationAccessUser(22));

    $noTdg = new OperationCoordinationService([
        'supplier_id' => 22,
        'managed_by' => 'ATENMEDI',
        'assigned_to_supplier_by_tdg' => false,
    ]);
    $tdg = new OperationCoordinationService([
        'supplier_id' => 22,
        'managed_by' => 'TDG',
        'assigned_to_supplier_by_tdg' => false,
    ]);

    expect(OperationsSupplierScope::authenticatedUserIsTdgAnalyst())->toBeFalse()
        ->and(CoordinationServiceAccess::itemIsManageableByUser($noTdg, 'Medicamento', true))->toBeTrue()
        ->and(CoordinationServiceAccess::itemIsManageableByUser($noTdg, 'Laboratorio', true))->toBeTrue()
        ->and(CoordinationServiceAccess::itemIsManageableByUser($tdg, 'Medicamento', true))->toBeFalse()
        ->and(CoordinationServiceAccess::itemIsVisibleToUser($noTdg, 'Medicamento', true))->toBeTrue();
});

it('proveedor solo ve/gestiona otros ítems si TDG asignó la coordinación', function (): void {
    Auth::login(makeCoordinationAccessUser(33));

    $withoutAssign = new OperationCoordinationService([
        'supplier_id' => 33,
        'managed_by' => 'ATENMEDI',
        'assigned_to_supplier_by_tdg' => false,
    ]);
    $withAssign = new OperationCoordinationService([
        'supplier_id' => 33,
        'managed_by' => 'ATENMEDI',
        'assigned_to_supplier_by_tdg' => true,
    ]);

    expect(CoordinationServiceAccess::itemIsVisibleToUser($withoutAssign, 'Estudio', null))->toBeFalse()
        ->and(CoordinationServiceAccess::itemIsManageableByUser($withoutAssign, 'Estudio', null))->toBeFalse()
        ->and(CoordinationServiceAccess::itemIsVisibleToUser($withoutAssign, 'Especialista', false))->toBeFalse()
        ->and(CoordinationServiceAccess::itemIsVisibleToUser($withAssign, 'Estudio', null))->toBeTrue()
        ->and(CoordinationServiceAccess::itemIsManageableByUser($withAssign, 'Estudio', null))->toBeTrue()
        ->and(CoordinationServiceAccess::itemIsManageableByUser($withAssign, 'Medicamento', false))->toBeTrue();
});

it('TDG ve todos los ítems', function (): void {
    Auth::login(makeCoordinationAccessUser(null));

    $record = new OperationCoordinationService([
        'managed_by' => 'ATENMEDI',
        'assigned_to_supplier_by_tdg' => false,
    ]);

    expect(CoordinationServiceAccess::itemIsVisibleToUser($record, 'Estudio', null))->toBeTrue()
        ->and(CoordinationServiceAccess::itemIsVisibleToUser($record, 'Medicamento', true))->toBeTrue();
});

it('aplica scope SQL de visibilidad de proveedor con med/lab cubierto o asignación TDG', function (): void {
    $sql = CoordinationServiceAccess::applyProviderCoordinationVisibilityScope(
        OperationCoordinationService::query(),
        15,
    )->toSql();

    expect($sql)
        ->toContain('supplier_id')
        ->toContain('assigned_to_supplier_by_tdg')
        ->toContain('is_covered')
        ->toContain('UPPER(TRIM(type))');
});

it('OperationsSupplierScope usa el scope de visibilidad de proveedor en listados', function (): void {
    $scope = file_get_contents(dirname(__DIR__, 2).'/app/Support/Filament/Operations/OperationsSupplierScope.php');

    expect($scope)
        ->toContain('CoordinationServiceAccess::applyProviderCoordinationVisibilityScope')
        ->toContain('applyCoordinationListScope');
});

it('AssignCoordinationServiceToSupplier construye bitácora y rechaza no-TDG o motivo corto', function (): void {
    $supplier = new \App\Models\Supplier;
    $supplier->forceFill(['id' => 9, 'name' => 'Farmacia Demo']);

    expect(AssignCoordinationServiceToSupplier::buildBitacoraDescription($supplier, 'Motivo suficiente'))
        ->toContain(AssignCoordinationServiceToSupplier::OBSERVATION_PREFIX)
        ->toContain('Farmacia Demo')
        ->toContain('Motivo: Motivo suficiente');

    Auth::login(makeCoordinationAccessUser(44));

    expect(fn () => AssignCoordinationServiceToSupplier::execute(
        new OperationCoordinationService(['id' => 1]),
        9,
        'Motivo suficientemente largo',
        Auth::user(),
    ))->toThrow(InvalidArgumentException::class, 'Solo un analista TDG');

    Auth::logout();
    Auth::login(makeCoordinationAccessUser(null));

    expect(fn () => AssignCoordinationServiceToSupplier::execute(
        new OperationCoordinationService(['id' => 1]),
        9,
        'corto',
        Auth::user(),
    ))->toThrow(InvalidArgumentException::class, 'al menos 10 caracteres');
});

it('CoordinationServiceItemsManager filtra visibilidad y gestionabilidad por CoordinationServiceAccess', function (): void {
    $manager = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/CoordinationServiceItemsManager.php');

    expect($manager)
        ->toContain('CoordinationServiceAccess::itemIsManageableByUser')
        ->toContain('coveredItemIsManageableByTdg')
        ->toContain('isCoveredWithoutInventory');
});

it('coveredItemIsManageableByTdg delega en la matriz por rol autenticado', function (): void {
    Auth::login(makeCoordinationAccessUser(null));

    $noTdg = new OperationCoordinationService(['supplier_id' => 7, 'managed_by' => 'ATENMEDI']);

    expect(CoordinationServiceItemsManager::coveredItemIsManageableByTdg($noTdg, 'Medicamento', true))->toBeFalse();

    Auth::logout();
    Auth::login(makeCoordinationAccessUser(7));

    expect(CoordinationServiceItemsManager::coveredItemIsManageableByTdg($noTdg, 'Medicamento', true))->toBeTrue();
});

it('cada ítem tiene un solo responsable: TDG o el proveedor, nunca los dos', function (string $managedBy, bool $assigned, ?bool $coverage, string $owner): void {
    $record = new OperationCoordinationService(['supplier_id' => 22, 'managed_by' => $managedBy, 'assigned_to_supplier_by_tdg' => $assigned]);
    $tdg = makeCoordinationAccessUser(null);
    $provider = makeCoordinationAccessUser(22);
    $otherProvider = makeCoordinationAccessUser(23);

    expect(CoordinationServiceAccess::itemOwner($record, $coverage))->toBe($owner)
        ->and(CoordinationServiceAccess::itemIsManageableByUser($record, 'Medicamento', $coverage, $tdg))->toBe($owner === CoordinationServiceAccess::OWNER_TDG)
        ->and(CoordinationServiceAccess::itemIsManageableByUser($record, 'Medicamento', $coverage, $provider))->toBe($owner === CoordinationServiceAccess::OWNER_SUPPLIER)
        ->and(CoordinationServiceAccess::itemIsManageableByUser($record, 'Medicamento', $coverage, $otherProvider))->toBeFalse();
})->with([
    'servicio de proveedor, cubierto' => ['ATENMEDI', false, true, CoordinationServiceAccess::OWNER_SUPPLIER],
    'servicio de proveedor, no cubierto' => ['ATENMEDI', false, false, CoordinationServiceAccess::OWNER_TDG],
    'servicio de proveedor asignado, no cubierto' => ['ATENMEDI', true, false, CoordinationServiceAccess::OWNER_SUPPLIER],
    'servicio TDG, cubierto' => ['TDG', false, true, CoordinationServiceAccess::OWNER_TDG],
    'servicio TDG asignado, cubierto' => ['TDG', true, true, CoordinationServiceAccess::OWNER_TDG],
    'servicio TDG asignado, no cubierto' => ['TDG', true, false, CoordinationServiceAccess::OWNER_SUPPLIER],
    'servicio TDG, cobertura desconocida' => ['TDG', false, null, CoordinationServiceAccess::OWNER_TDG],
]);

it('un servicio sin proveedor siempre lo gestiona TDG', function (): void {
    $record = new OperationCoordinationService(['supplier_id' => null, 'managed_by' => 'ATENMEDI', 'assigned_to_supplier_by_tdg' => true]);

    expect(CoordinationServiceAccess::itemOwner($record, true))->toBe(CoordinationServiceAccess::OWNER_TDG)
        ->and(CoordinationServiceAccess::itemIsManageableByUser($record, 'Medicamento', true, makeCoordinationAccessUser(22)))->toBeFalse();
});

it('evalúa al usuario recibido y no al de la sesión', function (): void {
    Auth::login(makeCoordinationAccessUser(null));

    $record = new OperationCoordinationService(['supplier_id' => 22, 'managed_by' => 'ATENMEDI', 'assigned_to_supplier_by_tdg' => false]);

    expect(CoordinationServiceAccess::itemIsManageableByUser($record, 'Medicamento', true, makeCoordinationAccessUser(22)))->toBeTrue()
        ->and(CoordinationServiceAccess::itemIsManageableByUser($record, 'Medicamento', true))->toBeFalse();
});
