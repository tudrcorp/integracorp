<?php

declare(strict_types=1);

use App\Models\OperationCoordinationService;
use App\Models\OperationQuoteGenerator;
use App\Models\Supplier;
use App\Services\OperationQuoteGeneratorPdfService;

uses(Tests\TestCase::class);

it('OperationCoordinationServicesTable genera y almacena pdf al persistir cotización', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Tables/OperationCoordinationServicesTable.php');

    expect($contents)
        ->toContain('OperationQuoteGeneratorPdfService::store')
        ->toContain('quote_pdf_path')
        ->toContain('renderQuoteGeneratorPdfCell')
        ->toContain('Documento');
});

it('OperationQuoteGeneratorPdfService define nombre de archivo por id', function (): void {
    $quote = new OperationQuoteGenerator;
    $quote->id = 12;

    expect(OperationQuoteGeneratorPdfService::filename($quote))->toBe('cotizacion-coordinacion-12.pdf');
});

it('operation quote generator pdf blade renders without errors', function (): void {
    $coordination = new OperationCoordinationService([
        'patient' => 'Paciente Demo',
        'reference_number' => 'REF-001',
    ]);

    $quote = new OperationQuoteGenerator([
        'type_service' => 'LABORATORIOS',
        'supplier_address' => 'Av. Principal, Caracas',
        'observations' => 'Entrega en 48 horas. Requiere ayuno de 8 horas.',
        'status' => OperationQuoteGenerator::STATUS_PENDING,
        'costo_dolares' => 20,
        'costo_bolivares' => 2200,
        'porcentaje_ganancia' => 10,
        'subtotal' => 20,
        'total' => 22,
        'items' => [
            [
                'category' => 'Laboratorio',
                'label' => 'Hemograma',
                'detail' => 'Completo',
                'coverage_label' => 'No cubierto',
                'unit_price_usd' => 10,
                'unit_price_ves' => 1000,
            ],
        ],
    ]);
    $quote->id = 5;
    $quote->setAttribute('created_at', now());
    $quote->setRelation('supplier', new Supplier([
        'name' => 'Laboratorio Central',
        'personal_phone' => '04141234567',
    ]));

    $html = view('documents.operation-quote-generator-pdf', [
        'quote' => $quote,
        'coordination' => $coordination,
        'bcvRate' => 100,
        'logoDataUri' => '',
    ])->render();

    expect($html)
        ->toContain('Cotización de servicios')
        ->toContain('Hemograma')
        ->toContain('Proveedor')
        ->toContain('Laboratorio Central')
        ->toContain('Teléfono')
        ->toContain('04141234567')
        ->toContain('Av. Principal, Caracas')
        ->toContain('Observaciones')
        ->toContain('section-title--observations')
        ->toContain('Resumen de cotización')
        ->toContain('width: 110px')
        ->toContain('font-size: 8pt')
        ->toContain('Entrega en 48 horas')
        ->toContain('US$ 22,00')
        ->toContain('US$ 11,00')
        ->and($html)->not->toContain('Ganancia aplicada')
        ->and($html)->not->toContain('Tasa BCV aplicada')
        ->and($html)->not->toContain('P. unit. (Bs.)')
        ->and($html)->not->toContain('Total (Bs.)')
        ->and($html)->not->toContain('US$ 20,00')
        ->and($html)->not->toContain('US$ 10,00');
});

it('migration agrega quote_pdf_path a operation_quote_generators', function (): void {
    $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_05_19_000504_add_quote_pdf_path_to_operation_quote_generators_table.php');

    expect($migration)->toContain("->string('quote_pdf_path')");
});
