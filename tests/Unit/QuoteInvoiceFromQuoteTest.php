<?php

declare(strict_types=1);

use App\Models\AffiliationCorporate;
use App\Models\OperationAccountsReceivable;
use App\Models\OperationCoordinationService;
use App\Models\OperationInventoryProductCategory;
use App\Models\OperationMedicationInvoice;
use App\Models\OperationQuoteGenerator;
use App\Models\Supplier;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineListLaboratory;
use App\Models\TelemedicinePatient;
use App\Models\TelemedicinePatientLab;
use App\Models\User;
use App\Services\MedicationInvoicePdfService;
use App\Support\Operations\CoordinationServiceItemsManager;
use App\Support\Operations\CoordinationServiceQuoteManager;
use App\Support\Operations\MedicationInventoryCatalog;
use App\Support\Operations\MedicationInvoiceException;
use App\Support\Operations\MedicationInvoiceLifecycle;
use App\Support\Operations\MedicationInvoiceWizard;
use App\Support\Operations\OperationQuoteGeneratorPublicAmounts;
use App\Support\Operations\QuoteInvoiceIssuer;
use App\Support\Operations\QuoteInvoicePayer;
use App\Support\Operations\QuoteLinePricing;
use App\Support\Operations\QuoteSummaryPanel;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Cotización de lo NO CUBIERTO con % de utilidad por ítem y factura al aprobarla (10/11/2026).
 * Todo corre en una transacción que siempre se revierte; correo, cola y HTTP simulados.
 */
beforeEach(function (): void {
    if (! Schema::hasColumn('operation_quote_generators', 'generates_invoice')) {
        $this->markTestSkipped('Falta la migración 2026_11_10_090000.');
    }

    DB::beginTransaction();
    Mail::fake();
    Queue::fake();
    Http::fake([
        've.dolarapi.com/*' => Http::response(['promedio' => 40.0]),
        '*' => Http::response([], 500),
    ]);

    Filament::setCurrentPanel('operations');

    $this->actingAs(User::factory()->make([
        'id' => 999_791,
        'name' => 'Analista Cotización Pest',
        'email' => 'analista.cotizacion.pest@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'OPERACIONES'],
    ]));

    MedicationInvoiceWizard::$directInvoicingEnabled = false;
    QuoteInvoicePayer::flush();
    CoordinationServiceItemsManager::flushClinicalItemsCache();
});

afterEach(function (): void {
    DB::rollBack();
    QuoteInvoicePayer::flush();
});

/**
 * Servicio con dos laboratorios NO CUBIERTOS del paciente indicado.
 *
 * @return array{coordination: OperationCoordinationService, labs: list<TelemedicinePatientLab>, patient: TelemedicinePatient}
 */
function qifScenario(?TelemedicinePatient $patient = null): array
{
    $case = TelemedicineCase::query()->whereNotNull('telemedicine_patient_id')->latest('id')->first();

    if (! $case instanceof TelemedicineCase) {
        test()->markTestSkipped('No hay casos de telemedicina para armar el escenario.');
    }

    $patient ??= TelemedicinePatient::query()->findOrFail($case->telemedicine_patient_id);

    $coordination = OperationCoordinationService::query()->forceCreate([
        'telemedicine_case_id' => $case->id,
        'telemedicine_patient_id' => $patient->id,
        'patient' => 'PACIENTE PEST',
        'ci_patient' => 'V-0000000',
        'contractor' => 'PEST',
        'relationship_patient' => 'TITULAR',
        'symptoms_diagnosis' => 'PRUEBA',
        'status' => 'PENDIENTE',
        'managed_by' => 'TDG',
        'created_by' => 'PEST',
    ]);

    $labs = [];

    foreach (['A', 'B'] as $suffix) {
        $labs[] = TelemedicinePatientLab::query()->forceCreate([
            'telemedicine_patient_id' => $patient->id,
            'telemedicine_case_id' => $case->id,
            'laboratory' => 'PERFIL COTIZACION PEST '.$suffix.' '.strtoupper(substr(md5(uniqid('', true)), 0, 6)),
            'type' => 'NO CUBIERTO',
            'status' => 'PENDIENTE',
            'operation_coordination_service_id' => $coordination->id,
        ]);
    }

    CoordinationServiceItemsManager::flushClinicalItemsCache();

    return ['coordination' => $coordination, 'labs' => $labs, 'patient' => $patient];
}

