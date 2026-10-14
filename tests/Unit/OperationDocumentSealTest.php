<?php

declare(strict_types=1);

use App\Support\Operations\OperationDocumentSeal;

uses(Tests\TestCase::class);

dataset('documentos de operaciones con sello', [
    'orden de servicio' => ['OperationServiceOrderPdfService', 'operation-service-order-pdf'],
    'cotización asociada' => ['OperationServiceOrderQuotePdfService', 'operation-service-order-quote-pdf'],
    'cotización de medicamentos' => ['OperationServiceOrderMedicationQuotePdfService', 'operation-service-order-medication-quote-pdf'],
    'cotización del generador' => ['OperationQuoteGeneratorPdfService', 'operation-quote-generator-pdf'],
]);

it('embebe el sello de la empresa como PNG', function (): void {
    expect(is_file(public_path(OperationDocumentSeal::FILE)))->toBeTrue()
        ->and(OperationDocumentSeal::dataUri())->toStartWith('data:image/png;base64,')
        ->and(base64_decode(substr(OperationDocumentSeal::dataUri(), strlen('data:image/png;base64,')), true))
        ->toBe(file_get_contents(public_path(OperationDocumentSeal::FILE)));
});

it('pasa el sello al PDF y la plantilla lo imprime', function (string $service, string $view): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Services/'.$service.'.php');
    $template = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/'.$view.'.blade.php');

    expect($source)->toContain("'sealDataUri' => OperationDocumentSeal::dataUri(),")
        ->and($source)->toContain('use App\Support\Operations\OperationDocumentSeal;')
        ->and(substr_count($template, "@include('documents.partials.operations-seal')"))->toBe(1)
        ->and(strpos($template, "@include('documents.partials.operations-seal')"))->toBeLessThan(strpos($template, 'class="footer'));
})->with('documentos de operaciones con sello');

it('dibuja el sello en un bloque que no se parte entre hojas', function (): void {
    $html = view('documents.partials.operations-seal', ['sealDataUri' => 'data:image/png;base64,AAAA'])->render();

    expect($html)->toContain('<img src="data:image/png;base64,AAAA" alt="Sello de Tu Doctor en Casa"')
        ->and($html)->toContain('page-break-inside: avoid')
        ->and($html)->toContain('table-layout: fixed');
});

it('omite el sello sin romper el documento si la imagen no está', function (?string $seal): void {
    $data = $seal === null ? [] : ['sealDataUri' => $seal];

    expect(trim(view('documents.partials.operations-seal', $data)->render()))->toBe('');
})->with(['vacío' => [''], 'sin variable' => [null]]);

it('reserva el alto del pie fijo para que el resumen y el sello no se monten sobre él', function (string $view): void {
    $template = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/'.$view.'.blade.php');

    expect($template)->toContain('margin: 18mm 20mm 34mm 20mm;');
})->with(['operation-service-order-quote-pdf', 'operation-quote-generator-pdf']);
