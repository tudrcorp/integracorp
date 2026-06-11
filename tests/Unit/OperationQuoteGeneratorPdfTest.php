<?php

declare(strict_types=1);

use App\Models\OperationCoordinationService;
use App\Models\OperationQuoteGenerator;
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
        'costo_bolivares' => 2000,
        'porcentaje_ganancia' => 10,
        'subtotal' => 20,
        'total' => 22,
        'items' => [
            [
                'category' => 'Laboratorio',
                'label' => 'Hemograma',
                'detail' => 'Completo',
                'coverage_label' => 'No cubierto',
            ],
        ],
    ]);
    $quote->id = 5;
    $quote->setAttribute('created_at', now());

    $html = view('documents.operation-quote-generator-pdf', [
        'quote' => $quote,
        'coordination' => $coordination,
        'bcvRate' => 100,
        'logoDataUri' => '',
    ])->render();

    expect($html)
        ->toContain('Cotización de servicios')
        ->toContain('Hemograma')
        ->toContain('Tasa BCV aplicada')
        ->toContain('Proveedor')
        ->toContain('Av. Principal, Caracas')
        ->toContain('Observaciones')
        ->toContain('Entrega en 48 horas');
});

it('migration agrega quote_pdf_path a operation_quote_generators', function (): void {
    $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_05_19_000504_add_quote_pdf_path_to_operation_quote_generators_table.php');

    expect($migration)->toContain("->string('quote_pdf_path')");
});