function qifPatient(string $type): ?TelemedicinePatient
{
    return TelemedicinePatient::query()
        ->where('type_affiliation', $type)
        ->when($type === 'CORPORATIVO', fn ($query) => $query->whereNotNull('afilliation_corporate_id'))
        ->latest('id')
        ->first();
}

/**
 * @param  array<string, mixed>  $overrides
 */
function qifQuote(OperationCoordinationService $coordination, array $labs, array $overrides = []): OperationQuoteGenerator
{
    $items = CoordinationServiceItemsManager::withSalePrices([
        ['key' => 'lab:'.$labs[0]->id, 'category' => 'LABORATORIOS', 'label' => $labs[0]->laboratory, 'unit_price_usd' => 10.0, 'profit_percentage' => 30.0],
        ['key' => 'lab:'.$labs[1]->id, 'category' => 'LABORATORIOS', 'label' => $labs[1]->laboratory, 'unit_price_usd' => 20.0, 'profit_percentage' => null],
    ], 40.0);

    return OperationQuoteGenerator::query()->forceCreate(array_merge([
        'telemedicine_case_id' => $coordination->telemedicine_case_id,
        'telemedicine_patient_id' => $coordination->telemedicine_patient_id,
        'operation_coordination_service_id' => $coordination->id,
        'type_service' => 'LABORATORIOS',
        'supplier_id' => Supplier::query()->orderBy('id')->value('id'),
        'status' => OperationQuoteGenerator::STATUS_PENDING,
        'items' => $items,
        'costo_dolares' => 30,
        'costo_bolivares' => 1320,
        'porcentaje_ganancia' => 10,
        'subtotal' => 30,
        'total' => 33,
        'generates_invoice' => true,
        'invoice_payer_type' => OperationMedicationInvoice::PAYER_PATIENT,
        'invoice_payer_name' => 'PACIENTE PEST',
        'invoice_payer_identification' => 'V-12345678',
        'invoice_payer_address' => 'Caracas, El Rosal',
        'created_by' => 'PEST',
    ], $overrides));
}

it('calcula el precio de venta por ítem: un % vacío es sin utilidad y el % efectivo sale de los montos', function (): void {
    $totals = QuoteLinePricing::totals([
        ['key' => 'lab:1', 'label' => 'A', 'unit_price_usd' => '10', 'profit_percentage' => '30'],
        ['key' => 'lab:2', 'label' => 'B', 'unit_price_usd' => 20, 'profit_percentage' => ''],
        ['key' => 'lab:3', 'label' => 'C', 'unit_price_usd' => 3.33, 'profit_percentage' => 33.33],
    ]);

    // 13 + 20 + 4,44 (3,33 × 1,3333 = 4,4399 → 4,44)
    expect($totals['cost'])->toBe(33.33)
        ->and($totals['total'])->toBe(37.44)
        ->and($totals['profit'])->toBe(4.11)
        ->and($totals['effective_percentage'])->toBe(12.33)
        ->and($totals['lines'][1]['percentage'])->toBeNull()
        ->and($totals['lines'][1]['price'])->toBe(20.0)
        ->and($totals['priced'])->toBe(3);

    expect(QuoteLinePricing::totals([['key' => 'lab:1', 'unit_price_usd' => null]])['total'])->toBeNull()
        ->and(QuoteLinePricing::validationMessage([['label' => 'X', 'profit_percentage' => -1]]))->toContain('entre 0 y')
        ->and(QuoteLinePricing::validationMessage([['label' => 'X', 'profit_percentage' => 5000]]))->toContain('entre 0 y')
        ->and(QuoteLinePricing::validationMessage([['label' => 'X', 'profit_percentage' => null]]))->toBeNull();
});

