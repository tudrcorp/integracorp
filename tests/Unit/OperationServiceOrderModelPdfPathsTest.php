<?php

declare(strict_types=1);

use App\Models\OperationServiceOrder;

it('permite asignar las rutas de pdf generados en la orden de servicio', function (): void {
    $model = new OperationServiceOrder;
    $model->fill([
        'service_order_pdf_path' => 'operation-service-orders/generated-pdf/service-order-ord-0001.pdf',
        'associated_quote_pdf_path' => 'operation-service-orders/generated-pdf/quote-ord-0001.pdf',
    ]);

    expect($model->service_order_pdf_path)->toBe('operation-service-orders/generated-pdf/service-order-ord-0001.pdf')
        ->and($model->associated_quote_pdf_path)->toBe('operation-service-orders/generated-pdf/quote-ord-0001.pdf');
});
