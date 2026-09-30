<?php

declare(strict_types=1);

use App\Models\OperationServiceOrder;
use App\Models\Supplier;
use App\Services\OperationServiceOrderQuotePdfService;

uses(Tests\TestCase::class);
use App\Services\OperationServiceOrderPdfService;

test('pdf filename sanitizes order number', function () {
    $order = new OperationServiceOrder;
    $order->order_number = 'OS-001/A';

    expect(OperationServiceOrderPdfService::filename($order))->toBe('orden-servicio-OS-001_A.pdf');
});

test('quote pdf filename sanitizes order number', function () {
    $order = new OperationServiceOrder;
    $order->order_number = 'OS-001/A';

    expect(OperationServiceOrderQuotePdfService::filename($order))->toBe('cotizacion-asociada-OS-001_A.pdf');
});

test('operation service order pdf blade renders without errors', function () {
    $order = new OperationServiceOrder([
        'order_number' => 'T1',
        'description' => 'D',
        'service_type' => 'ESPECIALISTA',
        'operation_coordination_service_id' => 1,
        'created_by' => 'x',
    ]);
    $order->exists = true;
    $order->setRelation('operationCoordinationService', null);
    $order->setRelation('supplier', null);
    $order->setRelation('doctorNurse', null);
    $order->setRelation('approvedOperationQuote', null);
    $order->setRelation('telemedicinePriority', null);
    $order->setRelation('operationInventoryUbication', null);
    $order->setRelation('operationServiceOrderItems', collect());
    $order->setAttribute('created_at', now());
    $order->setAttribute('updated_at', now());

    $html = view('documents.operation-service-order-pdf', [
        'order' => $order,
        'logoDataUri' => '',
    ])->render();

    expect($html)->toContain('Orden de servicio')
        ->and($html)->toContain('departamento de operaciones de Tu Doctor en Casa')
        ->and($html)->toContain('Servicio complementario')
        ->and($html)->toContain('Tipo de servicio')
        ->and($html)->toContain('ESPECIALISTA')
        ->and($html)->toContain('Proveedor')
        ->and($html)->toContain('Teléfono')
        ->and($html)->toContain('Dirección')
        ->and($html)->not->toContain('Trazabilidad')
        ->and($html)->not->toContain('Datos de la orden')
        ->and($html)->not->toContain('Montos y pago')
        ->and($html)->not->toContain('Coordinación y paciente');
});

test('operation service order pdf muestra datos de paciente y oculta secciones retiradas', function () {
    $coord = new App\Models\OperationCoordinationService([
        'patient' => 'IGNACIO ALEJANDRO RAMOS VASQUEZ',
        'ci_patient' => '18093303-2',
        'phone_holder' => '4241778952',
        'reference_number' => 'REF-31599',
        'address' => 'Urbanizacion Las Rosas',
        'contractor' => 'CORPORATIVO',
    ]);
    $coord->setRelation('state', null);
    $coord->setRelation('city', null);

    $order = new OperationServiceOrder([
        'order_number' => 'ORD-0062',
        'description' => 'Referencia de Pediatra',
        'service_type' => 'ESPECIALISTA',
        'operation_coordination_service_id' => 286,
        'created_by' => 'x',
    ]);
    $order->exists = true;
    $order->setRelation('operationCoordinationService', $coord);
    $order->setRelation('supplier', null);
    $order->setRelation('doctorNurse', null);
    $order->setRelation('approvedOperationQuote', null);
    $order->setRelation('telemedicinePriority', null);
    $order->setRelation('operationInventoryUbication', null);
    $order->setRelation('operationServiceOrderItems', collect());
    $order->setAttribute('created_at', now());
    $order->setAttribute('updated_at', now());

    $html = view('documents.operation-service-order-pdf', [
        'order' => $order,
        'logoDataUri' => '',
    ])->render();

    expect($html)->toContain('Datos de paciente')
        ->and($html)->toContain('IGNACIO ALEJANDRO RAMOS VASQUEZ')
        ->and($html)->toContain('Tipo de servicio')
        ->and($html)->toContain('ESPECIALISTA')
        ->and($html)->toContain('Servicio complementario')
        ->and($html)->toContain('Proveedor')
        ->and($html)->toContain('Teléfono')
        ->and($html)->toContain('Dirección')
        ->and($html)->not->toContain('Datos de la orden')
        ->and($html)->not->toContain('Coordinación y paciente')
        ->and($html)->not->toContain('Montos y pago')
        ->and($html)->not->toContain('Método de pago')
        ->and($html)->not->toContain('Total USD');
});

