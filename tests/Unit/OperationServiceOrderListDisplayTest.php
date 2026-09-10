<?php

declare(strict_types=1);

use App\Models\OperationCoordinationService;
use App\Models\OperationQuoteGenerator;
use App\Models\OperationServiceOrder;
use App\Models\OperationServiceOrderQuote;
use App\Models\TelemedicinePatient;
use App\Support\Operations\OperationServiceOrderListDisplay;
use Illuminate\Support\Collection;

function serviceOrderWithCoordination(
    array $orderAttributes = [],
    array $coordinationAttributes = [],
    ?TelemedicinePatient $patient = null,
): OperationServiceOrder {
    $order = new OperationServiceOrder($orderAttributes);
    $coordination = new OperationCoordinationService($coordinationAttributes);
    $coordination->setRelation('telemedicinePatient', $patient);
    $order->setRelation('operationCoordinationService', $coordination);
    $order->setRelation('approvedOperationQuote', null);
    $order->setRelation('operationServiceOrderQuotes', new Collection);

    return $order;
}

it('agrupa nombre completo y cédula del paciente', function (): void {
    $order = serviceOrderWithCoordination(
        [],
        [
            'patient' => 'Quevedo Briceno Rebeca',
            'ci_patient' => 'V-12345678',
        ],
    );

    expect(OperationServiceOrderListDisplay::patientFullName($order))->toBe('Quevedo Briceno Rebeca')
        ->and(OperationServiceOrderListDisplay::patientDocument($order))->toBe('V-12345678')
        ->and(OperationServiceOrderListDisplay::patientDocumentLabel($order))->toBe('C.I. V-12345678');
});

it('toma la cédula del paciente de telemedicina cuando la coordinación no la tiene', function (): void {
    $patient = new TelemedicinePatient([
        'full_name' => 'Ana Pérez',
        'nro_identificacion' => '87654321',
    ]);

    $order = serviceOrderWithCoordination([], ['patient' => 'Ana Pérez', 'ci_patient' => null], $patient);

    expect(OperationServiceOrderListDisplay::patientDocument($order))->toBe('87654321')
        ->and(OperationServiceOrderListDisplay::patientDocumentLabel($order))->toBe('C.I. 87654321');
});

it('muestra Sin cédula cuando no hay documento', function (): void {
    $order = serviceOrderWithCoordination([], ['patient' => 'Sin Documento', 'ci_patient' => null]);

    expect(OperationServiceOrderListDisplay::patientDocument($order))->toBe('')
        ->and(OperationServiceOrderListDisplay::patientDocumentLabel($order))->toBe('Sin cédula');
});

it('resuelve la unidad de negocio específica del paciente', function (): void {
    $patient = new TelemedicinePatient([
        'full_name' => 'Luis Gómez',
        'specific_business_unit' => 'Convenio Polar Valencia',
    ]);

    $withUnit = serviceOrderWithCoordination([], ['patient' => 'Luis Gómez'], $patient);
    $withoutUnit = serviceOrderWithCoordination([], ['patient' => 'Luis Gómez']);

    expect(OperationServiceOrderListDisplay::specificBusinessUnit($withUnit))->toBe('Convenio Polar Valencia')
        ->and(OperationServiceOrderListDisplay::specificBusinessUnit($withoutUnit))->toBe('—');
});

it('usa el total de la cotización aprobada como monto', function (): void {
    $order = serviceOrderWithCoordination();
    $quote = new OperationQuoteGenerator([
        'total' => 150.5,
        'costo_dolares' => 100,
    ]);
    $order->setRelation('approvedOperationQuote', $quote);

    expect(OperationServiceOrderListDisplay::quoteAmountUsd($order))->toBe(150.5)
        ->and(OperationServiceOrderListDisplay::quoteAmountLabel($order))->toBe('US$ 150,50');
});

it('suma cotizaciones de medicamentos cuando no hay cotización aprobada', function (): void {
    $order = serviceOrderWithCoordination();
    $order->setRelation('operationServiceOrderQuotes', collect([
        new OperationServiceOrderQuote(['total_amount_usd' => 10]),
        new OperationServiceOrderQuote(['total_amount_usd' => 5.25]),
    ]));

    expect(OperationServiceOrderListDisplay::quoteAmountUsd($order))->toBe(15.25)
        ->and(OperationServiceOrderListDisplay::quoteAmountLabel($order))->toBe('US$ 15,25');
});

it('usa el precio de cotización de la coordinación como respaldo', function (): void {
    $order = serviceOrderWithCoordination([], ['quote_price' => 88.9]);

    expect(OperationServiceOrderListDisplay::quoteAmountUsd($order))->toBe(88.9)
        ->and(OperationServiceOrderListDisplay::quoteAmountLabel($order))->toBe('US$ 88,90');
});

it('deja el monto en guion cuando no hay cotización', function (): void {
    $order = serviceOrderWithCoordination();

    expect(OperationServiceOrderListDisplay::quoteAmountUsd($order))->toBeNull()
        ->and(OperationServiceOrderListDisplay::quoteAmountLabel($order))->toBe('—');
});

it('formatea el código de cotización y resuelve la ruta del PDF', function (): void {
    $order = serviceOrderWithCoordination(['associated_quote_pdf_path' => 'quotes/from-order.pdf']);
    $quote = new OperationQuoteGenerator([
        'id' => 12,
        'quote_pdf_path' => 'quotes/approved.pdf',
    ]);
    $quote->id = 12;
    $order->setRelation('approvedOperationQuote', $quote);

    $fromOrderPath = serviceOrderWithCoordination(['associated_quote_pdf_path' => 'quotes/from-order.pdf']);

    expect(OperationServiceOrderListDisplay::quoteCodeLabel(12))->toBe('COT-000012')
        ->and(OperationServiceOrderListDisplay::quoteCodeLabel(null))->toBe('—')
        ->and(OperationServiceOrderListDisplay::quotePdfStoragePath($order))->toBe('quotes/approved.pdf')
        ->and(OperationServiceOrderListDisplay::quotePdfStoragePath($fromOrderPath))->toBe('quotes/from-order.pdf');
});

it('el estatus administrativo queda en PENDIENTE y color rojo por defecto', function (): void {
    $empty = new OperationServiceOrder;
    $pending = new OperationServiceOrder(['administrative_status' => 'pendiente']);
    $other = new OperationServiceOrder(['administrative_status' => 'PROCESADO']);

    expect(OperationServiceOrderListDisplay::administrativeStatus($empty))->toBe('PENDIENTE')
        ->and(OperationServiceOrderListDisplay::administrativeStatus($pending))->toBe('PENDIENTE')
        ->and(OperationServiceOrderListDisplay::administrativeStatusColor(null))->toBe('danger')
        ->and(OperationServiceOrderListDisplay::administrativeStatusColor('PENDIENTE'))->toBe('danger')
        ->and(OperationServiceOrderListDisplay::administrativeStatusColor('PROCESADO'))->toBe('gray')
        ->and(OperationServiceOrderListDisplay::administrativeStatus($other))->toBe('PROCESADO');
});
