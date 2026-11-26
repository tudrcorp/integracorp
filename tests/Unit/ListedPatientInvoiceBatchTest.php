<?php

declare(strict_types=1);

use App\Support\Operations\ListedPatientInvoiceBatch;
use App\Support\Operations\QuoteLinePricing;
use App\Support\Operations\TelemedicineCaseClaimNumber;

it('el lote trae las 9 órdenes del listado, cada una con un siniestro válido', function (): void {
    $rows = ListedPatientInvoiceBatch::rows();
    $orders = array_column($rows, 'order');

    expect($rows)->toHaveCount(9)
        ->and($orders)->toHaveCount(count(array_unique($orders)))
        ->and($orders)->toContain('ORD-0683', 'ORD-0693', 'ORD-0691');

    foreach ($rows as $row) {
        expect(TelemedicineCaseClaimNumber::problem(TelemedicineCaseClaimNumber::normalize($row['claim'])))->toBeNull();
    }
});

it('elige al paciente correcto cuando el número de orden está repetido y descarta el que no coincide', function (): void {
    $angel = ListedPatientInvoiceBatch::pick('Angel Custodio Araujo', [
        ['id' => 671, 'name' => 'ANGEL CUSTODIO ARAUJO'],
        ['id' => 672, 'name' => 'CARRASCO GONZALEZ EVERT'],
    ]);
    $emma = ListedPatientInvoiceBatch::pick('Emma Mosquera', [
        ['id' => 818, 'name' => 'EMMA MACIEL MOSQUERA TORRES'],
        ['id' => 819, 'name' => 'YUSNELLY DEL CARMEN HERNANDEZ CASTELLANOS'],
    ]);
    $eloisa = ListedPatientInvoiceBatch::matchScore('ELOISA ALVAREZ HERNANDEZ', 'ALVAREZ HENRIQUEZ ELOISA');
    $nelo = ListedPatientInvoiceBatch::matchScore('María Victoria Nelo de Rojas', 'MARIA VICTORIA NELO INFANTE');
    $juan = ListedPatientInvoiceBatch::pick('JUAN MEDINA', [
        ['id' => 833, 'name' => 'ILVERT IGNACIO GARCIA PEREZ'],
    ]);
    $janimar = ListedPatientInvoiceBatch::pick('Janimar Figueria', [
        ['id' => 639, 'name' => 'JANIMAR CHIQUINQUIRA FIGUEROA GRATEROL'],
    ]);

    expect($angel)->toMatchArray(['id' => 671])
        ->and($emma)->toMatchArray(['id' => 818])
        ->and($eloisa)->toBeGreaterThanOrEqual(2)
        ->and($nelo)->toBeGreaterThanOrEqual(2)
        ->and($juan)->toBeNull()
        ->and($janimar)->toMatchArray(['id' => 639]);
});

it('no elige cuando dos pacientes empatan', function (): void {
    $picked = ListedPatientInvoiceBatch::pick('MARIA PEREZ', [
        ['id' => 1, 'name' => 'MARIA JOSE PEREZ'],
        ['id' => 2, 'name' => 'MARIA LUISA PEREZ'],
    ]);

    expect($picked)->toBeNull();
});

it('cobra el porcentaje global de la cotización en cada línea y no pisa un porcentaje que ya trae el ítem', function (): void {
    $items = ListedPatientInvoiceBatch::itemsWithSaleProfit([
        ['key' => 'medication:1', 'label' => 'ATAMEL', 'unit_price_usd' => 0.54],
        ['key' => 'medication:2', 'label' => 'SPRAY', 'unit_price_usd' => 10.50],
    ], 20);

    expect(QuoteLinePricing::totals($items)['total'])->toBe(13.25);

    $kept = ListedPatientInvoiceBatch::itemsWithSaleProfit([
        ['key' => 'medication:1', 'label' => 'UNO', 'unit_price_usd' => 10, 'profit_percentage' => 10],
    ], 20);

    expect($kept[0]['profit_percentage'])->toBe(10);

    $flagged = ListedPatientInvoiceBatch::itemsWithSaleProfit([
        ['key' => 'medication:1', 'label' => 'UNO', 'unit_price_usd' => 10, QuoteLinePricing::ITEM_PRICING_FLAG => true],
    ], 20);

    expect($flagged[0])->not->toHaveKey('profit_percentage');
});

it('el comando no escribe si no se pide ejecutar', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Console/Commands/IssueListedPatientInvoicesCommand.php');

    expect($source)->toContain('--execute')
        ->and($source)->toContain('ListedPatientInvoiceBatch::issue')
        ->and($source)->toContain('Vista previa');
});