it('precarga el % del catálogo del laboratorio y el de la categoría de medicamentos, y deja vacío si no existe', function (): void {
    $name = 'CATALOGO PCT PEST '.strtoupper(substr(md5(uniqid('', true)), 0, 6));
    TelemedicineListLaboratory::query()->create(['name' => $name, 'type' => 'NO CUBIERTO', 'cost' => 12, 'profit_percentage' => 35]);

    expect(QuoteLinePricing::defaultPercentage('lab:1', $name))->toBe(35.0)
        ->and(QuoteLinePricing::defaultPercentage('lab:1', 'NO EXISTE '.$name))->toBeNull()
        ->and(QuoteLinePricing::defaultPercentage('otro:1', $name))->toBeNull();

    $categoryId = MedicationInventoryCatalog::medicationCategoryId();

    if ($categoryId !== null) {
        OperationInventoryProductCategory::query()->whereKey($categoryId)->update(['profit_percentage' => 22.5]);

        // `once()` memoriza por request; el valor se lee de nuevo en un proceso nuevo.
        expect(QuoteLinePricing::percentage(OperationInventoryProductCategory::query()->whereKey($categoryId)->value('profit_percentage')))->toBe(22.5);
    }
});

it('el resumen muestra cada ítem con su ganancia y destaca costo, ganancia total y total', function (): void {
    $html = QuoteSummaryPanel::render([
        ['key' => 'lab:1', 'label' => 'HEMATOLOGIA', 'unit_price_usd' => 10, 'profit_percentage' => 30],
        ['key' => 'lab:2', 'label' => 'GLICEMIA', 'unit_price_usd' => 20, 'profit_percentage' => null],
    ], 40.0)->toHtml();

    expect($html)
        ->toContain('HEMATOLOGIA')
        ->toContain('Sin utilidad')
        ->toContain('US$ 13,00')
        ->toContain('Costo total')
        ->toContain('US$ 30,00')
        ->toContain('Ganancia total')
        ->toContain('US$ 3,00')
        ->toContain('10,00 % sobre el costo')
        ->toContain('Total cotización')
        ->toContain('US$ 33,00')
        ->toContain('Bs. 1.320,00');
});

it('el PDF de la cotización muestra el precio de venta de cada ítem según su propio %', function (): void {
    $items = OperationQuoteGeneratorPublicAmounts::itemsWithProfit(CoordinationServiceItemsManager::withSalePrices([
        ['key' => 'lab:1', 'unit_price_usd' => 10, 'profit_percentage' => 30],
        ['key' => 'lab:2', 'unit_price_usd' => 20, 'profit_percentage' => null],
    ], 40.0), 99);

    expect($items[0]['unit_price_usd'])->toBe(13.0)
        ->and($items[1]['unit_price_usd'])->toBe(20.0);

    // Las cotizaciones anteriores conservan el % global.
    expect(OperationQuoteGeneratorPublicAmounts::itemsWithProfit([['unit_price_usd' => 10]], 50)[0]['unit_price_usd'])->toBe(15.0);
});

it('el afiliado individual no tiene grupo corporativo: la opción se deshabilita con el motivo', function (): void {
    $patient = qifPatient('INDIVIDUAL');

    if (! $patient instanceof TelemedicinePatient) {
        $this->markTestSkipped('No hay pacientes individuales.');
    }

    ['coordination' => $coordination] = qifScenario($patient);
    $options = QuoteInvoicePayer::options($coordination);

    expect($options['corporate'])->toBeNull()
        ->and($options['corporate_unavailable_reason'])->toContain('individual')
        ->and($options['patient']['identification'])->toBe(trim((string) $patient->nro_identificacion));

    expect(fn () => QuoteInvoicePayer::snapshot($coordination, OperationMedicationInvoice::PAYER_CORPORATE, null))
        ->toThrow(MedicationInvoiceException::class, 'individual');

    expect(fn () => QuoteInvoicePayer::snapshot($coordination, null, null))
        ->toThrow(MedicationInvoiceException::class, 'responsable de pago');
});

