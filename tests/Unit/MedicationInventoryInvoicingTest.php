<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\OperationCoordinationServices\Pages\ManageCoordinationServiceItems;
use App\Filament\Operations\Resources\OperationMedicationFailures\Pages\ListOperationMedicationFailures;
use App\Filament\Operations\Resources\OperationMedicationInvoices\Pages\ListOperationMedicationInvoices;
use App\Filament\Operations\Resources\OperationMedicationInvoices\Pages\ViewOperationMedicationInvoice;
use App\Models\OperationAccountsReceivable;
use App\Models\OperationCoordinationService;
use App\Models\OperationInventory;
use App\Models\OperationInventoryOutflow;
use App\Models\OperationInventoryProduct;
use App\Models\OperationInventoryProductStock;
use App\Models\OperationInventoryUbication;
use App\Models\OperationMedicationFailure;
use App\Models\OperationMedicationInvoice;
use App\Models\OperationQuoteGenerator;
use App\Models\TelemedicineCase;
use App\Models\TelemedicinePatientMedications;
use App\Models\User;
use App\Support\Operations\CoordinationServiceItemFinalizationReversal;
use App\Support\Operations\CoordinationServiceItemsManager;
use App\Support\Operations\MedicationFailureRecorder;
use App\Support\Operations\MedicationInventoryCatalog;
use App\Support\Operations\MedicationInvoiceException;
use App\Support\Operations\MedicationInvoiceIssuer;
use App\Support\Operations\MedicationInvoiceLifecycle;
use App\Support\Operations\MedicationInvoiceWizard;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Escribe dentro de una transacción que siempre se revierte (la conexión puede ser la MySQL
 * de desarrollo). El usuario es `make()`, nunca se guarda.
 */
beforeEach(function (): void {
    // El paso de facturación directa está retirado desde el 10/11/2026; estos tests lo cubren dormido.
    \App\Support\Operations\MedicationInvoiceWizard::$directInvoicingEnabled = true;
    if (! Schema::hasTable('operation_medication_invoices') || MedicationInventoryCatalog::medicationCategoryId() === null) {
        $this->markTestSkipped('Faltan las tablas de facturas o la categoría Medicamentos en esta conexión.');
    }

    DB::beginTransaction();

    Filament::setCurrentPanel('operations');

    $this->actingAs(User::factory()->make([
        'id' => 999_780,
        'name' => 'Analista Pest',
        'email' => 'analista.pest@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'OPERACIONES'],
    ]));

    CoordinationServiceItemsManager::flushClinicalItemsCache();
});

afterEach(function (): void {
    DB::rollBack();
    \App\Support\Operations\MedicationInvoiceWizard::$directInvoicingEnabled = false;
});

/**
 * Servicio médico con un medicamento NO CUBIERTO pendiente y un producto en inventario
 * (costo 10, utilidad de la categoría, 5 unidades en un almacén).
 *
 * @return array{coordination: OperationCoordinationService, medication: TelemedicinePatientMedications, product: OperationInventoryProduct, ubication: OperationInventoryUbication, inventory: OperationInventory, stock: OperationInventoryProductStock}
 */
function miiScenario(int $existence = 5, float $cost = 10.0, string $medicine = 'PESTOFENO TABLETAS 400MG'): array
{
    $case = TelemedicineCase::query()->whereNotNull('telemedicine_patient_id')->orderBy('id')->first();

    if (! $case instanceof TelemedicineCase) {
        test()->markTestSkipped('No hay casos de telemedicina para armar el escenario.');
    }

    $category = App\Models\OperationInventoryProductCategory::query()->findOrFail(MedicationInventoryCatalog::medicationCategoryId());
    $category->forceFill(['profit_percentage' => 30])->save();

    $coordination = OperationCoordinationService::query()->create([
        'telemedicine_case_id' => $case->id,
        'telemedicine_patient_id' => $case->telemedicine_patient_id,
        'patient' => 'PACIENTE PEST',
        'ci_patient' => 'V-0000000',
        'contractor' => 'PEST',
        'relationship_patient' => 'TITULAR',
        'symptoms_diagnosis' => 'PRUEBA',
        'status' => 'PENDIENTE',
        'managed_by' => 'TDG',
        'created_by' => 'PEST',
    ]);

    $medication = TelemedicinePatientMedications::query()->create([
        'telemedicine_patient_id' => $case->telemedicine_patient_id,
        'telemedicine_case_id' => $case->id,
        'medicine' => $medicine,
        'indications' => 'UNA CADA 8 HORAS',
        'quantity' => 2,
        'status' => 'PENDIENTE',
        'is_covered' => false,
        'operation_coordination_service_id' => $coordination->id,
    ]);

    $ubication = OperationInventoryUbication::query()->create(['name' => 'ALMACEN PEST', 'is_active' => true]);

    $product = OperationInventoryProduct::query()->create([
        'operation_inventory_product_category_id' => $category->id,
        'code' => 'PEST-'.uniqid(),
        'name' => 'PESTOFENO (400MG) 10 TABLETAS',
        'cost' => $cost,
        'unit' => 'UNIDAD',
        'presentation' => 'CAJA',
        'is_active' => true,
        'created_by' => 'PEST',
    ]);

    $inventory = OperationInventory::query()->create([
        'operation_inventory_product_id' => $product->id,
        'operation_inventory_ubication_id' => $ubication->id,
        'name' => $product->name,
        'unit' => 'UNIDAD',
        'existence' => $existence,
        'operation_inventory_type_id' => 1,
        'created_by' => 'PEST',
    ]);

    $stock = OperationInventoryProductStock::query()->create([
        'operation_inventory_product_id' => $product->id,
        'operation_inventory_ubication_id' => $ubication->id,
        'existence' => $existence,
        'created_by' => 'PEST',
    ]);

    CoordinationServiceItemsManager::flushClinicalItemsCache();

    return compact('coordination', 'medication', 'product', 'ubication', 'inventory', 'stock');
}

