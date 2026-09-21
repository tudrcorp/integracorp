<?php

declare(strict_types=1);

use App\Models\OperationQuoteGenerator;
use App\Models\OperationServiceOrder;
use App\Models\OperationServiceOrderItem;
use App\Support\Operations\CoordinationServiceAssociatedItemPricePreview;
use Filament\Actions\Action;

it('crea la acción de precios solo cuando hay cotización u orden', function (): void {
    $action = CoordinationServiceAssociatedItemPricePreview::makeAction([
        'id' => 15,
        'item_type' => 'lab',
        'title' => 'Laboratorio: CREATININA',
        'provider_name' => 'Laboratorio Central',
        'quote_id' => 8,
        'order_id' => 20,
    ]);

    expect($action)->toBeInstanceOf(Action::class)
        ->and($action?->getName())->toBe('previewAssociatedItemPrices_lab_15')
        ->and($action?->getLabel())->toBe('Ver precios')
        ->and($action?->getTooltip())->toBe('Ver precios de cotización y orden de servicio');

    expect(CoordinationServiceAssociatedItemPricePreview::makeAction([
        'id' => 15,
        'item_type' => 'lab',
        'title' => 'Laboratorio: CREATININA',
        'quote_id' => null,
        'order_id' => null,
    ]))->toBeNull();
});

it('renderiza precios de cotización y orden de servicio', function (): void {
    $quote = new OperationQuoteGenerator([
        'type_service' => 'LABORATORIOS',
        'total' => 40,
        'costo_dolares' => 32,
        'costo_bolivares' => 1600,
        'porcentaje_ganancia' => 25,
        'subtotal' => 32,
        'items' => [
            [
                'category' => 'Laboratorio',
                'label' => 'CREATININA',
                'unit_price_usd' => 12.5,
                'unit_price_ves' => 500,
            ],
            [
                'category' => 'Laboratorio',
                'label' => 'GLICEMIA',
                'unit_price_usd' => 27.5,
                'unit_price_ves' => 1100,
            ],
        ],
    ]);
    $quote->id = 8;

    $order = new OperationServiceOrder([
        'order_number' => 'ORD-0062',
        'service_type' => 'LABORATORIOS',
        'status' => 'EN GESTION',
        'total_amount_usd' => 40,
        'total_amount_ves' => 1600,
        'tasa_bcv' => 40,
    ]);
    $order->id = 20;
    $order->setRelation('operationServiceOrderItems', collect([
        new OperationServiceOrderItem([
            'category' => 'LABORATORIOS',
            'item_name' => 'CREATININA',
            'quantity' => 1,
            'amount' => 12.5,
        ]),
    ]));

    $html = (string) CoordinationServiceAssociatedItemPricePreview::render(
        $quote,
        $order,
        'Laboratorio: CREATININA',
        'Laboratorio Central',
    );

    expect($html)
        ->toContain('fi-associated-item-price-preview-quote')
        ->toContain('fi-associated-item-price-preview-order')
        ->toContain('COT-000008')
        ->toContain('ORD-0062')
        ->toContain('CREATININA')
        ->toContain('US$ 12.50')
        ->toContain('Bs. 500.00')
        ->toContain('US$ 40.00')
        ->toContain('Laboratorio Central');
});

it('explica cuando no hay documentos de gestión para mostrar precios', function (): void {
    $html = (string) CoordinationServiceAssociatedItemPricePreview::render(
        null,
        null,
        'Laboratorio: CREATININA',
        '—',
    );

    expect($html)->toContain('No hay cotización ni orden de servicio asociadas a este ítem.');
});