it('el paciente corporativo paga con la razón social, RIF y dirección de su afiliación corporativa', function (): void {
    $patient = qifPatient('CORPORATIVO');

    if (! $patient instanceof TelemedicinePatient) {
        $this->markTestSkipped('No hay pacientes corporativos.');
    }

    $affiliation = AffiliationCorporate::query()->withTrashed()->find($patient->afilliation_corporate_id);

    if (! $affiliation instanceof AffiliationCorporate || blank($affiliation->name_corporate)) {
        $this->markTestSkipped('El paciente corporativo no tiene afiliación con razón social.');
    }

    DB::table('affiliation_corporates')->where('id', $affiliation->id)->update([
        'document_type_rif' => 'J',
        'rif' => '409063704',
        'address' => 'Av. Principal de Las Mercedes',
    ]);

    ['coordination' => $coordination] = qifScenario($patient);
    $snapshot = QuoteInvoicePayer::snapshot($coordination, OperationMedicationInvoice::PAYER_CORPORATE, 'ignorada');

    expect($snapshot)->toBe([
        'invoice_payer_type' => OperationMedicationInvoice::PAYER_CORPORATE,
        'invoice_payer_name' => trim((string) $affiliation->name_corporate),
        'invoice_payer_identification' => 'J-409063704',
        'invoice_payer_address' => 'Av. Principal de Las Mercedes',
    ]);
});

it('si la ficha del paciente no tiene dirección, exige la que escribe el analista', function (): void {
    ['coordination' => $coordination, 'patient' => $patient] = qifScenario();
    DB::table('telemedicine_patients')->where('id', $patient->id)->update(['address' => null]);
    DB::table('telemedicine_patients')->where('id', $patient->id)->update(['afilliation_id' => null, 'afilliation_corporate_id' => null]);
    QuoteInvoicePayer::flush();

    $fresh = $coordination->fresh();

    if (QuoteInvoicePayer::options($fresh)['patient']['address'] !== null) {
        $this->markTestSkipped('El paciente hereda dirección de un afiliado vinculado por cédula.');
    }

    expect(fn () => QuoteInvoicePayer::snapshot($fresh, OperationMedicationInvoice::PAYER_PATIENT, '  '))
        ->toThrow(MedicationInvoiceException::class, 'dirección');

    expect(QuoteInvoicePayer::snapshot($fresh, OperationMedicationInvoice::PAYER_PATIENT, ' Calle  10, Maracay ')['invoice_payer_address'])
        ->toBe('Calle 10, Maracay');
});

it('desde Gestionar servicio crea la cotización con % por ítem y el responsable de pago, sin factura todavía', function (): void {
    $supplier = Supplier::query()->orderBy('id')->first();

    if (! $supplier instanceof Supplier) {
        $this->markTestSkipped('No hay proveedores.');
    }

    ['coordination' => $coordination, 'labs' => $labs] = qifScenario();
    $keys = ['lab:'.$labs[0]->id, 'lab:'.$labs[1]->id];

    $result = CoordinationServiceItemsManager::save($coordination, [
        'managed_service_item_keys' => $keys,
        'manage_quote_supplier_id' => $supplier->id,
        'manage_quote_bcv_rate' => 40.0,
        'manage_quote_line_items' => [
            ['key' => $keys[0], 'label' => $labs[0]->laboratory, 'unit_price_usd' => 10, 'profit_percentage' => 30],
            ['key' => $keys[1], 'label' => $labs[1]->laboratory, 'unit_price_usd' => 20, 'profit_percentage' => null],
        ],
        'manage_quote_generates_invoice' => true,
        'manage_quote_invoice_payer_type' => OperationMedicationInvoice::PAYER_PATIENT,
        'manage_quote_invoice_payer_address' => 'Caracas, El Rosal',
    ]);

    $quote = OperationQuoteGenerator::query()->where('operation_coordination_service_id', $coordination->id)->latest('id')->first();

    expect($result)->not->toBeNull()
        ->and($quote)->not->toBeNull()
        ->and((float) $quote->costo_dolares)->toBe(30.0)
        ->and((float) $quote->total)->toBe(33.0)
        ->and((float) $quote->porcentaje_ganancia)->toBe(10.0)
        ->and((float) $quote->costo_bolivares)->toBe(1320.0)
        ->and($quote->generates_invoice)->toBeTrue()
        ->and($quote->invoice_payer_type)->toBe(OperationMedicationInvoice::PAYER_PATIENT)
        ->and($quote->invoice_payer_address)->not->toBeEmpty()
        ->and((float) $quote->items[0]['profit_percentage'])->toBe(30.0)
        ->and($quote->items[1]['profit_percentage'])->toBeNull()
        ->and((float) $quote->items[0]['sale_price_usd'])->toBe(13.0)
        ->and(OperationMedicationInvoice::query()->where('operation_quote_generator_id', $quote->id)->exists())->toBeFalse();
});