/**
 * @return list<array{medication_id: int, key: string, lines: list<array{product_id: int, ubication_id: int, quantity: int}>}>
 */
function miiPlan(array $s, int $quantity = 2): array
{
    return [[
        'medication_id' => (int) $s['medication']->id,
        'key' => 'medication:'.$s['medication']->id,
        'lines' => [['product_id' => (int) $s['product']->id, 'ubication_id' => (int) $s['ubication']->id, 'quantity' => $quantity]],
    ]];
}

it('busca por nombre y no por la dosis sola', function (): void {
    expect(MedicationInventoryCatalog::tokens('DIKLASON TABLETAS 100MG'))->toBe(['DIKLASON', '100MG'])
        ->and(MedicationInventoryCatalog::isDoseToken('100MG'))->toBeTrue()
        ->and(MedicationInventoryCatalog::isDoseToken('DIKLASON'))->toBeFalse();

    $s = miiScenario();

    expect(MedicationInventoryCatalog::search('PESTOFENO 400MG')->pluck('id'))->toContain($s['product']->id)
        ->and(MedicationInventoryCatalog::search('NOEXISTE 400MG')->pluck('id'))->not->toContain($s['product']->id);
});

it('toma la menor existencia entre las dos tablas de inventario', function (): void {
    expect(MedicationInventoryCatalog::sellableExistence(10, 3))->toBe(3)
        ->and(MedicationInventoryCatalog::sellableExistence(2, 8))->toBe(2)
        ->and(MedicationInventoryCatalog::sellableExistence(5, null))->toBe(0)
        ->and(MedicationInventoryCatalog::sellableExistence(-1, 4))->toBe(0);
});

it('separa lo que se factura de lo que va a cotización', function (): void {
    $s = miiScenario();
    $key = 'medication:'.$s['medication']->id;

    $invoiceRows = ['row' => ['key' => $key, 'generate_quote' => false]];
    $quoteRows = ['row' => ['key' => $key, 'generate_quote' => true]];

    expect(MedicationInvoiceWizard::applies($s['coordination'], [$key]))->toBeTrue()
        ->and(MedicationInvoiceWizard::invoiceKeys($s['coordination'], [$key], $invoiceRows))->toBe([$key])
        ->and(MedicationInvoiceWizard::quoteKeys($s['coordination'], [$key], $invoiceRows))->toBe([])
        ->and(MedicationInvoiceWizard::invoiceKeys($s['coordination'], [$key], $quoteRows))->toBe([])
        ->and(MedicationInvoiceWizard::quoteKeys($s['coordination'], [$key], $quoteRows))->toBe([$key])
        ->and(CoordinationServiceItemsManager::buildManageQuoteLineItemsDefault($s['coordination'], [$key], [], false, [$key]))->toBe([])
        ->and(CoordinationServiceItemsManager::buildManageQuoteLineItemsDefault($s['coordination'], [$key]))->toHaveCount(1);
});

it('emite la factura: descuenta ambas existencias, finaliza el ítem y crea la cuenta por cobrar', function (): void {
    $s = miiScenario();

    $invoice = MedicationInvoiceIssuer::issue($s['coordination'], miiPlan($s, 2));

    expect($invoice->invoice_number)->toBe(OperationMedicationInvoice::formatNumber((int) $invoice->id))
        ->and($invoice->status)->toBe(OperationMedicationInvoice::STATUS_PENDING_PAYMENT)
        ->and((float) $invoice->total_usd)->toBe(26.0)
        ->and((float) $invoice->total_ves)->toBe(round(26.0 * (float) $invoice->bcv_rate, 2));

    $item = $invoice->items()->first();
    expect($item->quantity)->toBe(2)
        ->and((float) $item->unit_cost_usd)->toBe(10.0)
        ->and((float) $item->profit_percentage)->toBe(30.0)
        ->and((float) $item->unit_price_usd)->toBe(13.0)
        ->and($item->prescribed_name)->toBe('PESTOFENO TABLETAS 400MG');

    expect($s['inventory']->fresh()->existence)->toBe(3)
        ->and($s['stock']->fresh()->existence)->toBe(3);

    $medication = $s['medication']->fresh();
    expect($medication->status)->toBe('FINALIZADO')
        ->and($medication->inventory_dispatch_source)->toBe(MedicationInvoiceIssuer::DISPATCH_SOURCE_INVOICE)
        ->and($medication->inventory_dispatched_at)->not->toBeNull();

    $receivable = OperationAccountsReceivable::query()->where('operation_medication_invoice_id', $invoice->id)->first();
    expect($receivable)->not->toBeNull()
        ->and($receivable->status)->toBe(OperationAccountsReceivable::STATUS_PENDING_PAYMENT)
        ->and((float) $receivable->quote_amount_usd)->toBe(26.0);

    expect(OperationInventoryOutflow::query()->where('operation_inventory_product_id', $s['product']->id)->sum('quantity'))->toEqual(2)
        ->and($invoice->events()->count())->toBe(1);
});

