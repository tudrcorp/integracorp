<?php

declare(strict_types=1);

use App\Support\Operations\OperationQuoteGeneratorPublicAmounts;

it('suma la ganancia al monto base de la cotización', function (): void {
    expect(OperationQuoteGeneratorPublicAmounts::applyProfit(10, 10))->toBe(11.0)
        ->and(OperationQuoteGeneratorPublicAmounts::applyProfit(20, 10))->toBe(22.0)
        ->and(OperationQuoteGeneratorPublicAmounts::applyProfit(10, 0))->toBe(10.0);
});

it('aplica la ganancia a los precios unitarios de cada ítem', function (): void {
    $items = OperationQuoteGeneratorPublicAmounts::itemsWithProfit([
        [
            'label' => 'Hemograma',
            'unit_price_usd' => 10,
            'unit_price_ves' => 7850.7,
        ],
    ], 10);

    expect($items[0]['unit_price_usd'])->toBe(11.0)
        ->and($items[0]['unit_price_ves'])->toBe(8635.77)
        ->and($items[0]['label'])->toBe('Hemograma');
});
