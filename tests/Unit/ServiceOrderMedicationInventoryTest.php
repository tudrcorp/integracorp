<?php

declare(strict_types=1);

use App\Http\Controllers\OperationServiceOrderController;
use App\Models\OperationCoordinationService;
use App\Models\OperationDocumentList;
use App\Models\OperationInventory;
use App\Models\OperationInventoryMovement;
use App\Models\OperationInventoryOutflow;
use App\Models\OperationInventoryProductStock;
use App\Models\OperationInventoryUbication;
use App\Models\OperationServiceOrder;
use App\Models\OperationServiceOrderItem;
use App\Models\TelemedicinePatientMedications;
use App\Models\User;
use App\Support\Operations\CoordinationServiceCoveredItemsFinalizer;
use App\Support\Operations\OperationServiceOrderDocuments;
use App\Support\Operations\OperationServiceOrderFinalizationException;
use App\Support\Operations\ServiceOrderMedicationInventory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();
    Queue::fake();

    $coordination = OperationCoordinationService::query()
        ->whereNotNull('telemedicine_case_id')
        ->whereNotNull('telemedicine_patient_id')
        ->first();

    if (! $coordination instanceof OperationCoordinationService) {
        $this->markTestSkipped('No hay coordinaciones con caso y paciente para colgar los datos de prueba.');
    }

    $this->coordination = $coordination;
    $this->ubicationA = OperationInventoryUbication::query()->create(['name' => 'ALMACEN PRUEBA A', 'is_active' => true]);
    $this->ubicationB = OperationInventoryUbication::query()->create(['name' => 'ALMACEN PRUEBA B', 'is_active' => true]);

    $productId = DB::table('operation_inventory_products')->insertGetId([
        'code' => 'TEST-MED-DESPACHO',
        'name' => 'IBUPROFENO PRUEBA',
        'unit' => 'UND',
        'presentation' => DB::table('operation_inventory_products')->value('presentation') ?? 'UNIDAD',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->productId = $productId;
    $this->inventoryA = inventoryRowForTest($productId, $this->ubicationA, 10);
    $this->inventoryB = inventoryRowForTest($productId, $this->ubicationB, 2);

    Auth::setUser(User::factory()->make([
        'name' => 'ANALISTA PRUEBA',
        'departament' => ['SUPERADMIN', 'OPERACIONES'],
    ]));
});

afterEach(fn () => DB::rollBack());