it('congela el precio: cambiar el costo después no altera la factura', function (): void {
    $s = miiScenario();
    $invoice = MedicationInvoiceIssuer::issue($s['coordination'], miiPlan($s, 1));

    $s['product']->forceFill(['cost' => 99])->save();

    expect((float) $invoice->fresh()->total_usd)->toBe(13.0)
        ->and((float) $invoice->items()->first()->unit_price_usd)->toBe(13.0);
});

it('rechaza facturar sin existencia suficiente y no deja nada a medias', function (): void {
    $s = miiScenario(existence: 1);

    expect(fn () => DB::transaction(fn () => MedicationInvoiceIssuer::issue($s['coordination'], miiPlan($s, 3))))
        ->toThrow(MedicationInvoiceException::class, 'Existencia insuficiente');

    expect(OperationMedicationInvoice::query()->where('operation_coordination_service_id', $s['coordination']->id)->exists())->toBeFalse()
        ->and($s['inventory']->fresh()->existence)->toBe(1)
        ->and($s['medication']->fresh()->status)->toBe('PENDIENTE');
});

it('un producto sin costo exige cargarlo para facturar', function (): void {
    $s = miiScenario(cost: 0);

    expect(MedicationInvoiceIssuer::validationMessage($s['coordination'], miiPlan($s, 1)))->toContain('Cárguelo')
        ->and(MedicationInvoiceWizard::productIsSelectable($s['product']->id))->toBeTrue()
        ->and(MedicationInvoiceWizard::productNeedsCost($s['product']->id))->toBeTrue();
});

it('carga el costo al facturar: lo guarda en el producto y calcula el precio con el % de la categoría', function (): void {
    $s = miiScenario(cost: 0);
    $plan = miiPlan($s, 2);
    $plan[0]['lines'][0]['new_cost'] = 20.0;

    expect(MedicationInvoiceIssuer::validationMessage($s['coordination'], $plan))->toBeNull()
        ->and((float) $s['product']->fresh()->cost)->toBe(0.0);

    $invoice = MedicationInvoiceIssuer::issue($s['coordination'], $plan);

    expect((float) $s['product']->fresh()->cost)->toBe(20.0)
        ->and((float) $invoice->items()->first()->unit_cost_usd)->toBe(20.0)
        ->and((float) $invoice->items()->first()->unit_price_usd)->toBe(26.0)
        ->and((float) $invoice->total_usd)->toBe(52.0)
        ->and($invoice->events()->first()->description)->toContain('Costos cargados al facturar')
        ->and($invoice->events()->first()->payload['loaded_costs'][0]['cost'])->toEqual(20.0);
});

it('no pisa un costo que ya existe aunque el formulario traiga otro', function (): void {
    $s = miiScenario(cost: 10);
    $plan = miiPlan($s, 1);
    $plan[0]['lines'][0]['new_cost'] = 99.0;

    $invoice = MedicationInvoiceIssuer::issue($s['coordination'], $plan);

    expect((float) $s['product']->fresh()->cost)->toBe(10.0)
        ->and((float) $invoice->items()->first()->unit_price_usd)->toBe(13.0);
});

it('rechaza un costo cargado fuera de rango', function (): void {
    $s = miiScenario(cost: 0);
    $plan = miiPlan($s, 1);
    $plan[0]['lines'][0]['new_cost'] = MedicationInvoiceIssuer::MAX_LOADED_COST + 1;

    expect(MedicationInvoiceIssuer::validationMessage($s['coordination'], $plan))->toContain('demasiado alto');
});

it('sigue bloqueando el producto sin existencia', function (): void {
    $s = miiScenario(existence: 0);

    expect(MedicationInvoiceWizard::productIsSelectable($s['product']->id))->toBeFalse()
        ->and(MedicationInventoryCatalog::optionLabel($s['product'], 0))->toContain('no disponible (sin existencia)')
        ->and(MedicationInventoryCatalog::labelIsUnavailable(MedicationInventoryCatalog::optionLabel($s['product'], 0)))->toBeTrue()
        ->and(MedicationInventoryCatalog::labelIsUnavailable(MedicationInventoryCatalog::optionLabel($s['product'], 3)))->toBeFalse();
});

it('no factura dos veces el mismo medicamento', function (): void {
    $s = miiScenario();
    MedicationInvoiceIssuer::issue($s['coordination'], miiPlan($s, 1));

    expect(fn () => MedicationInvoiceIssuer::issue($s['coordination'], miiPlan($s, 1)))
        ->toThrow(MedicationInvoiceException::class);

    expect(OperationMedicationInvoice::query()->where('operation_coordination_service_id', $s['coordination']->id)->count())->toBe(1);
});

it('rechaza un medicamento cubierto: solo lo no cubierto se factura', function (): void {
    $s = miiScenario();
    $s['medication']->forceFill(['is_covered' => true])->save();
    CoordinationServiceItemsManager::flushClinicalItemsCache();

    expect(MedicationInvoiceIssuer::validationMessage($s['coordination'], miiPlan($s, 1)))->toContain('NO CUBIERTO');
});