it('responder NO deja la cotización como siempre, sin factura ni responsable', function (): void {
    $supplier = Supplier::query()->orderBy('id')->first();

    if (! $supplier instanceof Supplier) {
        $this->markTestSkipped('No hay proveedores.');
    }

    ['coordination' => $coordination, 'labs' => $labs] = qifScenario();
    $keys = ['lab:'.$labs[0]->id, 'lab:'.$labs[1]->id];

    CoordinationServiceItemsManager::save($coordination, [
        'managed_service_item_keys' => $keys,
        'manage_quote_supplier_id' => $supplier->id,
        'manage_quote_bcv_rate' => 40.0,
        'manage_quote_line_items' => [
            ['key' => $keys[0], 'label' => $labs[0]->laboratory, 'unit_price_usd' => 10, 'profit_percentage' => 30],
            ['key' => $keys[1], 'label' => $labs[1]->laboratory, 'unit_price_usd' => 20, 'profit_percentage' => 10],
        ],
        'manage_quote_generates_invoice' => false,
        'manage_quote_invoice_payer_type' => OperationMedicationInvoice::PAYER_PATIENT,
    ]);

    $quote = OperationQuoteGenerator::query()->where('operation_coordination_service_id', $coordination->id)->latest('id')->firstOrFail();

    expect($quote->generates_invoice)->toBeFalse()
        ->and($quote->invoice_payer_type)->toBeNull()
        ->and((float) $quote->total)->toBe(35.0);
});

it('al aprobar la cotización emite la factura con el responsable de pago, la cuenta por cobrar y la orden', function (): void {
    ['coordination' => $coordination, 'labs' => $labs] = qifScenario();
    $quote = qifQuote($coordination, $labs);

    $orderId = CoordinationServiceQuoteManager::save($coordination->fresh(), [
        'quote_statuses' => [[
            'quote_id' => $quote->id,
            'status' => OperationQuoteGenerator::STATUS_PENDING,
            'is_cash' => false,
            'has_service_order' => false,
            'status_locked' => false,
        ]],
        'selected_pending_quote_ids' => [$quote->id],
        'order_number' => CoordinationServiceQuoteManager::nextServiceOrderNumber(),
        'telemedicine_priority_id' => $coordination->telemedicine_priority_id,
    ]);

    $quote->refresh();
    $invoice = OperationMedicationInvoice::query()->where('operation_quote_generator_id', $quote->id)->with('items')->first();

    expect($orderId)->toBeGreaterThan(0)
        ->and($quote->status)->toBe(OperationQuoteGenerator::STATUS_APPROVED)
        ->and($invoice)->not->toBeNull()
        ->and($quote->operation_medication_invoice_id)->toBe($invoice->id)
        ->and($invoice->operation_service_order_id)->toBe($orderId)
        ->and($invoice->invoice_type)->toBe(OperationMedicationInvoice::TYPE_LABORATORIES)
        ->and($invoice->payer_type)->toBe(OperationMedicationInvoice::PAYER_PATIENT)
        ->and($invoice->payer_name)->toBe('PACIENTE PEST')
        ->and($invoice->payer_identification)->toBe('V-12345678')
        ->and($invoice->payer_address)->toBe('Caracas, El Rosal')
        ->and((float) $invoice->total_usd)->toBe(33.0)
        ->and((float) $invoice->bcv_rate)->toBe(40.0)
        ->and((float) $invoice->total_ves)->toBe(1320.0)
        ->and($invoice->items)->toHaveCount(2)
        ->and((float) $invoice->items[0]->unit_cost_usd)->toBe(10.0)
        ->and((float) $invoice->items[0]->profit_percentage)->toBe(30.0)
        ->and((float) $invoice->items[0]->unit_price_usd)->toBe(13.0)
        ->and((float) $invoice->items[1]->unit_price_usd)->toBe(20.0);

    $receivable = OperationAccountsReceivable::query()->where('operation_medication_invoice_id', $invoice->id)->first();

    expect($receivable)->not->toBeNull()
        ->and($receivable->status)->toBe(OperationAccountsReceivable::STATUS_PENDING_PAYMENT)
        ->and((float) $receivable->quote_amount_usd)->toBe(33.0);

    // El PDF de la factura lleva la sección del responsable de pago.
    expect(MedicationInvoicePdfService::html($invoice))
        ->toContain('Responsable de pago')
        ->toContain('Cédula de identidad')
        ->toContain('Caracas, El Rosal')
        ->toContain('según la cotización aprobada');

    // Emitir de nuevo no duplica.
    expect(DB::transaction(fn () => QuoteInvoiceIssuer::issueForApprovedQuote($coordination, $quote->fresh()))->id)->toBe($invoice->id)
        ->and(OperationMedicationInvoice::query()->where('operation_quote_generator_id', $quote->id)->count())->toBe(1);

    // Anularla no toca inventario: va por la anulación de servicios.
    MedicationInvoiceLifecycle::annul($invoice, 'El paciente no hará el examen.');

    expect($invoice->fresh()->status)->toBe(OperationMedicationInvoice::STATUS_ANNULLED)
        ->and($receivable->fresh()->status)->toBe(OperationAccountsReceivable::STATUS_ANNULLED);
});