function inventoryRowForTest(int $productId, OperationInventoryUbication $ubication, int $existence): OperationInventory
{
    $inventory = OperationInventory::query()->create([
        'operation_inventory_product_id' => $productId,
        'operation_inventory_ubication_id' => $ubication->id,
        'name' => 'IBUPROFENO PRUEBA',
        'unit' => 'UND',
        'operation_inventory_type_id' => (int) (DB::table('operation_inventory_types')->value('id') ?? 1),
        'existence' => $existence,
        'created_by' => 'TEST',
    ]);

    OperationInventoryProductStock::query()->create([
        'operation_inventory_product_id' => $productId,
        'operation_inventory_ubication_id' => $ubication->id,
        'existence' => $existence,
    ]);

    return $inventory;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function medicationForTest(OperationCoordinationService $coordination, array $attributes = []): TelemedicinePatientMedications
{
    return TelemedicinePatientMedications::query()->create([
        'telemedicine_patient_id' => $coordination->telemedicine_patient_id,
        'telemedicine_case_id' => $coordination->telemedicine_case_id,
        'operation_coordination_service_id' => $coordination->id,
        'medicine' => 'IBUPROFENO PRUEBA',
        'indications' => 'Cada 8 horas',
        'quantity' => 3,
        'status' => 'EN GESTION',
        ...$attributes,
    ]);
}

/**
 * @param  list<TelemedicinePatientMedications>  $medications
 */
function medicationOrderForTest(OperationCoordinationService $coordination, array $medications, bool $linked = true): OperationServiceOrder
{
    $order = OperationServiceOrder::query()->create([
        'operation_coordination_service_id' => $coordination->id,
        'order_number' => 'ORD-TEST-MED-'.uniqid(),
        'service_type' => 'MEDICAMENTOS',
        'status' => 'EN GESTION',
        'created_by' => 'TEST',
    ]);

    foreach ($medications as $medication) {
        OperationServiceOrderItem::query()->create([
            'operation_service_order_id' => $order->id,
            'telemedicine_patient_medication_id' => $linked ? $medication->id : null,
            'operation_inventory_id' => $linked ? $medication->operation_inventory_id : null,
            'item_name' => $medication->medicine,
            'category' => 'MEDICAMENTOS',
            'quantity' => (int) $medication->quantity,
        ]);
    }

    return $order;
}

/**
 * @return list<array<string, mixed>>
 */
function receiptDocumentsForTest(): array
{
    $type = OperationDocumentList::query()->firstOrCreate(['name' => OperationServiceOrderDocuments::MEDICATION_DELIVERY_RECEIPT]);

    return [[
        'document_name' => 'comprobante',
        'file_path' => 'operation-service-orders/0/documents/comprobante.pdf',
        'document_type_ids' => [(int) $type->id],
        'document_types' => [(string) $type->name],
        'uploaded_at' => now()->toDateTimeString(),
    ]];
}

it('descuenta del inventario al entregar, desde la ubicación elegida, y marca el medicamento', function (): void {
    $medication = medicationForTest($this->coordination, ['operation_inventory_id' => $this->inventoryA->id]);
    $order = medicationOrderForTest($this->coordination, [$medication]);

    $lines = ServiceOrderMedicationInventory::dispatch($order, $this->ubicationA->id, 'ANALISTA PRUEBA');

    expect($lines)->toBe(['IBUPROFENO PRUEBA × 3 (ALMACEN PRUEBA A)'])
        ->and($this->inventoryA->fresh()->existence)->toBe(7)
        ->and((int) OperationInventoryProductStock::query()->where('operation_inventory_ubication_id', $this->ubicationA->id)->value('existence'))->toBe(7)
        ->and($this->inventoryB->fresh()->existence)->toBe(2)
        ->and(OperationInventoryOutflow::query()->where('operation_inventory_id', $this->inventoryA->id)->value('observations'))->toContain($order->order_number)
        ->and(OperationInventoryMovement::query()->where('operation_inventory_id', $this->inventoryA->id)->value('quantity'))->toBe(3);

    $medication->refresh();

    expect($medication->inventory_dispatched_at)->not->toBeNull()
        ->and($medication->inventory_dispatch_source)->toBe(ServiceOrderMedicationInventory::DISPATCH_SOURCE_ORDER)
        ->and((int) $medication->inventory_dispatch_order_id)->toBe($order->id)
        ->and(ServiceOrderMedicationInventory::dispatch($order, $this->ubicationA->id, 'ANALISTA PRUEBA'))->toBe([])
        ->and($this->inventoryA->fresh()->existence)->toBe(7);
});

it('descuenta el mismo producto desde otra ubicación si el analista la elige', function (): void {
    $medication = medicationForTest($this->coordination, ['operation_inventory_id' => $this->inventoryA->id, 'quantity' => 2]);
    $order = medicationOrderForTest($this->coordination, [$medication]);

    ServiceOrderMedicationInventory::dispatch($order, $this->ubicationB->id, 'ANALISTA PRUEBA');

    expect($this->inventoryB->fresh()->existence)->toBe(0)
        ->and($this->inventoryA->fresh()->existence)->toBe(10)
        ->and((int) $order->fresh()->operation_inventory_ubication_id)->toBe($this->ubicationB->id);
});

it('no entrega nada si la existencia no alcanza, sumando líneas del mismo producto', function (): void {
    $first = medicationForTest($this->coordination, ['operation_inventory_id' => $this->inventoryB->id, 'quantity' => 1]);
    $second = medicationForTest($this->coordination, ['operation_inventory_id' => $this->inventoryB->id, 'quantity' => 2]);
    $order = medicationOrderForTest($this->coordination, [$first, $second]);

    expect(fn () => DB::transaction(fn () => ServiceOrderMedicationInventory::dispatch($order, $this->ubicationB->id, 'ANALISTA PRUEBA')))
        ->toThrow(OperationServiceOrderFinalizationException::class, 'Existencia insuficiente de «IBUPROFENO PRUEBA» en ALMACEN PRUEBA B: disponible 2, se entregan 3.');

    expect($this->inventoryB->fresh()->existence)->toBe(2)
        ->and($first->fresh()->inventory_dispatched_at)->toBeNull();
});

it('exige ubicación y que el producto exista en ella', function (): void {
    $medication = medicationForTest($this->coordination, ['operation_inventory_id' => $this->inventoryA->id]);
    $order = medicationOrderForTest($this->coordination, [$medication]);
    $empty = OperationInventoryUbication::query()->create(['name' => 'ALMACEN VACIO', 'is_active' => true]);

    expect(fn () => ServiceOrderMedicationInventory::dispatch($order, null, 'ANALISTA PRUEBA'))
        ->toThrow(OperationServiceOrderFinalizationException::class, 'Indique la ubicación de inventario desde la que se entregan los medicamentos: IBUPROFENO PRUEBA × 3.')
        ->and(fn () => ServiceOrderMedicationInventory::dispatch($order, $empty->id, 'ANALISTA PRUEBA'))
        ->toThrow(OperationServiceOrderFinalizationException::class, 'no está registrado en la ubicación ALMACEN VACIO');
});

it('no toca el stock de medicamentos fuera del inventario ni de los ya descontados en la consulta', function (): void {
    $freeText = medicationForTest($this->coordination, ['medicine' => 'JARABE DE FARMACIA', 'operation_inventory_id' => null]);
    $alreadyDispatched = medicationForTest($this->coordination, [
        'operation_inventory_id' => $this->inventoryA->id,
        'inventory_dispatched_at' => now(),
        'inventory_dispatch_source' => ServiceOrderMedicationInventory::DISPATCH_SOURCE_CONSULTATION,
    ]);
    $order = medicationOrderForTest($this->coordination, [$freeText, $alreadyDispatched]);

    expect(ServiceOrderMedicationInventory::requiresUbication($order))->toBeFalse()
        ->and(ServiceOrderMedicationInventory::dispatch($order, null, 'ANALISTA PRUEBA'))->toBe([])
        ->and($this->inventoryA->fresh()->existence)->toBe(10);
});

it('resuelve por nombre los ítems de órdenes creadas antes del vínculo', function (): void {
    $medication = medicationForTest($this->coordination, ['operation_inventory_id' => $this->inventoryA->id, 'medicine' => 'Ibuprofeno prueba ']);
    $order = medicationOrderForTest($this->coordination, [$medication], linked: false);

    expect(ServiceOrderMedicationInventory::pendingLines($order)->pluck('medication.id')->all())->toBe([$medication->id])
        ->and(ServiceOrderMedicationInventory::suggestedUbicationId($order))->toBe($this->ubicationA->id);
});

it('al finalizar una orden de medicamentos exige el comprobante de entrega y descuenta el stock', function (): void {
    $medication = medicationForTest($this->coordination, ['operation_inventory_id' => $this->inventoryA->id]);
    $order = medicationOrderForTest($this->coordination, [$medication]);
    $user = Auth::user();
    $otherDocument = [[...receiptDocumentsForTest()[0], 'document_types' => ['INFORME MÉDICO']]];

    expect(fn () => OperationServiceOrderDocuments::finalize($order, $otherDocument, $user, OperationServiceOrderDocuments::ORIGIN_ORDER_VIEW, $this->ubicationA->id))
        ->toThrow(OperationServiceOrderFinalizationException::class, 'cargue el comprobante de entrega');

    expect($order->fresh()->status)->toBe('EN GESTION')
        ->and($this->inventoryA->fresh()->existence)->toBe(10);

    $result = OperationServiceOrderDocuments::finalize($order, receiptDocumentsForTest(), $user, OperationServiceOrderDocuments::ORIGIN_ORDER_VIEW, $this->ubicationA->id);

    expect($result['order']->status)->toBe('FINALIZADO')
        ->and($this->inventoryA->fresh()->existence)->toBe(7)
        ->and($medication->fresh()->status)->toBe('FINALIZADO')
        ->and($medication->fresh()->inventory_dispatched_at)->not->toBeNull();
});

it('una orden de farmacia sin inventario se finaliza solo con el comprobante', function (): void {
    $freeText = medicationForTest($this->coordination, ['medicine' => 'JARABE DE FARMACIA', 'operation_inventory_id' => null]);
    $order = medicationOrderForTest($this->coordination, [$freeText]);

    $result = OperationServiceOrderDocuments::finalize($order, receiptDocumentsForTest(), Auth::user(), OperationServiceOrderDocuments::ORIGIN_SUPPLIER);

    expect($result['order']->status)->toBe('FINALIZADO')
        ->and($this->inventoryA->fresh()->existence)->toBe(10);
});

it('el comprobante suelto de la coordinación no cierra medicamentos que deben salir del inventario', function (): void {
    $pending = medicationForTest($this->coordination, ['operation_inventory_id' => $this->inventoryA->id, 'medicine' => 'IBUPROFENO PRUEBA']);
    $freeText = medicationForTest($this->coordination, ['medicine' => 'JARABE DE FARMACIA', 'operation_inventory_id' => null]);

    expect(CoordinationServiceCoveredItemsFinalizer::medicationsPendingInventoryDispatch([
        'medication:'.$pending->id,
        'medication:'.$freeText->id,
        'lab:'.$pending->id,
    ]))->toBe(['IBUPROFENO PRUEBA']);
});

it('la orden lleva el vínculo al medicamento y la cantidad recetada', function (): void {
    $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/OperationServiceOrderController.php');

    expect($controller)
        ->toContain("'telemedicine_patient_medication_id' => \$medicationId")
        ->toContain("'operation_inventory_id' => \$inventoryId");

    expect(OperationServiceOrderController::medicationLineQuantity(['quantity' => 4], (object) ['quantity' => 9]))->toBe(4)
        ->and(OperationServiceOrderController::medicationLineQuantity([], (object) ['quantity' => 9]))->toBe(9)
        ->and(OperationServiceOrderController::medicationLineQuantity([], (object) ['quantity' => null]))->toBe(1);
});

it('la consulta de telemedicina ya no descuenta medicamentos al recetar', function (): void {
    $create = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/CreateTelemedicineConsultationPatient.php');

    expect($create)
        ->not->toContain('TelemedicineMedicationInventoryDeductor')
        ->not->toContain('deductIfApplicable')
        ->toContain('ServiceOrderMedicationInventory');
});
