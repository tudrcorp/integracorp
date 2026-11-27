<?php

declare(strict_types=1);

use App\Models\OperationQuoteGenerator;
use App\Support\Operations\CoordinationServiceItemsManager;
use App\Support\Operations\CoordinationServiceQuoteEditForm;
use App\Support\Operations\CoordinationServiceQuoteManager;
use App\Support\Operations\QuoteLinePricing;
use App\Support\Operations\QuoteSummaryPanel;

uses(Tests\TestCase::class);

it('solo los medicamentos se cotizan por cantidad', function (): void {
    expect(QuoteLinePricing::quantityApplies('medication:10'))->toBeTrue()
        ->and(QuoteLinePricing::quantityApplies('lab:4'))->toBeFalse()
        ->and(QuoteLinePricing::quantityApplies('study:4'))->toBeFalse()
        ->and(QuoteLinePricing::quantityApplies('specialty:4'))->toBeFalse()
        ->and(QuoteLinePricing::quantityApplies(null))->toBeFalse();
});

it('acepta solo cantidades enteras entre 1 y el máximo', function (): void {
    expect(QuoteLinePricing::parseQuantity(3))->toBe(3)
        ->and(QuoteLinePricing::parseQuantity('12'))->toBe(12)
        ->and(QuoteLinePricing::parseQuantity(' 2 '))->toBe(2)
        ->and(QuoteLinePricing::parseQuantity(4.0))->toBe(4)
        ->and(QuoteLinePricing::parseQuantity(0))->toBeNull()
        ->and(QuoteLinePricing::parseQuantity(-1))->toBeNull()
        ->and(QuoteLinePricing::parseQuantity('1.5'))->toBeNull()
        ->and(QuoteLinePricing::parseQuantity(2.5))->toBeNull()
        ->and(QuoteLinePricing::parseQuantity(''))->toBeNull()
        ->and(QuoteLinePricing::parseQuantity(null))->toBeNull()
        ->and(QuoteLinePricing::parseQuantity('abc'))->toBeNull()
        ->and(QuoteLinePricing::parseQuantity(QuoteLinePricing::MAX_QUANTITY + 1))->toBeNull();
});

it('un medicamento sin cantidad queda pendiente; lo demás y las cotizaciones anteriores valen 1', function (): void {
    expect(QuoteLinePricing::quantity(['key' => 'medication:1', 'quantity' => null]))->toBeNull()
        ->and(QuoteLinePricing::quantity(['key' => 'medication:1', 'quantity' => '']))->toBeNull()
        ->and(QuoteLinePricing::quantity(['key' => 'medication:1', 'quantity' => 5]))->toBe(5)
        ->and(QuoteLinePricing::quantity(['key' => 'medication:1']))->toBe(1)
        ->and(QuoteLinePricing::quantity(['key' => 'lab:1', 'quantity' => 9]))->toBe(1)
        ->and(QuoteLinePricing::storedQuantity(['key' => 'lab:1']))->toBe(1)
        ->and(QuoteLinePricing::storedQuantity(['key' => 'medication:1']))->toBeNull()
        ->and(QuoteLinePricing::storedQuantity(['key' => 'medication:1', 'quantity' => '3']))->toBe(3);
});

it('multiplica costo y venta por la cantidad, redondeando por línea', function (): void {
    $totals = QuoteLinePricing::totals([
        ['key' => 'medication:1', 'label' => 'LORATADINA', 'quantity' => 3, 'unit_price_usd' => 2.5, 'profit_percentage' => 20],
        ['key' => 'lab:7', 'label' => 'HEMATOLOGÍA', 'quantity' => 4, 'unit_price_usd' => 10, 'profit_percentage' => null],
    ]);

    expect($totals['lines'][0])->toMatchArray([
        'quantity' => 3,
        'unit_cost' => 2.5,
        'unit_price' => 3.0,
        'cost' => 7.5,
        'profit' => 1.5,
        'price' => 9.0,
    ])
        ->and($totals['lines'][1])->toMatchArray(['quantity' => 1, 'cost' => 10.0, 'price' => 10.0])
        ->and($totals['cost'])->toBe(17.5)
        ->and($totals['total'])->toBe(19.0)
        ->and($totals['profit'])->toBe(1.5)
        ->and($totals['priced'])->toBe(2);

    expect(QuoteLinePricing::lineSalePrice(['key' => 'medication:1', 'quantity' => 3, 'unit_price_usd' => 1.11, 'profit_percentage' => 33.33]))
        ->toBe(round(round(1.11 * 1.3333, 2) * 3, 2));
});

