<?php

declare(strict_types=1);

use Illuminate\Support\Facades\View;

uses(Tests\TestCase::class);

it('el modal de reporte de proveedores incluye mensaje de espera para la vista previa PDF', function (): void {
    $html = View::make('filament.operations.suppliers.suppliers-report-modal', [
        'pdfPreviewUrl' => 'https://example.test/operations/suppliers/report/preview',
        'pdfDownloadUrl' => 'https://example.test/operations/suppliers/report/download',
    ])
        ->withErrors([])
        ->render();

    expect($html)
        ->toContain('Generando el PDF')
        ->toContain('preparando la vista previa')
        ->toContain('x-ref="pdfPreview"')
        ->toContain('pdfPreviewLoading');
});
