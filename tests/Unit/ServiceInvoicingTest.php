<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\OperationCoordinationServices\Pages\ManageCoordinationServiceItems;
use App\Models\OperationAccountsReceivable;
use App\Models\OperationCoordinationService;
use App\Models\OperationMedicationInvoice;
use App\Models\OperationServiceOrder;
use App\Models\Supplier;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineListLaboratory;
use App\Models\TelemedicinePatientLab;
use App\Models\User;
use App\Support\Operations\ClinicalServiceCatalog;
use App\Support\Operations\CoordinationServiceItemsManager;
use App\Support\Operations\MedicationInvoiceException;
use App\Support\Operations\MedicationInvoiceLifecycle;
use App\Support\Operations\MedicationInvoiceWizard;
use App\Support\Operations\OperationServiceOrderViewActions;
use App\Support\Operations\PatientInvoiceItemGuard;
use App\Support\Operations\Reports\MedicationInvoiceReportBuilder;
use App\Support\Operations\Reports\OperationReportFilters;
use App\Support\Operations\ServiceInvoiceIssuer;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Escribe dentro de una transacción que siempre se revierte. Correo, cola y HTTP simulados:
 * crear la orden de servicio no debe avisar a ningún proveedor real.
 */
beforeEach(function (): void {
    // El paso de facturación directa está retirado desde el 10/11/2026; estos tests lo cubren dormido.
    \App\Support\Operations\MedicationInvoiceWizard::$directInvoicingEnabled = true;
    if (! Schema::hasColumn('telemedicine_list_laboratories', 'cost') || ! Schema::hasColumn('operation_medication_invoices', 'invoice_type')) {
        $this->markTestSkipped('Faltan las columnas de precio del catálogo o el tipo de factura.');
    }

    DB::beginTransaction();
    Mail::fake();
    Queue::fake();
    // Tasa BCV fija; cualquier otra salida a internet se bloquea.
    Http::fake([
        've.dolarapi.com/*' => Http::response(['promedio' => 36.5]),
        '*' => Http::response([], 500),
    ]);

    Filament::setCurrentPanel('operations');

    $this->actingAs(User::factory()->make([
        'id' => 999_790,
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
 * Servicio médico con un laboratorio NO CUBIERTO pendiente y su fila del catálogo.
 *
 * @return array{coordination: OperationCoordinationService, lab: TelemedicinePatientLab, entry: TelemedicineListLaboratory, key: string}
 */
function sviScenario(?float $cost = 20.0, ?float $percentage = 25.0): array
{
    $case = TelemedicineCase::query()->whereNotNull('telemedicine_patient_id')->orderBy('id')->first();

    if (! $case instanceof TelemedicineCase) {
        test()->markTestSkipped('No hay casos de telemedicina para armar el escenario.');
    }

    $name = 'PERFIL PEST '.strtoupper(substr(md5(uniqid('', true)), 0, 8));

    $entry = TelemedicineListLaboratory::query()->create(['name' => $name, 'type' => 'NO CUBIERTO', 'cost' => $cost, 'profit_percentage' => $percentage]);

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

    $lab = TelemedicinePatientLab::query()->create([
        'telemedicine_patient_id' => $case->telemedicine_patient_id,
        'telemedicine_case_id' => $case->id,
        'laboratory' => $name,
        'type' => 'NO CUBIERTO',
        'status' => 'PENDIENTE',
        'operation_coordination_service_id' => $coordination->id,
    ]);

    CoordinationServiceItemsManager::flushClinicalItemsCache();

    return ['coordination' => $coordination, 'lab' => $lab, 'entry' => $entry, 'key' => 'lab:'.$lab->id];
}

/**
 * @return list<array{key: string, item_id: int, catalog_id: int, new_cost: float|null, new_percentage: float|null}>
 */
function sviPlan(array $s, ?float $newCost = null, ?float $newPercentage = null): array
{
    return [[
        'key' => $s['key'],
        'item_id' => (int) $s['lab']->id,
        'catalog_id' => (int) $s['entry']->id,
        'new_cost' => $newCost,
        'new_percentage' => $newPercentage,
    ]];
}

it('detecta el laboratorio no cubierto como facturable y propone su fila del catálogo por nombre', function (): void {
    $s = sviScenario();
    $rows = MedicationInvoiceWizard::buildRows($s['coordination'], [$s['key']], []);
    $row = array_values($rows)[0];

    expect(MedicationInvoiceWizard::kind($s['coordination'], [$s['key']]))->toBe(ClinicalServiceCatalog::KIND_LAB)
        ->and($row['kind'])->toBe(ClinicalServiceCatalog::KIND_LAB)
        ->and($row['catalog_id'])->toBe($s['entry']->id)
        ->and(MedicationInvoiceWizard::invoiceKeys($s['coordination'], [$s['key']], $rows))->toBe([$s['key']])
        ->and(MedicationInvoiceWizard::invoicedServiceKeys($s['coordination'], [$s['key']], $rows))->toBe([$s['key']])
        ->and(ClinicalServiceCatalog::price($s['entry']))->toBe(25.0);
});

it('emite la factura del laboratorio con su precio del catálogo, sin finalizar el ítem', function (): void {
    $s = sviScenario();

    $invoice = DB::transaction(fn () => ServiceInvoiceIssuer::issue($s['coordination'], ClinicalServiceCatalog::KIND_LAB, sviPlan($s)));
    $item = $invoice->items()->first();

    expect($invoice->invoice_number)->toStartWith('FAC-')
        ->and($invoice->invoice_type)->toBe(OperationMedicationInvoice::TYPE_LABORATORIES)
        ->and((float) $invoice->total_usd)->toBe(25.0)
        ->and($item->item_type)->toBe(OperationMedicationInvoice::TYPE_LABORATORIES)
        ->and($item->source_item_id)->toBe($s['lab']->id)
        ->and($item->catalog_item_id)->toBe($s['entry']->id)
        ->and((float) $item->unit_cost_usd)->toBe(20.0)
        ->and((float) $item->profit_percentage)->toBe(25.0)
        ->and($item->quantity)->toBe(1)
        ->and($s['lab']->fresh()->status)->toBe('PENDIENTE')
        ->and(OperationAccountsReceivable::query()->where('operation_medication_invoice_id', $invoice->id)->value('status'))->toBe(OperationAccountsReceivable::STATUS_PENDING_PAYMENT)
        ->and(PatientInvoiceItemGuard::activeInvoiceNumberFor($s['lab']))->toBe($invoice->invoice_number);
});

it('carga costo y % en el catálogo cuando faltan, sin pisar lo que ya existe', function (): void {
    $s = sviScenario(cost: null, percentage: null);

    expect(ServiceInvoiceIssuer::validationMessage($s['coordination'], ClinicalServiceCatalog::KIND_LAB, sviPlan($s)))->toContain('costo');

    $invoice = DB::transaction(fn () => ServiceInvoiceIssuer::issue($s['coordination'], ClinicalServiceCatalog::KIND_LAB, sviPlan($s, 40.0, 10.0)));

    expect((float) $s['entry']->fresh()->cost)->toBe(40.0)
        ->and((float) $s['entry']->fresh()->profit_percentage)->toBe(10.0)
        ->and((float) $invoice->total_usd)->toBe(44.0)
        ->and($invoice->events()->first()->description)->toContain('Precios cargados en el catálogo');

    $other = sviScenario(cost: 30.0, percentage: 0.0);
    $second = DB::transaction(fn () => ServiceInvoiceIssuer::issue($other['coordination'], ClinicalServiceCatalog::KIND_LAB, sviPlan($other, 99.0, 99.0)));

    expect((float) $other['entry']->fresh()->cost)->toBe(30.0)
        ->and((float) $second->total_usd)->toBe(30.0);
});

it('rechaza un ítem cubierto, uno ya facturado y uno sin fila del catálogo', function (): void {
    $s = sviScenario();
    $s['lab']->forceFill(['type' => 'CUBIERTO'])->save();
    CoordinationServiceItemsManager::flushClinicalItemsCache();

    expect(ServiceInvoiceIssuer::validationMessage($s['coordination'], ClinicalServiceCatalog::KIND_LAB, sviPlan($s)))->toContain('NO CUBIERTO');

    $t = sviScenario();
    DB::transaction(fn () => ServiceInvoiceIssuer::issue($t['coordination'], ClinicalServiceCatalog::KIND_LAB, sviPlan($t)));
    expect(ServiceInvoiceIssuer::validationMessage($t['coordination'], ClinicalServiceCatalog::KIND_LAB, sviPlan($t)))->toContain('factura vigente');

    $u = sviScenario();
    $plan = sviPlan($u);
    $plan[0]['catalog_id'] = 0;
    expect(ServiceInvoiceIssuer::validationMessage($u['coordination'], ClinicalServiceCatalog::KIND_LAB, $plan))->toContain('catálogo');
});

it('desde Gestionar servicio factura el laboratorio y crea su orden al proveedor, con el ítem EN GESTION', function (): void {
    $supplier = Supplier::query()->orderBy('id')->first();

    if (! $supplier instanceof Supplier) {
        $this->markTestSkipped('No hay proveedores jurídicos para la orden.');
    }

    $s = sviScenario();
    $rows = MedicationInvoiceWizard::buildRows($s['coordination'], [$s['key']], []);

    $orderId = CoordinationServiceItemsManager::save($s['coordination'], [
        'managed_service_item_keys' => [$s['key']],
        MedicationInvoiceWizard::ROWS_FIELD => $rows,
        'supplier_id' => $supplier->id,
        'order_number' => CoordinationServiceItemsManager::nextServiceOrderNumber(),
        'appointment_at' => now()->addDay()->format('Y-m-d H:i:s'),
        'service_order_bcv_rate' => 36.5,
        'service_order_price_usd' => 18,
        'service_order_price_ves' => 657,
    ]);

    $invoice = OperationMedicationInvoice::query()->where('operation_coordination_service_id', $s['coordination']->id)->first();

    expect($orderId)->toBeInt()->toBeGreaterThan(0)
        ->and($invoice)->not->toBeNull()
        ->and($invoice->operation_service_order_id)->toBe($orderId)
        ->and($s['lab']->fresh()->status)->toBe('EN GESTION')
        ->and(OperationServiceOrder::query()->find($orderId)?->service_type)->toBe('LABORATORIOS');

    // La orden no se puede cancelar, anular ni reasignar con la factura vigente.
    expect(OperationServiceOrderViewActions::closingBlockReason(OperationServiceOrder::query()->find($orderId)))->toContain($invoice->invoice_number);

    // Anular la factura la libera: la orden sigue activa y ya se puede cerrar.
    MedicationInvoiceLifecycle::annul($invoice, 'El paciente no hará el examen');

    expect($invoice->fresh()->status)->toBe(OperationMedicationInvoice::STATUS_ANNULLED)
        ->and($s['lab']->fresh()->status)->toBe('EN GESTION')
        ->and(OperationServiceOrderViewActions::closingBlockReason(OperationServiceOrder::query()->find($orderId)))->toBeNull();
});

it('no anula la factura si la orden ya está finalizada (el servicio se realizó)', function (): void {
    $s = sviScenario();
    $invoice = DB::transaction(fn () => ServiceInvoiceIssuer::issue($s['coordination'], ClinicalServiceCatalog::KIND_LAB, sviPlan($s)));

    $order = OperationServiceOrder::query()->create([
        'operation_coordination_service_id' => $s['coordination']->id,
        'service_type' => 'LABORATORIOS',
        'status' => 'FINALIZADO',
        'order_number' => 'OS-PEST-'.uniqid(),
        'created_by' => 'PEST',
    ]);
    $invoice->forceFill(['operation_service_order_id' => $order->id])->save();

    expect(fn () => MedicationInvoiceLifecycle::annul($invoice->fresh(), 'Intento sobre una orden finalizada'))
        ->toThrow(MedicationInvoiceException::class, 'FINALIZADA');
});

it('el reporte trae el tipo de cada factura y filtra por tipo', function (): void {
    $s = sviScenario();
    $invoice = DB::transaction(fn () => ServiceInvoiceIssuer::issue($s['coordination'], ClinicalServiceCatalog::KIND_LAB, sviPlan($s)));

    $filters = OperationReportFilters::fromArray(['period' => 'today', 'invoice_types' => [OperationMedicationInvoice::TYPE_LABORATORIES, 'INVENTADO']]);
    $data = MedicationInvoiceReportBuilder::build(App\Enums\OperationReportType::MedicationInvoices, $filters);
    $rows = collect(iterator_to_array($data->rows, false))->keyBy(0);

    expect($filters->invoiceTypes)->toBe([OperationMedicationInvoice::TYPE_LABORATORIES])
        ->and($rows->get($invoice->invoice_number)[1] ?? null)->toBe('Laboratorios')
        ->and(MedicationInvoiceReportBuilder::query(OperationReportFilters::fromArray(['period' => 'today', 'invoice_types' => [OperationMedicationInvoice::TYPE_MEDICATIONS]]))->whereKey($invoice->id)->exists())->toBeFalse();
});

it('el PDF dice el tipo y no habla de inventario', function (): void {
    $s = sviScenario();
    $invoice = DB::transaction(fn () => ServiceInvoiceIssuer::issue($s['coordination'], ClinicalServiceCatalog::KIND_LAB, sviPlan($s)));
    $html = App\Services\MedicationInvoicePdfService::html($invoice->fresh());

    expect($html)->toContain('Laboratorios')->toContain('Concepto')->not->toContain('inventario Diagnomóvil');
});

it('el catálogo muestra costo, % y precio al paciente, y el asistente propone su precio', function (): void {
    $s = sviScenario();

    expect(strip_tags(MedicationInvoiceWizard::catalogPriceBadge(ClinicalServiceCatalog::KIND_LAB, (string) $s['entry']->name)))->toBe('Precio al paciente: US$ 25.00')
        ->and(strip_tags(MedicationInvoiceWizard::catalogPriceBadge(ClinicalServiceCatalog::KIND_LAB, 'NO EXISTE PEST')))->toBe('No está en el catálogo');

    Livewire::test(App\Filament\Operations\Resources\TelemedicineListLaboratories\Pages\ListTelemedicineListLaboratories::class)
        ->assertSuccessful()
        ->assertSee('Precio al paciente');

    $page = Livewire::test(ManageCoordinationServiceItems::class, ['record' => $s['coordination']->getKey()])
        ->set('data.managed_service_item_keys', [$s['key']])
        ->assertSee('Facturar al paciente: laboratorios')
        ->assertSee('Precio al paciente US$ 25.00');

    expect(array_values($page->get('data.'.MedicationInvoiceWizard::ROWS_FIELD))[0]['catalog_id'])->toBe($s['entry']->id);
});

it('estudios y especialistas no cubiertos también se facturan con su catálogo', function (string $kind, string $catalogClass, string $itemClass, string $nameColumn, string $invoiceType): void {
    $case = TelemedicineCase::query()->whereNotNull('telemedicine_patient_id')->orderBy('id')->first();
    $name = 'PEST '.strtoupper(substr(md5(uniqid('', true)), 0, 8));
    $entry = $catalogClass::query()->create(['name' => $name, 'type' => 'NO CUBIERTO', 'cost' => 50, 'profit_percentage' => 20]);

    $coordination = OperationCoordinationService::query()->create([
        'telemedicine_case_id' => $case->id,
        'telemedicine_patient_id' => $case->telemedicine_patient_id,
        'patient' => 'PACIENTE PEST', 'ci_patient' => 'V-0', 'contractor' => 'PEST', 'relationship_patient' => 'TITULAR',
        'symptoms_diagnosis' => 'PRUEBA', 'status' => 'PENDIENTE', 'managed_by' => 'TDG', 'created_by' => 'PEST',
    ]);

    $item = $itemClass::query()->create([
        'telemedicine_patient_id' => $case->telemedicine_patient_id,
        'telemedicine_case_id' => $case->id,
        $nameColumn => $name,
        'type' => 'NO CUBIERTO',
        'assigned_by' => 'PEST',
        'status' => 'PENDIENTE',
        'operation_coordination_service_id' => $coordination->id,
    ]);
    CoordinationServiceItemsManager::flushClinicalItemsCache();

    $key = $kind.':'.$item->id;
    $rows = MedicationInvoiceWizard::buildRows($coordination, [$key], []);

    expect(MedicationInvoiceWizard::kind($coordination, [$key]))->toBe($kind)
        ->and(array_values($rows)[0]['catalog_id'])->toBe($entry->id);

    $invoice = DB::transaction(fn () => ServiceInvoiceIssuer::issue($coordination, $kind, ServiceInvoiceIssuer::planFromRows($rows, [$key])));

    expect($invoice->invoice_type)->toBe($invoiceType)
        ->and((float) $invoice->total_usd)->toBe(60.0)
        ->and(PatientInvoiceItemGuard::activeInvoiceNumberFor($item))->toBe($invoice->invoice_number);
})->with([
    'estudio' => [ClinicalServiceCatalog::KIND_STUDY, App\Models\TelemedicineListStudy::class, App\Models\TelemedicinePatientStudy::class, 'study', OperationMedicationInvoice::TYPE_IMAGING],
    'especialista' => [ClinicalServiceCatalog::KIND_SPECIALTY, App\Models\TelemedicineListSpecialist::class, App\Models\TelemedicinePatientSpecialty::class, 'specialty', OperationMedicationInvoice::TYPE_SPECIALIST],
]);

it('sin proveedor en la orden no escribe nada y avisa una sola vez', function (): void {
    $s = sviScenario();
    $rows = MedicationInvoiceWizard::buildRows($s['coordination'], [$s['key']], []);
    $ordersBefore = OperationServiceOrder::query()->where('operation_coordination_service_id', $s['coordination']->id)->count();

    $result = CoordinationServiceItemsManager::save($s['coordination'], [
        'managed_service_item_keys' => [$s['key']],
        MedicationInvoiceWizard::ROWS_FIELD => $rows,
        'order_number' => CoordinationServiceItemsManager::nextServiceOrderNumber(),
    ]);

    $notifications = collect(session()->get('filament.notifications', []))->pluck('title')->all();

    expect($result)->toBeNull()
        ->and(OperationMedicationInvoice::query()->where('operation_coordination_service_id', $s['coordination']->id)->exists())->toBeFalse()
        ->and(OperationServiceOrder::query()->where('operation_coordination_service_id', $s['coordination']->id)->count())->toBe($ordersBefore)
        ->and($s['lab']->fresh()->status)->toBe('PENDIENTE')
        ->and($notifications)->toBe(['Revise la orden de servicio']);
});

it('el paso Orden de servicio pide el proveedor cuando se factura un servicio', function (): void {
    $s = sviScenario();

    expect(CoordinationServiceItemsManager::invoicedOrderProviderMessage([]))->toContain('Elija el proveedor')
        ->and(CoordinationServiceItemsManager::invoicedOrderProviderMessage(['supplier_id' => 5]))->toBeNull()
        ->and(CoordinationServiceItemsManager::invoicedOrderProviderMessage(['supplier_id' => 5, 'doctor_nurse_id' => 3]))->toContain('un solo proveedor')
        ->and(CoordinationServiceItemsManager::invoicedOrderProviderMessage(['register_unregistered_provider' => true, 'unregistered_provider_type' => 'juridico']))->toContain('Generar cotización');

    Livewire::test(ManageCoordinationServiceItems::class, ['record' => $s['coordination']->getKey()])
        ->set('data.managed_service_item_keys', [$s['key']])
        ->assertSee('Para ítems cubiertos y los no cubiertos que se facturan al paciente')
        ->assertSee('Proveedor jurídico')
        ->assertSee('Orden para lo que se realiza');
});