it('registra el pago: factura y cuenta por cobrar quedan pagadas y ya no se anulan', function (): void {
    $s = miiScenario();
    $invoice = MedicationInvoiceIssuer::issue($s['coordination'], miiPlan($s, 1));

    $paid = MedicationInvoiceLifecycle::registerPayment($invoice, [
        'paid_at' => now()->toDateString(),
        'payment_method' => 'PAGO_MOVIL',
        'payment_reference' => '123456',
    ]);

    expect($paid->status)->toBe(OperationMedicationInvoice::STATUS_PAID)
        ->and(OperationAccountsReceivable::query()->where('operation_medication_invoice_id', $invoice->id)->value('status'))->toBe(OperationAccountsReceivable::STATUS_PAID)
        ->and(fn () => MedicationInvoiceLifecycle::annul($paid, 'Intento de anular una pagada'))->toThrow(MedicationInvoiceException::class);
});

it('exige referencia salvo en efectivo y rechaza fechas futuras', function (): void {
    $s = miiScenario();
    $invoice = MedicationInvoiceIssuer::issue($s['coordination'], miiPlan($s, 1));

    expect(fn () => MedicationInvoiceLifecycle::registerPayment($invoice, ['paid_at' => now()->toDateString(), 'payment_method' => 'ZELLE']))
        ->toThrow(MedicationInvoiceException::class, 'referencia')
        ->and(fn () => MedicationInvoiceLifecycle::registerPayment($invoice, ['paid_at' => now()->addDays(2)->toDateString(), 'payment_method' => 'EFECTIVO_USD']))
        ->toThrow(MedicationInvoiceException::class, 'posterior');
});

it('anular devuelve la existencia, reabre el medicamento y anula la cuenta por cobrar', function (): void {
    $s = miiScenario();
    $invoice = MedicationInvoiceIssuer::issue($s['coordination'], miiPlan($s, 2));
    $s['coordination']->forceFill(['status' => 'FINALIZADO'])->save();

    expect(fn () => MedicationInvoiceLifecycle::annul($invoice, 'corto'))->toThrow(MedicationInvoiceException::class);

    $annulled = MedicationInvoiceLifecycle::annul($invoice, 'El paciente no retiró el medicamento');

    expect($annulled->status)->toBe(OperationMedicationInvoice::STATUS_ANNULLED)
        ->and($s['inventory']->fresh()->existence)->toBe(5)
        ->and($s['stock']->fresh()->existence)->toBe(5)
        ->and($s['medication']->fresh()->status)->toBe('PENDIENTE')
        ->and($s['medication']->fresh()->inventory_dispatched_at)->toBeNull()
        ->and($s['coordination']->fresh()->status)->toBe('EN GESTION')
        ->and(OperationAccountsReceivable::query()->where('operation_medication_invoice_id', $invoice->id)->value('status'))->toBe(OperationAccountsReceivable::STATUS_ANNULLED)
        ->and($annulled->items()->first()->restocked_at)->not->toBeNull();

    // Tras anular, el medicamento puede facturarse otra vez.
    CoordinationServiceItemsManager::flushClinicalItemsCache();
    expect(MedicationInvoiceIssuer::validationMessage($s['coordination']->fresh(), miiPlan($s, 1)))->toBeNull();
});

it('«Reversar ítems finalizados» no reabre un medicamento facturado', function (): void {
    $s = miiScenario();
    $invoice = MedicationInvoiceIssuer::issue($s['coordination'], miiPlan($s, 1));

    $reversed = CoordinationServiceItemFinalizationReversal::reverse(
        $s['coordination']->fresh(),
        'medication',
        (int) $s['medication']->id,
        'PESTOFENO TABLETAS 400MG',
        'Prueba de reverso con una nota suficientemente larga',
        true,
    );

    expect($reversed)->toBeFalse()
        ->and($s['medication']->fresh()->status)->toBe('FINALIZADO')
        ->and($invoice->fresh()->status)->toBe(OperationMedicationInvoice::STATUS_PENDING_PAYMENT);
});

it('registra la falla una sola vez por medicamento pendiente', function (): void {
    $s = miiScenario();
    $row = ['key' => 'medication:'.$s['medication']->id, 'generate_quote' => true, 'failure_reason' => OperationMedicationFailure::REASON_NOT_IN_INVENTORY, 'failure_notes' => 'Comprar caja de 10'];

    $first = MedicationFailureRecorder::recordFromRows($s['coordination'], [$row]);
    $second = MedicationFailureRecorder::recordFromRows($s['coordination'], [$row]);

    expect($first)->toHaveCount(1)
        ->and($second)->toHaveCount(0)
        ->and($first[0]->status)->toBe(OperationMedicationFailure::STATUS_PENDING_PURCHASE)
        ->and($first[0]->medicine_name)->toBe('PESTOFENO TABLETAS 400MG')
        ->and(fn () => MedicationFailureRecorder::recordFromRows($s['coordination'], [[...$row, 'failure_reason' => 'INVENTADO']]))
        ->toThrow(MedicationInvoiceException::class);

    $resolved = MedicationFailureRecorder::resolve($first[0], OperationMedicationFailure::STATUS_ACQUIRED, 'Comprado en farmacia', (int) $s['product']->id);
    expect($resolved->status)->toBe(OperationMedicationFailure::STATUS_ACQUIRED)
        ->and($resolved->operation_inventory_product_id)->toBe($s['product']->id)
        ->and(fn () => MedicationFailureRecorder::resolve($resolved, OperationMedicationFailure::STATUS_DISCARDED, 'Otra vez'))
        ->toThrow(MedicationInvoiceException::class);
});

