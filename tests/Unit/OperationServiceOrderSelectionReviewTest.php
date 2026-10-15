<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\OperationServiceOrders\Pages\ListOperationServiceOrders;
use App\Models\OperationServiceOrder;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Al cargar la tabla se caducan órdenes vencidas (escribe en la base): todo va en una
 * transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Filament::setCurrentPanel('operations');
    $this->actingAs(User::factory()->make([
        'id' => 999999981,
        'email' => 'analista.os@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'OPERACIONES'],
    ]));
});

afterEach(fn () => DB::rollBack());

/**
 * Dos órdenes FINALIZADO y una CADUCADA, para probar que la vista de seleccionadas ignora los filtros.
 *
 * @return array{0: OperationServiceOrder, 1: OperationServiceOrder, 2: OperationServiceOrder}
 */
function selectionReviewOrders(): array
{
    $finalized = OperationServiceOrder::query()->where('status', 'FINALIZADO')->latest('id')->limit(2)->get();
    $expired = OperationServiceOrder::query()->where('status', 'CADUCADA')->latest('id')->firstOrFail();

    expect($finalized)->toHaveCount(2);

    return [$finalized[0], $finalized[1], $expired];
}

/**
 * El flujo del analista: busca, marca (la tabla copia la selección a `tableSelection`) y vacía el buscador.
 *
 * @param  list<OperationServiceOrder>  $orders
 */
function searchSelectAndClear(mixed $component, array $orders): mixed
{
    return $component
        ->set('tableSearch', (string) $orders[0]->order_number)
        ->set('tableSelection', array_map(fn (OperationServiceOrder $order): string => (string) $order->id, $orders))
        ->set('tableSearch', '');
}

it('la tabla enlaza la selección a una propiedad de Livewire', function (): void {
    $component = Livewire::test(ListOperationServiceOrders::class);

    expect($component->instance()->getTable()->getCurrentSelectionLivewireProperty())->toBe('tableSelection');
});

it('al dejar de buscar muestra solo las órdenes seleccionadas', function (): void {
    [$first, $second, $expired] = selectionReviewOrders();
    $other = OperationServiceOrder::query()->whereKeyNot([$first->id, $second->id, $expired->id])->latest('id')->firstOrFail();

    searchSelectAndClear(Livewire::test(ListOperationServiceOrders::class)->call('loadTable'), [$first, $expired])
        ->assertSet('reviewingSelection', true)
        ->assertCanSeeTableRecords([$first, $expired])
        ->assertCanNotSeeTableRecords([$second, $other])
        ->assertSee('Mostrando solo las 2 órdenes seleccionadas')
        ->assertSee('Ver todas las órdenes');
});

it('marcar sin haber buscado no reduce la tabla y ofrece ver solo las seleccionadas', function (): void {
    [$first, $second] = selectionReviewOrders();

    Livewire::test(ListOperationServiceOrders::class)
        ->call('loadTable')
        ->set('tableSelection', [(string) $first->id])
        ->call('gotoPage', 1)
        ->assertSet('reviewingSelection', false)
        ->assertCanSeeTableRecords([$first, $second])
        ->assertSee('Ver solo las seleccionadas')
        ->call('showSelectedRecordsOnly')
        ->assertCanSeeTableRecords([$first])
        ->assertCanNotSeeTableRecords([$second]);
});

it('la vista de seleccionadas ignora los filtros para que nada marcado quede oculto', function (): void {
    [$first, , $expired] = selectionReviewOrders();

    $component = Livewire::test(ListOperationServiceOrders::class)
        ->call('loadTable')
        ->set('tableDeferredFilters.status.values', ['FINALIZADO'])
        ->call('applyTableFilters')
        ->assertCanNotSeeTableRecords([$expired]);

    searchSelectAndClear($component, [$first, $expired])
        ->assertCanSeeTableRecords([$first, $expired]);
});

it('mientras se escribe en el buscador la tabla busca como siempre', function (): void {
    [$first, $second] = selectionReviewOrders();

    $component = Livewire::test(ListOperationServiceOrders::class)
        ->call('loadTable')
        ->set('tableSelection', [(string) $first->id])
        ->set('tableSearch', (string) $second->order_number);

    expect($component->instance()->isShowingSelectedOnly())->toBeFalse();
    $component->assertCanSeeTableRecords([$second])
        ->assertDontSee('Mostrando solo');
});

it('«Ver todas las órdenes» se mantiene aunque vuelva a buscar, hasta pedir de nuevo solo las seleccionadas', function (): void {
    [$first, $second] = selectionReviewOrders();

    searchSelectAndClear(Livewire::test(ListOperationServiceOrders::class)->call('loadTable'), [$first])
        ->call('showAllWhileSelectingRecords')
        ->assertCanSeeTableRecords([$first, $second])
        ->assertSee('La tabla muestra todas las órdenes')
        ->set('tableSearch', 'x')
        ->set('tableSearch', '')
        ->assertSet('showAllWhileSelecting', true)
        ->assertCanSeeTableRecords([$second])
        ->call('showSelectedRecordsOnly')
        ->assertCanSeeTableRecords([$first])
        ->assertCanNotSeeTableRecords([$second]);
});

it('quitar la selección vuelve a la tabla normal', function (): void {
    [$first, $second] = selectionReviewOrders();

    searchSelectAndClear(Livewire::test(ListOperationServiceOrders::class)->call('loadTable'), [$first])
        ->call('clearTableSelection')
        ->assertSet('tableSelection', [])
        ->assertSet('showAllWhileSelecting', false)
        ->assertSet('reviewingSelection', false)
        ->assertDispatched('deselectAllTableRecords')
        ->assertCanSeeTableRecords([$first, $second])
        ->assertDontSee('Mostrando solo');
});

it('si el analista desmarca todo, la próxima selección empieza de cero', function (): void {
    [$first] = selectionReviewOrders();

    searchSelectAndClear(Livewire::test(ListOperationServiceOrders::class)->call('loadTable'), [$first])
        ->call('showAllWhileSelectingRecords')
        ->set('tableSelection', [])
        ->assertSet('showAllWhileSelecting', false)
        ->assertSet('reviewingSelection', false);
});

it('ignora claves que no son ids válidos', function (): void {
    [$first] = selectionReviewOrders();

    $component = Livewire::test(ListOperationServiceOrders::class)
        ->set('tableSelection', ['abc', '0', '-4', (string) $first->id, (string) $first->id]);

    expect($component->instance()->selectedTableRecordIds())->toBe([$first->id]);
});
