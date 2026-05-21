<?php

declare(strict_types=1);

use App\Models\OperationServiceOrderQuote;

it('OperationServiceOrderQuote convierte items_payload a arreglo', function (): void {
    $quote = new OperationServiceOrderQuote;
    $quote->fill([
        'quote_number' => 'COT-ORD-0001-01',
        'items_payload' => [
            ['item_id' => 10, 'item_name' => 'Paracetamol', 'quantity' => 1],
        ],
    ]);

    expect($quote->items_payload)
        ->toBeArray()
        ->and($quote->items_payload[0]['item_name'])->toBe('Paracetamol');
});