it('confirma la gestión desde el asistente: factura, finaliza y no crea cotización', function (): void {
    $s = miiScenario();
    $key = 'medication:'.$s['medication']->id;
    $quotesBefore = OperationQuoteGenerator::query()->count();

    $page = Livewire::test(ManageCoordinationServiceItems::class, ['record' => $s['coordination']->getKey()])
        ->assertSuccessful()
        ->set('data.managed_service_item_keys', [$key]);

    $rows = $page->get('data.'.MedicationInvoiceWizard::ROWS_FIELD);
    $rowKey = array_key_first($rows);
    $lineKey = array_key_first($rows[$rowKey]['products']);

    $page->set('data.'.MedicationInvoiceWizard::ROWS_FIELD.'.'.$rowKey.'.products.'.$lineKey.'.product_id', $s['product']->id)
        ->set('data.'.MedicationInvoiceWizard::ROWS_FIELD.'.'.$rowKey.'.products.'.$lineKey.'.ubication_id', $s['ubication']->id)
        ->set('data.'.MedicationInvoiceWizard::ROWS_FIELD.'.'.$rowKey.'.products.'.$lineKey.'.quantity', 2)
        ->assertSee('Medicamentos desde inventario')
        ->call('save')
        ->assertHasNoErrors();

    $invoice = OperationMedicationInvoice::query()->where('operation_coordination_service_id', $s['coordination']->id)->first();

    expect($invoice)->not->toBeNull()
        ->and((float) $invoice->total_usd)->toBe(26.0)
        ->and($s['medication']->fresh()->status)->toBe('FINALIZADO')
        ->and($s['coordination']->fresh()->status)->toBe('FINALIZADO')
        ->and($s['stock']->fresh()->existence)->toBe(3)
        ->and(OperationQuoteGenerator::query()->count())->toBe($quotesBefore);
});

it('desde el asistente se elige un sustituto sin costo, se carga el costo y se factura', function (): void {
    $s = miiScenario(cost: 0, medicine: 'MARCAINEXISTENTE TABLETAS');
    $key = 'medication:'.$s['medication']->id;

    $page = Livewire::test(ManageCoordinationServiceItems::class, ['record' => $s['coordination']->getKey()])
        ->set('data.managed_service_item_keys', [$key]);

    $rows = $page->get('data.'.MedicationInvoiceWizard::ROWS_FIELD);
    $rowKey = array_key_first($rows);
    $lineKey = array_key_first($rows[$rowKey]['products']);
    $base = 'data.'.MedicationInvoiceWizard::ROWS_FIELD.'.'.$rowKey;

    // El nombre recetado no está; el analista busca el sustituto por otro nombre.
    expect(MedicationInventoryCatalog::search('MARCAINEXISTENTE TABLETAS'))->toBeEmpty();

    $page->set($base.'.search_term', 'pestofeno')
        ->set($base.'.products.'.$lineKey.'.product_id', $s['product']->id)
        ->set($base.'.products.'.$lineKey.'.ubication_id', $s['ubication']->id)
        ->set($base.'.products.'.$lineKey.'.quantity', 1)
        ->assertSee('Costo del producto (US$)')
        ->call('save')
        ->assertHasErrors();

    expect(OperationMedicationInvoice::query()->where('operation_coordination_service_id', $s['coordination']->id)->exists())->toBeFalse();

    $page->set($base.'.products.'.$lineKey.'.new_cost', 8)
        ->call('save')
        ->assertHasNoErrors();

    $invoice = OperationMedicationInvoice::query()->where('operation_coordination_service_id', $s['coordination']->id)->first();

    expect($invoice)->not->toBeNull()
        ->and((float) $invoice->total_usd)->toBe(10.4)
        ->and((float) $s['product']->fresh()->cost)->toBe(8.0)
        ->and($invoice->items()->first()->prescribed_name)->toBe('MARCAINEXISTENTE TABLETAS')
        ->and($invoice->items()->first()->product_name)->toBe('PESTOFENO (400MG) 10 TABLETAS');
});

it('abre las listas y la ficha de la factura', function (): void {
    $s = miiScenario();
    $invoice = MedicationInvoiceIssuer::issue($s['coordination'], miiPlan($s, 1));

    Livewire::test(ListOperationMedicationInvoices::class)->assertSuccessful()->assertSee('Pendientes por cobrar');
    Livewire::test(ListOperationMedicationFailures::class)->assertSuccessful()->assertSee('Fallas de medicamentos');
    Livewire::test(ViewOperationMedicationInvoice::class, ['record' => $invoice->getKey()])
        ->assertSuccessful()
        ->assertSee($invoice->invoice_number)
        ->assertSee('PESTOFENO (400MG) 10 TABLETAS')
        ->assertSee('Registrar pago');

    $failure = MedicationFailureRecorder::recordFromRows($s['coordination'], [[
        'key' => 'medication:'.$s['medication']->id,
        'failure_reason' => OperationMedicationFailure::REASON_DOES_NOT_MATCH,
    ]])[0] ?? null;

    if ($failure !== null) {
        Livewire::test(App\Filament\Operations\Resources\OperationMedicationFailures\Pages\ViewOperationMedicationFailure::class, ['record' => $failure->getKey()])
            ->assertSuccessful()
            ->assertSee('PESTOFENO TABLETAS 400MG')
            ->assertSee('Marcar como adquirido');
    }
});