it('sin responsable de pago no aprueba: no queda orden ni factura y la cotización sigue pendiente', function (): void {
    ['coordination' => $coordination, 'labs' => $labs] = qifScenario();
    $quote = qifQuote($coordination, $labs, ['invoice_payer_address' => null]);

    $result = CoordinationServiceQuoteManager::save($coordination->fresh(), [
        'quote_statuses' => [[
            'quote_id' => $quote->id,
            'status' => OperationQuoteGenerator::STATUS_PENDING,
            'is_cash' => false,
            'has_service_order' => false,
            'status_locked' => false,
        ]],
        'selected_pending_quote_ids' => [$quote->id],
        'order_number' => CoordinationServiceQuoteManager::nextServiceOrderNumber(),
    ]);

    expect($result)->toBeNull()
        ->and($quote->refresh()->status)->toBe(OperationQuoteGenerator::STATUS_PENDING)
        ->and($quote->operation_service_order_id)->toBeNull()
        ->and(OperationMedicationInvoice::query()->where('operation_quote_generator_id', $quote->id)->exists())->toBeFalse();
});

it('no factura la cortesía, ni las líneas de cortesía, ni una cotización que dijo que no', function (): void {
    ['coordination' => $coordination, 'labs' => $labs] = qifScenario();

    $courtesy = qifQuote($coordination, $labs, ['is_courtesy' => true]);
    $without = qifQuote($coordination, $labs, ['generates_invoice' => false]);

    expect(DB::transaction(fn () => QuoteInvoiceIssuer::issueForApprovedQuote($coordination, $courtesy)))->toBeNull()
        ->and(DB::transaction(fn () => QuoteInvoiceIssuer::issueForApprovedQuote($coordination, $without)))->toBeNull();

    $items = $courtesy->items;
    $items[1]['is_courtesy'] = true;
    $partial = qifQuote($coordination, $labs, ['items' => $items]);

    $invoice = DB::transaction(fn () => QuoteInvoiceIssuer::issueForApprovedQuote($coordination, $partial));

    expect($invoice)->not->toBeNull()
        ->and((float) $invoice->total_usd)->toBe(13.0)
        ->and($invoice->items()->count())->toBe(1);
});

it('no factura un ítem que ya tiene una factura vigente', function (): void {
    ['coordination' => $coordination, 'labs' => $labs] = qifScenario();

    DB::transaction(fn () => QuoteInvoiceIssuer::issueForApprovedQuote($coordination, qifQuote($coordination, $labs)));

    expect(fn () => DB::transaction(fn () => QuoteInvoiceIssuer::issueForApprovedQuote($coordination, qifQuote($coordination, $labs))))
        ->toThrow(MedicationInvoiceException::class, 'factura vigente');
});

it('el paso de facturación directa desde inventario quedó retirado de Gestionar servicio', function (): void {
    ['coordination' => $coordination, 'labs' => $labs] = qifScenario();

    expect(MedicationInvoiceWizard::$directInvoicingEnabled)->toBeFalse()
        ->and(MedicationInvoiceWizard::applies($coordination, ['lab:'.$labs[0]->id]))->toBeFalse();

    $form = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/ManageCoordinationServiceItemsForm.php');

    expect($form)
        ->not->toContain("TextInput::make('manage_quote_porcentaje_ganancia')")
        ->toContain('QuoteLineProfitField::make()')
        ->toContain("QuoteInvoiceFormFields::make(\n");
});

