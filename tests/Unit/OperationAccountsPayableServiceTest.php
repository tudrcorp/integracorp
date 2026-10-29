<?php

declare(strict_types=1);

use App\Enums\StatusCuentaPorPagar;
use App\Filament\Operations\Resources\OperationAccountsPayables\Pages\ListOperationAccountsPayables;
use App\Filament\Operations\Resources\OperationAccountsPayables\Pages\ViewOperationAccountsPayable;
use App\Filament\Operations\Resources\OperationAccountsPayables\Widgets\AccountsPayableOverview;
use App\Models\OperationAccountsPayable;
use App\Models\OperationServiceOrder;
use App\Models\OperationServiceOrderItem;
use App\Models\User;
use App\Support\Operations\AccountsPayableServiceDisplay;
use Filament\Facades\Filament;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * @param  list<array{0: string, 1: float}>  $items
 */
function orderWithItems(string $type, array $items): OperationServiceOrder
{
    $order = new OperationServiceOrder(['service_type' => $type, 'order_number' => 'ORD-PEST']);
    $order->setRelation('operationServiceOrderItems', collect(array_map(
        fn (array $item): OperationServiceOrderItem => new OperationServiceOrderItem(['item_name' => $item[0], 'quantity' => $item[1]]),
        $items,
    )));

    return $order;
}

function operationsAnalyst(): User
{
    return User::factory()->make([
        'id' => 999_781,
        'name' => 'Analista Operaciones Pest',
        'email' => 'operaciones.pest@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'OPERACIONES'],
    ]);
}

it('traduce el tipo de la orden a un servicio legible', function (): void {
    expect(AccountsPayableServiceDisplay::type('LABORATORIOS')['label'])->toBe('Laboratorio')
        ->and(AccountsPayableServiceDisplay::type(' especialista ')['label'])->toBe('Consulta con especialista')
        ->and(AccountsPayableServiceDisplay::type('MEDICAMENTOS')['icon'])->toBe('heroicon-m-eye-dropper')
        ->and(AccountsPayableServiceDisplay::type('ODONTOLOGIA')['label'])->toBe('Odontologia')
        ->and(AccountsPayableServiceDisplay::type(null)['label'])->toBe('Sin tipo de servicio');
});

it('muestra qué se paga: los primeros ítems y «y N más», con la lista completa aparte', function (): void {
    $order = orderWithItems('LABORATORIOS', [['HEMATOLOGIA COMPLETA (HC)', 1], ['PCR', 1], ['VSG', 1], ['UROANALISIS', 1], ['GLICEMIA', 1]]);

    expect(AccountsPayableServiceDisplay::itemsLine($order))->toBe('HEMATOLOGIA COMPLETA (HC) · PCR · VSG y 2 más')
        ->and(AccountsPayableServiceDisplay::itemsTooltip($order))->toBe("HEMATOLOGIA COMPLETA (HC)\nPCR\nVSG\nUROANALISIS\nGLICEMIA")
        ->and(AccountsPayableServiceDisplay::itemsTooltip(orderWithItems('LABORATORIOS', [['PCR', 1]])))->toBeNull();
});

it('en medicamentos muestra la cantidad de cada uno', function (): void {
    $order = orderWithItems('MEDICAMENTOS', [['ACETAMINOFEN (180MG/5ML) JARABE', 1], ['RINOMAX GOTAS NASALES', 2.5]]);

    expect(AccountsPayableServiceDisplay::itemsLine($order))->toBe('ACETAMINOFEN (180MG/5ML) JARABE ×1 · RINOMAX GOTAS NASALES ×2,5')
        ->and(AccountsPayableServiceDisplay::itemsLine(null))->toBeNull();
});

it('reconoce los estatus heredados al contar', function (): void {
    expect(AccountsPayableServiceDisplay::storedStatusValues([StatusCuentaPorPagar::Pagada]))->toBe(['PAGADA', 'PAGADO', 'CANCELADA', 'CANCELADO'])
        ->and(AccountsPayableServiceDisplay::storedStatusValues([StatusCuentaPorPagar::PendientePorPagar]))->toContain('POR PAGAR');
});

it('la lista muestra el encabezado, la columna del servicio y las tarjetas filtran la tabla', function (): void {
    Filament::setCurrentPanel('operations');
    $this->actingAs(operationsAnalyst());

    $overview = AccountsPayableServiceDisplay::overview();

    expect($overview['pending_by_type'])->toHaveCount(4)
        ->and($overview['pending']['count'])->toBeGreaterThanOrEqual(array_sum(array_column($overview['pending_by_type'], 'count')));

    Livewire::test(AccountsPayableOverview::class)
        ->assertSee('Por pagar')
        ->assertSee('Por pagar según el servicio')
        ->call('shortcut', 'type:LABORATORIOS')
        ->assertDispatched('accounts-payable-shortcut', key: 'type:LABORATORIOS');

    $list = Livewire::test(ListOperationAccountsPayables::class)
        ->assertSuccessful()
        ->call('applyAccountsPayableShortcut', 'type:LABORATORIOS')
        ->assertSet('tableFilters.service_type.values', ['LABORATORIOS'])
        ->assertSet('tableFilters.payment_status.values', [StatusCuentaPorPagar::PendientePorPagar->value, StatusCuentaPorPagar::EnGestion->value]);

    $types = $list->instance()->getTableRecords()->map(fn (OperationAccountsPayable $record): ?string => $record->operationServiceOrder?->service_type)->unique()->values()->all();

    expect(array_diff($types, ['LABORATORIOS']))->toBe([]);

    $list->call('applyAccountsPayableShortcut', 'tipo-inventado')
        ->assertSet('tableFilters.service_type.values', ['LABORATORIOS'])
        ->call('applyAccountsPayableShortcut', 'all')
        ->assertSet('tableFilters.service_type.values', [])
        ->assertSet('tableFilters.payment_status.values', []);
});

it('la ficha muestra el detalle del servicio', function (): void {
    $record = OperationAccountsPayable::query()->whereHas('operationServiceOrder.operationServiceOrderItems')->latest('id')->first();

    if ($record === null) {
        $this->markTestSkipped('No hay cuentas por pagar con ítems en la base.');
    }

    Filament::setCurrentPanel('operations');
    $this->actingAs(operationsAnalyst());

    $item = (string) $record->operationServiceOrder->operationServiceOrderItems()->value('item_name');

    Livewire::test(ViewOperationAccountsPayable::class, ['record' => $record->getKey()])
        ->assertSuccessful()
        ->assertSee('Servicio por pagar')
        ->assertSee($item);
});

it('la búsqueda encuentra facturas por el servicio prestado', function (): void {
    if (! OperationAccountsPayable::query()->whereHas('operationServiceOrder.operationServiceOrderItems', fn ($query) => $query->where('item_name', 'like', '%HEMATOLOGIA%'))->exists()) {
        $this->markTestSkipped('No hay facturas de hematología en la base.');
    }

    Filament::setCurrentPanel('operations');
    $this->actingAs(operationsAnalyst());

    $records = Livewire::test(ListOperationAccountsPayables::class)
        ->searchTable('hematologia')
        ->instance()
        ->getTableRecords();

    expect($records)->not->toBeEmpty()
        ->and($records->every(fn (OperationAccountsPayable $record): bool => str_contains(
            mb_strtoupper(implode(' ', AccountsPayableServiceDisplay::items($record->operationServiceOrder))),
            'HEMATOLOGIA',
        )))->toBeTrue();
});