it('muestra en la selección si el medicamento está en el inventario y cuántas coincidencias hay', function (): void {
    $s = miiScenario(existence: 4);
    $key = 'medication:'.$s['medication']->id;

    $description = (string) CoordinationServiceItemsManager::manageServiceItemDescriptions($s['coordination'])[$key];

    expect($description)
        ->toContain('No cubierto')
        ->toContain('En inventario: 1 coincidencia · 1 con existencia (4 und.)');

    expect(MedicationInventoryCatalog::searchSummary('PESTOFENO TABLETAS 400MG'))->toBe(['matches' => 1, 'sellable' => 1, 'units' => 4, 'without_cost' => 0])
        ->and(strip_tags(MedicationInvoiceWizard::inventoryAvailabilityBadge('NOEXISTEXYZ - AMP')))->toBe('No está en el inventario');
});

it('avisa cuántas coincidencias no tienen costo o no tienen existencia', function (): void {
    $s = miiScenario(cost: 0, existence: 3);

    expect(strip_tags(MedicationInvoiceWizard::inventoryAvailabilityBadge((string) $s['medication']->medicine)))
        ->toBe('En inventario: 1 coincidencia · 1 con existencia (3 und.) · 1 sin costo');

    $s['inventory']->forceFill(['existence' => 0])->save();

    expect(strip_tags(MedicationInvoiceWizard::inventoryAvailabilityBadge('PESTOFENO 400MG')))
        ->toBe('En inventario: 1 coincidencia · sin existencia');
});

it('no muestra la existencia del inventario al usuario de un proveedor', function (): void {
    $s = miiScenario();

    $this->actingAs(User::factory()->make([
        'id' => 999_781,
        'name' => 'Proveedor Pest',
        'email' => 'proveedor.pest@example.com',
        'status' => 'ACTIVO',
        'departament' => ['OPERACIONES'],
        'supplier_id' => 1,
    ]));

    expect(MedicationInvoiceWizard::userCanSeeInventoryAvailability())->toBeFalse();

    foreach (CoordinationServiceItemsManager::manageServiceItemDescriptions($s['coordination']) as $description) {
        expect((string) $description)->not->toContain('En inventario');
    }
});

it('genera el PDF una sola vez por contenido y una versión nueva al cambiar el estado', function (): void {
    Illuminate\Support\Facades\Storage::fake(App\Services\MedicationInvoicePdfService::DISK);
    $s = miiScenario();
    $invoice = MedicationInvoiceIssuer::issue($s['coordination'], miiPlan($s, 1));

    $first = App\Services\MedicationInvoicePdfService::document($invoice->fresh());
    $again = App\Services\MedicationInvoicePdfService::document($invoice->fresh());

    expect($first['contents'])->toStartWith('%PDF')
        ->and($again['fingerprint'])->toBe($first['fingerprint'])
        ->and($first['filename'])->toBe('factura-'.mb_strtolower($invoice->invoice_number).'.pdf')
        ->and(App\Services\MedicationInvoicePdfService::html($invoice->fresh()))->toContain('PENDIENTE POR COBRAR')->toContain('no constituye factura fiscal');

    MedicationInvoiceLifecycle::registerPayment($invoice, ['paid_at' => now()->toDateString(), 'payment_method' => 'EFECTIVO_USD']);
    $paid = App\Services\MedicationInvoicePdfService::document($invoice->fresh());

    expect($paid['fingerprint'])->not->toBe($first['fingerprint'])
        ->and(App\Services\MedicationInvoicePdfService::html($invoice->fresh()))->toContain('COBRADA')
        ->and(Illuminate\Support\Facades\Storage::disk(App\Services\MedicationInvoicePdfService::DISK)->files(App\Services\MedicationInvoicePdfService::DIRECTORY))->toHaveCount(1);
});

