<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\OperationServiceOrders\Pages\ListOperationServiceOrders;
use App\Filament\Operations\Resources\OperationServiceOrders\Tables\OperationServiceOrdersTable;
use App\Models\OperationServiceOrder;
use App\Models\User;
use App\Support\Filament\Operations\SpecificBusinessUnitTableTools;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/*
 * El listado dispara la caducidad de órdenes al construir la consulta, que sí
 * escribe: transacción revertida obligatoria (CLAUDE.md §0.2).
 */
beforeEach(function (): void {
    DB::beginTransaction();

    $this->actingAs(User::factory()->create([
        'email' => 'qa.ordenes.un@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'OPERACIONES'],
    ]));

    Filament::setCurrentPanel('operations');
});

afterEach(fn () => DB::rollBack());

function serviceOrderWithSpecificUnit(bool $withUnit): ?OperationServiceOrder
{
    $hasUnit = fn ($patient) => $patient->whereNotNull('specific_business_unit')->where('specific_business_unit', '!=', '');

    return OperationServiceOrder::query()
        ->when(
            $withUnit,
            fn ($query) => $query->whereHas('operationCoordinationService.telemedicinePatient', $hasUnit),
            fn ($query) => $query->whereDoesntHave('operationCoordinationService.telemedicinePatient', $hasUnit),
        )
        ->with('operationCoordinationService.telemedicinePatient')
        ->first();
}

it('filtra las órdenes por unidad específica y por «Sin unidad específica»', function (): void {
    $conUnidad = serviceOrderWithSpecificUnit(true);
    $sinUnidad = serviceOrderWithSpecificUnit(false);

    if ($conUnidad === null || $sinUnidad === null) {
        $this->markTestSkipped('La base no tiene órdenes con y sin unidad específica.');
    }

    $unidad = (string) $conUnidad->operationCoordinationService->telemedicinePatient->specific_business_unit;
    $ids = [$conUnidad->getKey(), $sinUnidad->getKey()];

    $visibles = fn (array $valores): array => Livewire::test(ListOperationServiceOrders::class)
        ->filterTable('specific_business_unit', $valores)
        ->instance()
        ->getFilteredTableQuery()
        ->whereKey($ids)
        ->pluck('operation_service_orders.id')
        ->map(fn (mixed $id): int => (int) $id)
        ->sort()
        ->values()
        ->all();

    expect($visibles([$unidad]))->toBe([$conUnidad->getKey()])
        ->and($visibles([SpecificBusinessUnitTableTools::WITHOUT]))->toBe([$sinUnidad->getKey()])
        ->and($visibles([$unidad, SpecificBusinessUnitTableTools::WITHOUT]))->toBe(collect($ids)->sort()->values()->all());
});

it('la búsqueda de la tabla encuentra por unidad específica', function (): void {
    $conUnidad = serviceOrderWithSpecificUnit(true);
    $sinUnidad = serviceOrderWithSpecificUnit(false);

    if ($conUnidad === null || $sinUnidad === null) {
        $this->markTestSkipped('La base no tiene órdenes con y sin unidad específica.');
    }

    $unidad = (string) $conUnidad->operationCoordinationService->telemedicinePatient->specific_business_unit;

    $filtrada = Livewire::test(ListOperationServiceOrders::class)
        ->searchTable($unidad)
        ->instance()
        ->getFilteredTableQuery();

    expect((clone $filtrada)->whereKey($conUnidad->getKey())->exists())->toBeTrue()
        ->and((clone $filtrada)->whereKey($sinUnidad->getKey())->exists())->toBeFalse();
});

it('las opciones salen de las órdenes visibles e incluyen «Sin unidad específica»', function (): void {
    $conUnidad = serviceOrderWithSpecificUnit(true);

    if ($conUnidad === null) {
        $this->markTestSkipped('La base no tiene órdenes con unidad específica.');
    }

    $unidad = (string) $conUnidad->operationCoordinationService->telemedicinePatient->specific_business_unit;

    expect(OperationServiceOrdersTable::specificBusinessUnitOptions())
        ->toHaveKey($unidad, $unidad)
        ->toHaveKey(SpecificBusinessUnitTableTools::WITHOUT, SpecificBusinessUnitTableTools::WITHOUT_LABEL);
});