it('la pantalla Gestionar servicio muestra el % por ítem, el resumen y la pregunta de factura con su responsable', function (): void {
    $name = 'PERFIL PANTALLA PEST '.strtoupper(substr(md5(uniqid('', true)), 0, 6));
    TelemedicineListLaboratory::query()->create(['name' => $name, 'type' => 'NO CUBIERTO', 'cost' => 12, 'profit_percentage' => 35]);

    ['coordination' => $coordination, 'labs' => $labs] = qifScenario();
    $labs[0]->forceFill(['laboratory' => $name])->save();
    CoordinationServiceItemsManager::flushClinicalItemsCache();
    $key = 'lab:'.$labs[0]->id;

    $page = Livewire\Livewire::test(App\Filament\Operations\Resources\OperationCoordinationServices\Pages\ManageCoordinationServiceItems::class, ['record' => $coordination->getKey()])
        ->set('data.managed_service_item_keys', [$key])
        ->assertSuccessful()
        ->assertSee('% de utilidad')
        ->assertSee('Ganancia total')
        ->assertSee('¿Esta cotización genera factura?')
        ->assertDontSee('Medicamentos desde inventario')
        ->assertDontSee('Porcentaje de ganancia');

    $line = collect($page->get('data.manage_quote_line_items'))->firstWhere('key', $key);

    expect((float) $line['profit_percentage'])->toBe(35.0);

    $page->set('data.manage_quote_generates_invoice', 1)
        ->assertSee('Responsable de pago')
        ->assertSee('El paciente')
        ->assertSee('El grupo corporativo')
        ->set('data.manage_quote_invoice_payer_type', OperationMedicationInvoice::PAYER_PATIENT)
        ->assertSee('Responsable de pago en la factura');
});

it('al editar una cotización pendiente se cambian los % por ítem y se quita la factura', function (): void {
    ['coordination' => $coordination, 'labs' => $labs] = qifScenario();
    $quote = qifQuote($coordination, $labs);

    $page = Livewire\Livewire::test(App\Filament\Operations\Resources\OperationCoordinationServices\Pages\ManageCoordinationServiceQuotes::class, ['record' => $coordination->getKey()])
        ->mountAction('editPendingQuote', ['quoteId' => $quote->id])
        ->assertActionMounted('editPendingQuote');

    $lines = $page->get('mountedActions.0.data.edit_quote_line_items');
    $firstKey = array_key_first($lines);

    expect((float) $lines[$firstKey]['profit_percentage'])->toBe(30.0)
        ->and((bool) $page->get('mountedActions.0.data.edit_quote_generates_invoice'))->toBeTrue();

    $page->set('mountedActions.0.data.edit_quote_line_items.'.$firstKey.'.profit_percentage', 50)
        ->set('mountedActions.0.data.edit_quote_generates_invoice', 0)
        ->callMountedAction()
        ->assertHasNoErrors();

    $quote->refresh();

    expect((float) $quote->total)->toBe(35.0)
        ->and((float) $quote->porcentaje_ganancia)->toBe(16.67)
        ->and($quote->generates_invoice)->toBeFalse()
        ->and($quote->invoice_payer_type)->toBeNull();
});

it('una cotización anterior al % por ítem conserva su total al abrirla para editar', function (): void {
    ['coordination' => $coordination, 'labs' => $labs] = qifScenario();
    $legacy = qifQuote($coordination, $labs, [
        'items' => [
            ['key' => 'lab:'.$labs[0]->id, 'label' => $labs[0]->laboratory, 'unit_price_usd' => 10],
            ['key' => 'lab:'.$labs[1]->id, 'label' => $labs[1]->laboratory, 'unit_price_usd' => 20],
        ],
        'porcentaje_ganancia' => 20,
        'total' => 36,
        'generates_invoice' => false,
    ]);

    $defaults = App\Support\Operations\CoordinationServiceQuoteEditForm::defaults($legacy);

    expect(QuoteLinePricing::totals($defaults['edit_quote_line_items'])['total'])->toBe(36.0);
});