it('sirve la vista previa del PDF a quien puede ver la factura', function (): void {
    Illuminate\Support\Facades\Storage::fake(App\Services\MedicationInvoicePdfService::DISK);
    $s = miiScenario();
    $invoice = MedicationInvoiceIssuer::issue($s['coordination'], miiPlan($s, 1));

    $this->get(route('operations.medication-invoices.pdf.preview', ['operationMedicationInvoice' => $invoice]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $this->get(route('operations.medication-invoices.pdf.download', ['operationMedicationInvoice' => $invoice]))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename='.App\Services\MedicationInvoicePdfService::filename($invoice));
});

it('envía el PDF por correo en cola y lo deja en la bitácora', function (): void {
    Illuminate\Support\Facades\Storage::fake(App\Services\MedicationInvoicePdfService::DISK);
    Illuminate\Support\Facades\Mail::fake();
    $s = miiScenario();
    $invoice = MedicationInvoiceIssuer::issue($s['coordination'], miiPlan($s, 1));

    (new App\Jobs\SendMedicationInvoicePdfJob(App\Jobs\SendMedicationInvoicePdfJob::CHANNEL_EMAIL, ['paciente@example.com'], 999_780, (int) $invoice->id))->handle();

    Illuminate\Support\Facades\Mail::assertSent(App\Mail\MedicationInvoicePdfMail::class, fn ($mail): bool => $mail->hasTo('paciente@example.com')
        && $mail->invoiceNumber === $invoice->invoice_number);

    expect($invoice->events()->where('event', App\Models\OperationMedicationInvoiceEvent::EVENT_SENT)->value('description'))
        ->toContain('correo a paciente@example.com');
});

it('no envía una factura anulada aunque el envío ya estuviera en cola', function (): void {
    Illuminate\Support\Facades\Storage::fake(App\Services\MedicationInvoicePdfService::DISK);
    Illuminate\Support\Facades\Mail::fake();
    $s = miiScenario();
    $invoice = MedicationInvoiceIssuer::issue($s['coordination'], miiPlan($s, 1));
    MedicationInvoiceLifecycle::annul($invoice, 'Anulada antes de que saliera el correo');

    (new App\Jobs\SendMedicationInvoicePdfJob(App\Jobs\SendMedicationInvoicePdfJob::CHANNEL_EMAIL, ['paciente@example.com'], 999_780, (int) $invoice->id))->handle();

    Illuminate\Support\Facades\Mail::assertNothingSent();
    expect(App\Filament\Operations\Resources\OperationMedicationInvoices\Actions\MedicationInvoiceActions::canSend($invoice->fresh()))->toBeFalse();
});

it('muestra ver PDF y enviar en la ficha de la factura', function (): void {
    $s = miiScenario();
    $invoice = MedicationInvoiceIssuer::issue($s['coordination'], miiPlan($s, 1));

    Livewire::test(ViewOperationMedicationInvoice::class, ['record' => $invoice->getKey()])
        ->assertSuccessful()
        ->assertSee('Acciones')
        ->assertSee('Ver PDF')
        ->assertSee('Enviar por correo')
        ->assertSee('Enviar por WhatsApp')
        ->assertSee('Pendiente por cobrar')
        ->assertActionExists('preview_medication_invoice_pdf');
});

/**
 * Dos facturas de un mismo escenario: una cobrada y una anulada, más una pendiente aparte.
 *
 * @return array{pending: OperationMedicationInvoice, paid: OperationMedicationInvoice, annulled: OperationMedicationInvoice}
 */
function miiThreeInvoices(): array
{
    $make = function (): OperationMedicationInvoice {
        $s = miiScenario();

        return MedicationInvoiceIssuer::issue($s['coordination'], miiPlan($s, 2));
    };

    $pending = $make();
    $paid = $make();
    $annulled = $make();

    MedicationInvoiceLifecycle::registerPayment($paid, ['paid_at' => now()->toDateString(), 'payment_method' => 'EFECTIVO_USD']);
    MedicationInvoiceLifecycle::annul($annulled, 'Anulada para probar los totales');

    return ['pending' => $pending->fresh(), 'paid' => $paid->fresh(), 'annulled' => $annulled->fresh()];
}

it('el reporte totaliza por estado y las anuladas no suman', function (): void {
    $invoices = miiThreeInvoices();
    $filters = App\Support\Operations\Reports\OperationReportFilters::fromArray(['invoice_ids' => array_map(fn ($i) => $i->id, $invoices)]);

    $summary = App\Support\Operations\Reports\MedicationInvoiceReportBuilder::summary($filters);

    expect($summary['count'])->toBe(3)
        ->and($summary['pending_count'])->toBe(1)
        ->and($summary['paid_count'])->toBe(1)
        ->and($summary['annulled_count'])->toBe(1)
        ->and($summary['pending_usd'])->toBe(26.0)
        ->and($summary['paid_usd'])->toBe(26.0)
        ->and($summary['annulled_usd'])->toBe(26.0)
        ->and($summary['total_usd'])->toBe(52.0)
        ->and($summary['units'])->toBe(4)
        ->and($summary['lines'])->toBe(2);

    $data = App\Support\Operations\Reports\MedicationInvoiceReportBuilder::build(App\Enums\OperationReportType::MedicationInvoices, $filters);
    $rows = iterator_to_array($data->rows, false);

    expect($rows)->toHaveCount(3)
        ->and($data->totals[0])->toBe('TOTAL (sin anuladas)')
        ->and($data->totals[7])->toBe('2 facturas')
        ->and($data->totals[10])->toBe(52.0)
        ->and(implode(' ', $data->criteria))->toContain('Facturas seleccionadas en la tabla: 3')->toContain('no suman')
        ->and(array_column($rows, 1))->toBe(['Medicamentos', 'Medicamentos', 'Medicamentos'])
        ->and(array_column($rows, 7))->toContain('Pendiente por cobrar', 'Cobrada', 'Anulada')
        ->and(array_column($rows, 8))->toBe([1, 1, 1])
        ->and(array_column($rows, 9))->toBe([2, 2, 2]);
});

it('por periodo filtra por emisión y estado; la selección manda sobre el periodo', function (): void {
    $invoices = miiThreeInvoices();
    $ids = array_map(fn ($i) => $i->id, $invoices);

    $paidOnly = App\Support\Operations\Reports\OperationReportFilters::fromArray(['period' => 'today', 'invoice_statuses' => [OperationMedicationInvoice::STATUS_PAID, 'INVENTADO']]);
    expect($paidOnly->invoiceStatuses)->toBe([OperationMedicationInvoice::STATUS_PAID])
        ->and(App\Support\Operations\Reports\MedicationInvoiceReportBuilder::query($paidOnly)->pluck('operation_medication_invoices.id')->all())->toContain($invoices['paid']->id)
        ->not->toContain($invoices['pending']->id);

    $lastMonth = App\Support\Operations\Reports\OperationReportFilters::fromArray(['period' => 'last_month']);
    expect(App\Support\Operations\Reports\MedicationInvoiceReportBuilder::query($lastMonth)->whereIn('operation_medication_invoices.id', $ids)->count())->toBe(0);

    $selected = App\Support\Operations\Reports\OperationReportFilters::fromArray(['period' => 'last_month', 'invoice_ids' => [$invoices['pending']->id]]);
    expect(App\Support\Operations\Reports\MedicationInvoiceReportBuilder::count($selected))->toBe(1);
});

it('genera el reporte en Excel, CSV y PDF al instante', function (string $format): void {
    Illuminate\Support\Facades\Storage::fake(App\Support\Operations\Reports\OperationReportGenerator::DISK);
    $invoices = miiThreeInvoices();
    $type = App\Enums\OperationReportType::MedicationInvoices;
    $reportFormat = App\Enums\OperationReportFormat::from($format);
    $filters = App\Support\Operations\Reports\OperationReportFilters::fromArray(['invoice_ids' => array_map(fn ($i) => $i->id, $invoices)]);
    $filename = App\Support\Operations\Reports\OperationReportGenerator::filename($type, $reportFormat);

    expect(App\Support\Operations\Reports\OperationReportGenerator::shouldQueue($type, $reportFormat, 3))->toBeFalse();

    $path = App\Support\Operations\Reports\OperationReportGenerator::store($type, $reportFormat, $filters, 999_780, $filename);
    $contents = (string) Illuminate\Support\Facades\Storage::disk(App\Support\Operations\Reports\OperationReportGenerator::DISK)->get($path);

    expect(strlen($contents))->toBeGreaterThan(100);

    if ($reportFormat === App\Enums\OperationReportFormat::Csv) {
        expect($contents)->toContain('TOTAL (sin anuladas)')->toContain($invoices['paid']->invoice_number);
    }

    if ($reportFormat === App\Enums\OperationReportFormat::Pdf) {
        expect($contents)->toStartWith('%PDF');
    }
})->with(['xlsx', 'csv', 'pdf']);

it('solo una selección muy grande va a cola', function (): void {
    $type = App\Enums\OperationReportType::MedicationInvoices;

    expect(App\Support\Operations\Reports\OperationReportGenerator::shouldQueue($type, App\Enums\OperationReportFormat::Pdf, 1000))->toBeFalse()
        ->and(App\Support\Operations\Reports\OperationReportGenerator::shouldQueue($type, App\Enums\OperationReportFormat::Pdf, 1001))->toBeTrue()
        ->and(App\Support\Operations\Reports\OperationReportGenerator::shouldQueue($type, App\Enums\OperationReportFormat::Excel, 3000))->toBeFalse()
        ->and(App\Support\Operations\Reports\OperationReportGenerator::shouldQueue($type, App\Enums\OperationReportFormat::Excel, 3001))->toBeTrue();
});

it('la acción masiva de la tabla descarga el reporte de las facturas seleccionadas', function (): void {
    Illuminate\Support\Facades\Storage::fake(App\Support\Operations\Reports\OperationReportGenerator::DISK);
    $invoices = miiThreeInvoices();

    Livewire::test(ListOperationMedicationInvoices::class)
        ->assertTableBulkActionExists('export_medication_invoices_report')
        ->callTableBulkAction('export_medication_invoices_report', array_values($invoices), ['format' => 'csv'])
        ->assertHasNoTableBulkActionErrors()
        ->assertFileDownloaded();
});

it('el reporte aparece en el Generador de reportes en su propia sección', function (): void {
    Livewire::test(App\Filament\Operations\Pages\GeneradorDeReportes::class)
        ->assertSuccessful()
        ->assertSee('Facturación')
        ->assertSee('Facturas a pacientes')
        ->set('data.type', App\Enums\OperationReportType::MedicationInvoices->value)
        ->assertSee('Por fecha de emisión de la factura.')
        ->assertSee('Estado de la factura');
});

it('sin el permiso no se ve ni se genera en ningún lado', function (): void {
    $this->actingAs(User::factory()->make([
        'id' => 999_782,
        'name' => 'Analista sin permiso',
        'email' => 'sin.permiso@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['OPERACIONES'],
    ]));

    expect(App\Support\Operations\Reports\MedicationInvoiceReportBuilder::allows())->toBeFalse()
        ->and(fn () => App\Support\Operations\Reports\OperationReportGenerator::store(
            App\Enums\OperationReportType::MedicationInvoices,
            App\Enums\OperationReportFormat::Csv,
            App\Support\Operations\Reports\OperationReportFilters::fromArray(['invoice_ids' => [1]]),
            999_782,
            'reporte-operaciones-medication-invoices-prueba.csv',
        ))->toThrow(Illuminate\Auth\Access\AuthorizationException::class);
});
