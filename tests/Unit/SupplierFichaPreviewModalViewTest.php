<?php

declare(strict_types=1);

use App\Models\Supplier;
use App\Services\SupplierFichaPdfService;
use Illuminate\Support\Facades\View;

uses(Tests\TestCase::class);

it('el modal de ficha PDF incluye mensaje de espera y rutas esperadas', function (): void {
    $html = View::make('filament.operations.suppliers.supplier-ficha-preview-modal', [
        'pdfPreviewUrl' => 'https://example.test/operations/suppliers/1/ficha/preview',
        'pdfDownloadUrl' => 'https://example.test/operations/suppliers/1/ficha/download',
        'supplierLabel' => 'Clínica Demo',
    ])
        ->withErrors([])
        ->render();

    expect($html)
        ->toContain('Generando la ficha en PDF')
        ->toContain('x-ref="pdfPreview"')
        ->toContain('pdfPreviewLoading');
});

it('nombre de archivo de descarga de ficha sigue el patrón esperado', function (): void {
    $supplier = new Supplier;
    $supplier->id = 7;

    expect(SupplierFichaPdfService::downloadFilename($supplier))->toBe('Ficha-Proveedor-7.pdf');
});