it('una línea de medicamento sin cantidad no suma y se reporta como pendiente', function (): void {
    $lines = [
        ['key' => 'medication:1', 'label' => 'LORATADINA', 'quantity' => null, 'unit_price_usd' => 2.5, 'profit_percentage' => 20],
        ['key' => 'medication:2', 'label' => 'IBUPROFENO', 'quantity' => 2, 'unit_price_usd' => 4, 'profit_percentage' => null],
    ];

    $totals = QuoteLinePricing::totals($lines);

    expect($totals['priced'])->toBe(1)
        ->and($totals['count'])->toBe(2)
        ->and($totals['cost'])->toBe(8.0)
        ->and(CoordinationServiceItemsManager::manageQuoteSubtotalFromLineItems($lines))->toBe(8.0)
        ->and(QuoteLinePricing::validationMessage($lines))->toContain('Indique la cantidad de «LORATADINA»');

    $html = QuoteSummaryPanel::render($lines, 40.0)->toHtml();

    expect($html)
        ->toContain('Cant.')
        ->toContain('Falta el precio o la cantidad de 1 ítem')
        ->toContain('US$ 4,00 c/u');
});

it('valida la cantidad solo en medicamentos y conserva la validación del % de utilidad', function (): void {
    expect(QuoteLinePricing::validationMessage([
        ['key' => 'lab:1', 'label' => 'PERFIL', 'unit_price_usd' => 5],
        ['key' => 'medication:1', 'label' => 'LORATADINA', 'quantity' => 2, 'unit_price_usd' => 5],
    ]))->toBeNull()
        ->and(QuoteLinePricing::validationMessage([
            ['key' => 'medication:1', 'label' => 'LORATADINA', 'quantity' => 0, 'unit_price_usd' => 5],
        ]))->toContain('número entero entre 1 y')
        ->and(QuoteLinePricing::validationMessage([
            ['key' => 'lab:1', 'label' => 'PERFIL', 'unit_price_usd' => 5, 'profit_percentage' => -4],
        ]))->toContain('% de utilidad');
});

it('una cotización anterior a la cantidad conserva su total al abrirla para editar', function (): void {
    $quote = new OperationQuoteGenerator([
        'items' => [
            ['key' => 'medication:1', 'label' => 'LORATADINA', 'unit_price_usd' => 10, 'profit_percentage' => 20, QuoteLinePricing::ITEM_PRICING_FLAG => true],
            ['key' => 'lab:3', 'label' => 'PERFIL', 'unit_price_usd' => 5, 'profit_percentage' => null, QuoteLinePricing::ITEM_PRICING_FLAG => true],
        ],
        'porcentaje_ganancia' => 0,
    ]);

    $lines = CoordinationServiceQuoteEditForm::defaults($quote)['edit_quote_line_items'];

    expect($lines[0]['quantity'])->toBe(1)
        ->and($lines[1]['quantity'])->toBe(1)
        ->and(QuoteLinePricing::totals($lines)['total'])->toBe(17.0);
});

it('la orden de servicio toma la cantidad cotizada de cada medicamento', function (): void {
    $quote = new OperationQuoteGenerator([
        'items' => [
            ['key' => 'medication:15', 'quantity' => 4],
            ['key' => 'medication:16'],
            ['key' => 'medication:17', 'quantity' => null],
            ['key' => 'lab:3', 'quantity' => 8],
        ],
    ]);

    expect(CoordinationServiceQuoteManager::quotedMedicationQuantities($quote))->toBe([15 => 4, 16 => 1]);
});

it('la factura de la cotización y el PDF usan cantidad y total de la línea', function (): void {
    $issuer = file_get_contents(base_path('app/Support/Operations/QuoteInvoiceIssuer.php'));
    $pdf = file_get_contents(base_path('resources/views/documents/operation-quote-generator-pdf.blade.php'));
    $manager = file_get_contents(base_path('app/Support/Operations/CoordinationServiceQuoteManager.php'));

    expect($issuer)
        ->toContain("'quantity' => \$line['quantity']")
        ->toContain("'line_total_usd' => \$line['line_total']")
        ->toContain("array_column(\$lines, 'line_total')")
        ->and($pdf)
        ->toContain('<th class="num">Cant.</th>')
        ->toContain('<th class="num">Total (USD)</th>')
        ->toContain('QuoteLinePricing::quantity($item)')
        ->and($manager)
        ->toContain('quotedMedicationQuantities($quote)')
        ->not->toContain("'quantity' => 1,\n                'indications'");
});