test('operation service order pdf incluye la direccion del proveedor', function () {
    $supplier = new Supplier([
        'name' => 'CENTRO PROFESIONAL COLONIAL C.A',
        'personal_phone' => '04141234567',
        'ubicacion_principal' => 'Av. Bolivar, Calabozo',
    ]);
    $supplier->setRelation('state', null);
    $supplier->setRelation('city', null);

    $order = new OperationServiceOrder([
        'order_number' => 'ORD-0062',
        'description' => 'Referencia de Pediatra',
        'service_type' => 'ESPECIALISTA',
        'operation_coordination_service_id' => 286,
        'created_by' => 'x',
    ]);
    $order->exists = true;
    $order->setRelation('operationCoordinationService', null);
    $order->setRelation('supplier', $supplier);
    $order->setRelation('doctorNurse', null);
    $order->setRelation('approvedOperationQuote', null);
    $order->setRelation('telemedicinePriority', null);
    $order->setRelation('operationInventoryUbication', null);
    $order->setRelation('operationServiceOrderItems', collect());
    $order->setAttribute('created_at', now());
    $order->setAttribute('updated_at', now());

    $html = view('documents.operation-service-order-pdf', [
        'order' => $order,
        'logoDataUri' => '',
    ])->render();

    expect($html)->toContain('Proveedor')
        ->and($html)->toContain('CENTRO PROFESIONAL COLONIAL C.A')
        ->and($html)->toContain('Teléfono')
        ->and($html)->toContain('04141234567')
        ->and($html)->toContain('Dirección')
        ->and($html)->toContain('Av. Bolivar, Calabozo')
        ->and($html)->not->toContain('Proveedor natural')
        ->and($html)->not->toContain('Proveedor jurídico');
});

it('la plantilla de OS no incluye datos de la orden ni montos y pago', function (): void {
    $src = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/operation-service-order-pdf.blade.php');

    expect($src)
        ->toContain('Datos de paciente')
        ->toContain('Servicio complementario')
        ->toContain('Tipo de servicio')
        ->toContain('Proveedor')
        ->toContain('Teléfono')
        ->toContain('Dirección')
        ->toContain('OperationServiceOrderProviderSummary::nameOrDash')
        ->toContain('OperationServiceOrderProviderSummary::addressOrDash')
        ->toContain('OperationServiceOrderProviderSummary::phoneOrDash')
        ->toContain('TelemedicinePatientDisplayName::forCoordination')
        ->toContain('class="item-cat"')
        ->toContain('width:16%')
        ->toContain('max-width: 110px')
        ->toContain('margin-bottom: 10px')
        ->toContain('section-title--block')
        ->and($src)->not->toContain('Datos de la orden')
        ->and($src)->not->toContain('Coordinación y paciente')
        ->and($src)->not->toContain('Montos y pago')
        ->and($src)->not->toContain('Proveedor natural')
        ->and($src)->not->toContain('Proveedor jurídico')
        ->and($src)->not->toContain('Proveedor No Convenido');
});

test('operation service order quote blade renders without errors', function () {
    $order = new OperationServiceOrder([
        'order_number' => 'T2',
        'description' => 'D',
        'operation_coordination_service_id' => 2,
        'created_by' => 'x',
    ]);
    $order->exists = true;
    $order->setRelation('operationCoordinationService', null);
    $order->setRelation('supplier', null);
    $order->setRelation('telemedicinePriority', null);

    $html = view('documents.operation-service-order-quote-pdf', [
        'order' => $order,
        'quoteData' => [
            'service_label' => 'Laboratorio',
            'price_usd' => 10,
            'price_ves' => 1000,
            'bcv_rate' => 100,
        ],
        'logoDataUri' => '',
    ])->render();

    expect($html)->toContain('Cotización asociada')
        ->and($html)->toContain('Precio cotizado (USD)')
        ->and($html)->not->toContain('Tasa BCV aplicada')
        ->and($html)->not->toContain('Precio cotizado (Bs.)');
});

test('operation service order medication quote blade renders without errors', function () {
    $order = new OperationServiceOrder([
        'order_number' => 'T3',
        'description' => 'D',
        'operation_coordination_service_id' => 3,
        'created_by' => 'x',
    ]);
    $order->exists = true;
    $order->setRelation('operationCoordinationService', null);
    $order->setRelation('supplier', null);
    $order->setRelation('telemedicinePriority', null);

    $html = view('documents.operation-service-order-medication-quote-pdf', [
        'order' => $order,
        'quoteMeta' => [
            'quote_number' => 'COT-T3-01',
            'supplier_name' => 'Proveedor Demo',
            'bcv_rate' => 100,
            'total_amount_usd' => 10,
            'total_amount_ves' => 1000,
        ],
        'items' => [
            ['item_name' => 'Paracetamol', 'quantity' => 1, 'unit_amount_usd' => 10, 'line_total_usd' => 10],
        ],
        'logoDataUri' => '',
    ])->render();

    expect($html)->toContain('Cotización de medicamentos')
        ->and($html)->toContain('Ítems cotizados')
        ->and($html)->toContain('Paracetamol')
        ->and($html)->not->toContain('Total Bs.')
        ->and($html)->not->toContain('Tasa BCV');
});

