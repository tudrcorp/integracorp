<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\OperationServiceOrders\Pages\ListOperationServiceOrders;
use App\Filament\Operations\Resources\OperationServiceOrders\Tables\OperationServiceOrdersTable;
use App\Models\OperationServiceOrder;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/*
 * El listado dispara OperationServiceOrderValidity::expireEligibleOrdersThrottled() al construir la consulta,
 * que sí escribe. Transacción revertida obligatoria (CLAUDE.md §0.2).
 */
beforeEach(fn () => DB::beginTransaction());

afterEach(fn () => DB::rollBack());

function serviceOrdersTable(): Table
{
    return OperationServiceOrdersTable::configure(Table::make(new ListOperationServiceOrders));
}

function operationsAnalystOrSkip(): User
{
    $user = User::query()
        ->where('status', 'ACTIVO')
        ->where('email', 'like', '%@tudrencasa.com')
        ->get()
        ->first(fn (User $user): bool => array_intersect(
            ['OPERACIONES', 'SUPERADMIN'],
            (array) $user->departament,
        ) !== []);

    if ($user === null) {
        test()->markTestSkipped('No hay analista activo de Operaciones en la base.');
    }

    return $user;
}

/*
 * ---------------------------------------------------------------------------
 * La selección sobrevive a búsquedas, filtros y páginas
 * ---------------------------------------------------------------------------
 */

it('no vacía la selección cuando el analista cambia la búsqueda o los filtros', function (): void {
    expect(serviceOrdersTable()->shouldDeselectAllRecordsWhenFiltered())->toBeFalse();
});

it('no limita la selección a la página visible', function (): void {
    expect(serviceOrdersTable()->selectsCurrentPageOnly())->toBeFalse();
});

it('no emite la orden de deseleccionar al escribir en la búsqueda', function (): void {
    Filament::setCurrentPanel('operations');

    Livewire::actingAs(operationsAnalystOrSkip())
        ->test(ListOperationServiceOrders::class)
        ->set('tableSearch', 'texto-que-no-coincide-con-nada')
        ->assertNotDispatched('deselectAllTableRecords');
});

/*
 * ---------------------------------------------------------------------------
 * «Seleccionar todos» guarda IDs concretos, no una consulta viva
 * ---------------------------------------------------------------------------
 */

it('desactiva el modo de selección por descarte para que «Seleccionar todos» fije IDs', function (): void {
    expect(serviceOrdersTable()->canTrackDeselectedRecords())->toBeFalse();
});

/*
 * ---------------------------------------------------------------------------
 * Los bulk actions reciben lo marcado en búsquedas anteriores
 * ---------------------------------------------------------------------------
 */

it('resuelve los registros marcados aunque la búsqueda actual ya no los devuelva', function (): void {
    $orden = OperationServiceOrder::query()->first();

    if ($orden === null) {
        $this->markTestSkipped('No hay órdenes de servicio en la base.');
    }

    Filament::setCurrentPanel('operations');

    $componente = Livewire::actingAs(operationsAnalystOrSkip())
        ->test(ListOperationServiceOrders::class)
        ->set('selectedTableRecords', [(string) $orden->getKey()])
        ->set('tableSearch', 'texto-que-no-coincide-con-nada');

    $seleccionados = $componente->instance()
        ->getSelectedTableRecords()
        ->map(fn (OperationServiceOrder $registro): int => (int) $registro->getKey())
        ->all();

    expect($seleccionados)->toBe([(int) $orden->getKey()]);
});