/**
 * @param  array<string, mixed>  $coordinationAttributes
 */
function serviceOrderWithCase(array $coordinationAttributes, ?string $caseCode, ?string $consultationReference = null): OperationServiceOrder
{
    $coord = new App\Models\OperationCoordinationService([
        'patient' => 'GENESIS SOFIA COVA FIGUEROA',
        'ci_patient' => '33478468',
        'phone_holder' => '0414-861.63.71',
        ...$coordinationAttributes,
    ]);
    $coord->setRelation('state', null);
    $coord->setRelation('city', null);
    $coord->setRelation('telemedicineCase', $caseCode === null ? null : new App\Models\TelemedicineCase(['code' => $caseCode]));
    $coord->setRelation('telemedicineConsultationPatient', $consultationReference === null
        ? null
        : new App\Models\TelemedicineConsultationPatient(['code_reference' => $consultationReference]));

    $order = new OperationServiceOrder([
        'order_number' => 'ORD-0285',
        'service_type' => 'MEDICAMENTOS',
        'operation_coordination_service_id' => 1,
        'created_by' => 'x',
    ]);
    $order->exists = true;
    $order->setRelation('operationCoordinationService', $coord);
    $order->setRelation('supplier', null);
    $order->setRelation('doctorNurse', null);
    $order->setRelation('approvedOperationQuote', null);
    $order->setRelation('telemedicinePriority', null);
    $order->setRelation('operationInventoryUbication', null);
    $order->setRelation('operationServiceOrderItems', collect());
    $order->setAttribute('created_at', now());

    return $order;
}

test('el encabezado de la orden muestra el código del caso y la referencia', function () {
    $order = serviceOrderWithCase(['reference_number' => 'REF-58180'], '89928-0732');

    $html = view('documents.operation-service-order-pdf', ['order' => $order, 'logoDataUri' => ''])->render();
    $header = Illuminate\Support\Str::between($html, 'class="col-title title-cell"', '</td>');

    expect($header)
        ->toContain('N° <strong>ORD-0285</strong>')
        ->toContain('Caso: <strong>89928-0732</strong>')
        ->toContain('Referencia: <strong>REF-58180</strong>');
});

test('la referencia ya no se mezcla con el teléfono del paciente', function () {
    $order = serviceOrderWithCase(['reference_number' => 'REF-58180'], '89928-0732');

    $html = view('documents.operation-service-order-pdf', ['order' => $order, 'logoDataUri' => ''])->render();

    expect($html)
        ->not->toContain('Teléfono / Ref.')
        ->not->toContain('0414-861.63.71 · REF-58180')
        ->toContain('0414-861.63.71');
});

test('si la coordinación no guardó la referencia se toma la de la consulta', function () {
    $order = serviceOrderWithCase(['reference_number' => null], '89928-0732', 'REF-11111');

    expect(App\Support\Operations\OperationServiceOrderCaseReference::referenceNumber($order))->toBe('REF-11111');
});

test('una orden sin caso ni referencia muestra guiones sin romper el documento', function () {
    $order = serviceOrderWithCase(['reference_number' => '  '], null);

    $html = view('documents.operation-service-order-pdf', ['order' => $order, 'logoDataUri' => ''])->render();

    expect(App\Support\Operations\OperationServiceOrderCaseReference::caseCode($order))->toBeNull()
        ->and(App\Support\Operations\OperationServiceOrderCaseReference::referenceNumber($order))->toBeNull()
        ->and($html)->toContain('Caso: <strong>—</strong>')
        ->and($html)->toContain('Referencia: <strong>—</strong>');
});

test('el servicio de PDF precarga el caso y la consulta para no consultar en la vista', function () {
    $service = file_get_contents(dirname(__DIR__, 2).'/app/Services/OperationServiceOrderPdfService.php');

    expect($service)
        ->toContain("'operationCoordinationService.telemedicineCase:id,code'")
        ->toContain("'operationCoordinationService.telemedicineConsultationPatient:id,code_reference'");
});
